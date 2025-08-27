<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\PdfPurchaseRequest;
use App\Models\ApprovalHistory;
use App\Models\Group;
use App\Models\ApprovalRank;
use App\Models\User;
use App\Models\Section;
use App\Jobs\SendPdfApprovalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfApprovalController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $user->load('assignments.approvalRank', 'assignments.group', 'sections', 'mainBranch');

        $requesters = User::whereHas('pdfPurchaseRequests')->orderBy('name')->get();
        $sections = Section::orderBy('name')->get();

        $pdfPurchaseRequestsQuery = PdfPurchaseRequest::query()
            ->with(['requester.sections', 'requester.mainBranch']);

        $userIsPurchasingGroup = $user->assignments->contains(fn($assignment) => $assignment->group->name === 'Phòng Mua');
        $userIsRequestingGroup = $user->assignments->contains(fn($assignment) => $assignment->group->name === 'Phòng Đề Nghị');

        if ($userIsPurchasingGroup) {
            $pdfPurchaseRequestsQuery->where('status', 'purchasing_approval');
        } elseif ($userIsRequestingGroup) {
            $pdfPurchaseRequestsQuery->where('status', 'pending_approval');
        } else {
            $pdfPurchaseRequestsQuery->whereIn('status', ['pending_approval', 'purchasing_approval']);
        }

        if ($request->filled('pia_code')) {
            $pdfPurchaseRequestsQuery->where('pia_code', 'like', '%' . $request->input('pia_code') . '%');
        }
        if ($request->filled('requester_id')) {
            $pdfPurchaseRequestsQuery->where('requester_id', $request->input('requester_id'));
        }
        if ($request->filled('section_id')) {
            $sectionId = $request->input('section_id');
            $pdfPurchaseRequestsQuery->whereHas('requester.sections', function ($q) use ($sectionId) {
                $q->where('sections.id', $sectionId);
            });
        }

        $pendingPdfRequests = $pdfPurchaseRequestsQuery->get()->filter(function ($pdfPr) use ($user) {
            return $this->userCanApprovePdf($user, $pdfPr);
        });

        $pendingPdfRequests = $pendingPdfRequests->sortByDesc('created_at');

        $page = Paginator::resolveCurrentPage() ?: 1;
        $perPage = 15;
        $total = $pendingPdfRequests->count();
        $results = $pendingPdfRequests->slice(($page - 1) * $perPage, $perPage)->all();

        $paginatedPdfRequests = new LengthAwarePaginator($results, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return view('users.pdf_approvals.index', compact('paginatedPdfRequests', 'requesters', 'sections'));
    }

    public function history(Request $request)
    {
        $user = Auth::user();
        $sections = Section::orderBy('name')->get();
        $requesters = User::whereHas('pdfPurchaseRequests')->orderBy('name')->get();

        $pdfPurchaseRequestsQuery = PdfPurchaseRequest::query()
            ->with(['requester', 'approvalHistories'])
            ->whereHas('approvalHistories', function ($historyQuery) use ($user) {
                $historyQuery->where('user_id', $user->id)->where('action', 'approved');
            });

        if ($request->filled('pia_code')) {
            $pdfPurchaseRequestsQuery->where('pia_code', 'like', '%' . $request->input('pia_code') . '%');
        }
        if ($request->filled('requester_id')) {
            $pdfPurchaseRequestsQuery->where('requester_id', $request->input('requester_id'));
        }
        if ($request->filled('section_id')) {
            $sectionId = $request->input('section_id');
            $pdfPurchaseRequestsQuery->whereHas('requester.sections', function ($q) use ($sectionId) {
                $q->where('sections.id', $sectionId);
            });
        }

        $approvedPdfRequests = $pdfPurchaseRequestsQuery->latest()->paginate(15);

        return view('users.pdf_approvals.history', compact('approvedPdfRequests', 'sections', 'requesters'));
    }

    public function approve(Request $request, PdfPurchaseRequest $pdfPurchaseRequest)
    {
        if (!$this->userCanApprovePdf(Auth::user(), $pdfPurchaseRequest)) {
            abort(403, 'Bạn không có quyền duyệt phiếu PDF này.');
        }

        $request->validate(['comment' => 'nullable|string|max:2000']);

        DB::beginTransaction();
        try {
            $this->performApprovalLogic($pdfPurchaseRequest, $request->comment);
            DB::commit();
            return back()->with('success', "Đã duyệt phiếu PDF {$pdfPurchaseRequest->pia_code} thành công.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Approve Error for PDF PR {$pdfPurchaseRequest->id}: " . $e->getMessage());
            return back()->with('error', "Lỗi khi duyệt phiếu PDF: " . $e->getMessage());
        }
    }

    public function reject(Request $request, PdfPurchaseRequest $pdfPurchaseRequest)
    {
        if (!$this->userCanApprovePdf(Auth::user(), $pdfPurchaseRequest)) {
            abort(403, 'Bạn không có quyền từ chối phiếu PDF này.');
        }
        $request->validate(['comment' => 'required|string|max:2000']);

        DB::beginTransaction();
        try {
            $this->performRejectionLogic($pdfPurchaseRequest, $request->comment);
            DB::commit();
            return back()->with('success', "Đã từ chối phiếu PDF {$pdfPurchaseRequest->pia_code} thành công.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Reject Error for PDF PR {$pdfPurchaseRequest->id}: " . $e->getMessage());
            return back()->with('error', "Lỗi khi từ chối phiếu PDF: " . $e->getMessage());
        }
    }

    public function bulkApprove(Request $request)
    {
        $request->validate([
            'request_ids' => 'required|array|min:1',
            'request_ids.*' => 'integer',
        ]);

        $ids = $request->input('request_ids');
        $approvedCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($ids as $id) {
            $pdfPurchaseRequest = PdfPurchaseRequest::find($id);

            if (!$pdfPurchaseRequest || !$this->userCanApprovePdf(Auth::user(), $pdfPurchaseRequest)) {
                $errors[] = "Không có quyền hoặc không tìm thấy phiếu PDF ID {$id}.";
                $failedCount++;
                continue;
            }

            DB::beginTransaction();
            try {
                $this->performApprovalLogic($pdfPurchaseRequest, 'Duyệt hàng loạt');
                DB::commit();
                $approvedCount++;
            } catch (\Exception $e) {
                DB::rollBack();
                $errors[] = "Lỗi khi duyệt phiếu PDF ID {$id}: " . $e->getMessage();
                Log::error("Bulk Approve Error for PDF PR ID {$id}: " . $e->getMessage());
                $failedCount++;
            }
        }

        $message = "Đã duyệt thành công {$approvedCount} phiếu.";
        if ($failedCount > 0) {
            $message .= " Thất bại {$failedCount} phiếu.";
        }
        return back()->with('success', $message);
    }

    private function performApprovalLogic(PdfPurchaseRequest $model, $comment = null)
    {
        $user = Auth::user();
        $user->load('assignments.group', 'assignments.approvalRank');
        $currentRankLevel = $model->current_rank_level;

        Log::info('--- Bắt đầu Approval Logic ---');
        Log::info("PR ID: {$model->id}, Mã: {$model->pia_code}");
        Log::info("Trạng thái và Cấp độ hiện tại: {$model->status} - {$model->current_rank_level}");

        $currentPdfPath = $model->signed_pdf_path
            ? Storage::disk('public')->path($model->signed_pdf_path)
            : Storage::disk('public')->path($model->original_pdf_path);

        if (!file_exists($currentPdfPath)) {
            throw new \Exception("Không tìm thấy file PDF hiện tại để thêm chữ ký.");
        }

        $userSignaturePath = Storage::disk('public')->path($user->signature_image_path);
        if (!file_exists($userSignaturePath)) {
            throw new \Exception("Không tìm thấy ảnh chữ ký của người duyệt.");
        }

        $x = $model->signature_pos_x ?? 85;
        $y = $model->signature_pos_y ?? 50;
        $width = $model->signature_width ?? 15;
        $height = $model->signature_height ?? 12;
        $page = $model->signature_page ?? 1;

        $lastSignedHistory = ApprovalHistory::where('pdf_purchase_request_id', $model->id)
            ->whereIn('action', ['signed_and_submitted', 'approved'])
            ->whereNotNull('signature_position')
            ->latest('created_at')
            ->first();

        $lastPosition = null;
        if ($lastSignedHistory) {
            if (is_string($lastSignedHistory->signature_position)) {
                $lastPosition = json_decode($lastSignedHistory->signature_position, true);
            } else {
                $lastPosition = $lastSignedHistory->signature_position;
            }
        }

        $currentX = $lastPosition ? ($lastPosition['x'] ?? $x) : $x;
        $offsetX = ($lastPosition['width'] ?? $width) + 13;
        $nextX = $currentX + $offsetX;
        $now = now()->format('H:i:s d/m/Y ');

        $tempSignedPdfFileName = 'PR_Signed_Temp_' . $model->pia_code . '_' . time() . '.pdf';
        $tempSignedPdfPath = 'pr_pdfs/signed/' . $tempSignedPdfFileName;
        $tempOutputFilePath = Storage::disk('public')->path($tempSignedPdfPath);

        DB::beginTransaction();
        try {
            $pdf = new Fpdi();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetDrawColor(255, 255, 255);
            $pdf->SetLineWidth(0);
            $pdf->SetCellPaddings(0, 0, 0, 0);
            $pageCount = $pdf->setSourceFile($currentPdfPath);

            if ($page > $pageCount) {
                throw new \Exception("Trang ký ({$page}) vượt quá số trang của PDF gốc ({$pageCount}).");
            }

            for ($i = 1; $i <= $pageCount; $i++) {
                $tplId = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tplId);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId);

                if ($i == $page) {
                    $pdf->Image($userSignaturePath, $nextX, $y, $width, $height, '', '', '', false, 300, '', false, false, false);
                    $pdf->SetFont('helvetica', '', 7);
                    $pdf->SetTextColor(0, 0, 0);
                    $timeX = $nextX - 1;
                    $timeY = $y + $height + 4;
                    $pdf->Text($timeX, $timeY, $now);

                    if ($currentRankLevel == 3 && !$model->requires_director_approval) {
                        $nextX_duplicate = $nextX + $offsetX;
                        $pdf->Image($userSignaturePath, $nextX_duplicate, $y, $width, $height, '', '', '', false, 300, '', false, false, false);

                        $timeX_duplicate = $nextX_duplicate - 2;
                        $pdf->Text($timeX_duplicate, $timeY, $now);

                        $nextX = $nextX_duplicate;
                    }
                }
            }

            Storage::disk('public')->makeDirectory('pr_pdfs/signed');
            $pdf->Output($tempOutputFilePath, 'F');

            if ($model->signed_pdf_path && Storage::disk('public')->exists($model->signed_pdf_path)) {
                Storage::disk('public')->delete($model->signed_pdf_path);
            }

            $model->signature_pos_x = $nextX;
            $model->signature_pos_y = $y;
            $model->signature_width = $width;
            $model->signature_height = $height;
            $model->signature_page = $page;
            $model->signed_pdf_path = $tempSignedPdfPath;

            $rankAtApproval = 'Unknown';
            foreach ($user->assignments as $assignment) {
                if ($assignment->approvalRank->rank_level === $currentRankLevel) {
                    $rankAtApproval = $assignment->group->name . ' Cấp ' . $assignment->approvalRank->rank_level;
                    break;
                }
            }

            $signaturePositionData = json_encode([
                'x' => $nextX,
                'y' => $y,
                'width' => $width,
                'height' => $height,
                'page' => $page,
            ]);

            ApprovalHistory::create([
                'pdf_purchase_request_id' => $model->id,
                'user_id' => $user->id,
                'rank_at_approval' => $rankAtApproval,
                'action' => 'approved',
                'signature_image_path' => $user->signature_image_path ?? 'no-signature.png',
                'comment' => $comment,
                'signature_position' => $signaturePositionData,
            ]);

            $approverGroup = Auth::user()->assignments->where('approvalRank.rank_level', $currentRankLevel)->first()->group->name ?? null;
            $oldStatus = $model->status;
            $oldRankLevel = $model->current_rank_level;

            if ($approverGroup === 'Phòng Đề Nghị') {
                if ($currentRankLevel == 2) {
                    $model->current_rank_level = 3;
                } elseif ($currentRankLevel == 3) {
                    if ($model->requires_director_approval) {
                        $model->current_rank_level = 4;
                    } else {
                        $model->status = 'purchasing_approval';
                        $model->current_rank_level = 2;
                        SendPdfApprovalNotification::dispatch($model, $model->requester, 'completion_requesting_group');
                    }
                } elseif ($currentRankLevel == 4) {
                    $model->status = 'purchasing_approval';
                    $model->current_rank_level = 2;
                    SendPdfApprovalNotification::dispatch($model, $model->requester, 'completion_requesting_group');
                } else {
                    $model->current_rank_level++;
                }
            } elseif ($approverGroup === 'Phòng Mua') {
                if ($currentRankLevel == 2) {
                    $model->current_rank_level = 4;
                } elseif ($currentRankLevel == 4) {
                    $model->status = 'completed';
                    SendPdfApprovalNotification::dispatch($model, $model->requester, 'completion_all');
                }
            } else {
                $model->current_rank_level++;
            }

            $model->save();

            Log::info("Phiếu sau khi lưu: Status: {$model->status}, Current Rank: {$model->current_rank_level}");

            DB::commit();

            $nextApprovers = $this->findNextApproversForPdfPurchaseRequest($model);
            if ($nextApprovers->isNotEmpty()) {
                foreach ($nextApprovers as $approver) {
                    SendPdfApprovalNotification::dispatch($model, $approver, 'approval');
                }
                Log::info('DEBUG: PDF Notification jobs dispatched for next approvers for PR: ' . $model->id);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Perform Approval Error for PDF PR {$model->id}: " . $e->getMessage());
            throw $e;
        }
        Log::info('--- Kết thúc Approval Logic thành công ---');
    }
    public function bulkReject(Request $request)
    {
        $request->validate([
            'request_ids' => 'required|array|min:1',
            'request_ids.*' => 'integer',
            'comment' => 'required|string|max:2000',
        ]);

        $ids = $request->input('request_ids');
        $comment = $request->input('comment');
        $rejectedCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($ids as $id) {
            $pdfPurchaseRequest = PdfPurchaseRequest::find($id);

            if (!$pdfPurchaseRequest || !$this->userCanApprovePdf(Auth::user(), $pdfPurchaseRequest)) {
                $errors[] = "Không có quyền hoặc không tìm thấy phiếu PDF ID {$id}.";
                $failedCount++;
                continue;
            }

            DB::beginTransaction();
            try {
                $this->performRejectionLogic($pdfPurchaseRequest, $comment);
                DB::commit();
                $rejectedCount++;
            } catch (\Exception $e) {
                DB::rollBack();
                $errors[] = "Lỗi khi từ chối phiếu PDF ID {$id}: " . $e->getMessage();
                Log::error("Bulk Reject Error for PDF PR ID {$id}: " . $e->getMessage());
                $failedCount++;
            }
        }

        $message = "Đã từ chối thành công {$rejectedCount} phiếu.";
        if ($failedCount > 0) {
            $message .= " Thất bại {$failedCount} phiếu.";
        }
        return back()->with('success', $message);
    }
     public function bulkPreview(Request $request)
    {
        // THÊM: Gọi hàm dọn dẹp file tạm thời ngay lập tức
        $this->_runTemporaryPdfCleanup();

        $ids = $request->input('ids');
        if (empty($ids) || !is_array($ids)) {
            return redirect()->route('users.pdf-approvals.index')->with('error', 'Không có phiếu nào được chọn để xem trước.');
        }

        $pdfPurchaseRequests = PdfPurchaseRequest::whereIn('id', $ids)
                                                ->with('requester')
                                                ->get()
                                                ->filter(function($pdfPr) {
                                                    return $this->userCanApprovePdf(Auth::user(), $pdfPr);
                                                });

        if ($pdfPurchaseRequests->isEmpty()) {
            return redirect()->route('users.pdf-approvals.index')->with('error', 'Không tìm thấy phiếu nào hoặc bạn không có quyền xem trước các phiếu này.');
        }

        // Gộp tất cả các file PDF đã chọn thành một file duy nhất
        try {
            $mergedPdfPath = $this->mergePdfsForPreview($pdfPurchaseRequests);
        } catch (\Exception $e) {
            Log::error("Error merging PDFs for bulk preview: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->route('users.pdf-approvals.index')->with('error', 'Đã xảy ra lỗi khi gộp các file PDF để xem trước: ' . $e->getMessage());
        }

        // Trả về file PDF đã gộp trực tiếp
        $filePath = Storage::disk('public')->path($mergedPdfPath);

        if (!file_exists($filePath)) {
            Log::error("Merged PDF file not found at: " . $filePath);
            return redirect()->route('users.pdf-approvals.index')->with('error', 'Không tìm thấy file PDF đã gộp.');
        }

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
        ];

        return response()->file($filePath, $headers);
    }
private function _runTemporaryPdfCleanup(): void
    {
        Log::info('--- Bắt đầu dọn dẹp file PDF tạm thời từ web request ---');
        $directory = 'pr_pdfs/temp_merged';
        $files = Storage::disk('public')->files($directory);
        $deletedCount = 0;
        $hoursOld = 1/2; // Xóa file cũ hơn 1 giờ

        foreach ($files as $file) {
            try {
                if (Storage::disk('public')->lastModified($file) < now()->subHours($hoursOld)->timestamp) {
                    Storage::disk('public')->delete($file);
                    $deletedCount++;
                    Log::info("Đã xóa file PDF tạm thời: {$file}");
                }
            } catch (\Exception $e) {
                Log::error("Lỗi khi xóa file PDF tạm thời {$file}: " . $e->getMessage());
            }
        }
        Log::info("Hoàn tất dọn dẹp. Đã xóa {$deletedCount} file PDF tạm thời.");
    }
     private function mergePdfsForPreview(Collection $pdfPurchaseRequests): string
    {
        $pdf = new Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(true, 10); // Tự động ngắt trang với lề dưới 10mm

        $tempMergedFileName = 'merged_preview_' . time() . '_' . Str::random(8) . '.pdf';
        $tempMergedFilePath = 'pr_pdfs/temp_merged/' . $tempMergedFileName;
        $outputFilePath = Storage::disk('public')->path($tempMergedFilePath);

        // Đảm bảo thư mục tồn tại
        Storage::disk('public')->makeDirectory('pr_pdfs/temp_merged');

        foreach ($pdfPurchaseRequests as $pdfPr) {
            $filePath = $pdfPr->signed_pdf_path
                ? Storage::disk('public')->path($pdfPr->signed_pdf_path)
                : Storage::disk('public')->path($pdfPr->original_pdf_path);

            if (!file_exists($filePath)) {
                Log::warning("File PDF not found for merging: " . $filePath);
                continue; // Bỏ qua file nếu không tìm thấy
            }

            try {
                $pageCount = $pdf->setSourceFile($filePath);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $tplId = $pdf->importPage($i);
                    $size = $pdf->getTemplateSize($tplId);

                    // Thêm trang mới với kích thước và hướng của trang gốc
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($tplId);


                }
            } catch (\Exception $e) {
                Log::error("Error importing PDF page for merging (PR ID: {$pdfPr->id}): " . $e->getMessage());
                continue;
            }
        }

        $pdf->Output($outputFilePath, 'F');

        return $tempMergedFilePath;
    }

    private function performRejectionLogic(PdfPurchaseRequest $model, $comment)
    {
        $user = Auth::user();
        $user->load('assignments.group', 'assignments.approvalRank');
        $currentRankLevel = $model->current_rank_level;

        $rankAtApproval = 'Unknown';
        foreach ($user->assignments as $assignment) {
            if ($assignment->approvalRank->rank_level === $currentRankLevel) {
                $rankAtApproval = $assignment->group->name . ' Cấp ' . $assignment->approvalRank->rank_level;
                break;
            }
        }

        $model->status = 'rejected';
        $model->rejection_reason = $comment;
        $model->save();

        ApprovalHistory::create([
            'pdf_purchase_request_id' => $model->id,
            'user_id' => $user->id,
            'rank_at_approval' => $rankAtApproval,
            'action' => 'rejected',
            'signature_image_path' => $user->signature_image_path ?? 'no-signature.png',
            'comment' => $comment,
        ]);

        $allInvolvedUsers = User::whereHas('approvalHistories', function($q) use ($model) {
            $q->where('pdf_purchase_request_id', $model->id)
              ->whereIn('action', ['signed_and_submitted', 'approved']);
        })->get();

        if (!$allInvolvedUsers->contains('id', $model->requester->id)) {
            $allInvolvedUsers->push($model->requester);
        }

        foreach ($allInvolvedUsers as $recipient) {
            SendPdfApprovalNotification::dispatch($model, $recipient, 'rejection');
            Log::info("DEBUG: Rejection notification dispatched for PR: {$model->id} to user: {$recipient->email}");
        }
    }

    public function userCanApprovePdf($user, $pdfPr): bool
    {
        Log::info('--- Checking userCanApprovePdf ---');
        Log::info("User ID: {$user->id}, PR ID: {$pdfPr->id}, PR Status: {$pdfPr->status}, Current Rank: {$pdfPr->current_rank_level}");

        if ($pdfPr->status !== 'pending_approval' && $pdfPr->status !== 'purchasing_approval') {
            Log::info('Condition failed: PR status is not pending_approval or purchasing_approval.');
            return false;
        }

        $userAssignments = $user->assignments;
        $requiredRankLevel = $pdfPr->current_rank_level;
        $pdfPrSectionId = $pdfPr->requester->sections->first()->id ?? null;
        $userSectionIds = $user->sections->pluck('id');

        foreach ($userAssignments as $assignment) {
            Log::info("   - User Assignment: Group: {$assignment->group->name}, Rank: {$assignment->approvalRank->rank_level}");

            if ($assignment->approvalRank->rank_level === $requiredRankLevel) {
                if ($assignment->group->name === 'Phòng Đề Nghị') {
                    if ($requiredRankLevel < 4) {
                        if ($userSectionIds->contains($pdfPrSectionId)) {
                            Log::info('   -> Approved (Phòng Đề Nghị, rank < 4, section match)');
                            return true;
                        }
                    } elseif ($requiredRankLevel === 4) {
                        if ($pdfPr->requires_director_approval) {
                            Log::info('   -> Approved (Phòng Đề Nghị, rank 4, director approval required)');
                            return true;
                        }
                    }
                } elseif ($assignment->group->name === 'Phòng Mua') {
                    if ($requiredRankLevel === 2 || $requiredRankLevel === 3) {
                        if ($userSectionIds->contains($pdfPrSectionId)) {
                            Log::info('   -> Approved (Phòng Mua, rank < 4, section match)');
                            return true;
                        }
                    } elseif ($requiredRankLevel === 4) {
                        Log::info('   -> Approved (Phòng Mua, rank 4, auto-approved)');
                        return true;
                    }
                }
            }
        }
        Log::info('--- userCanApprovePdf returns false ---');
        return false;
    }

    public function findNextApproversForPdfPurchaseRequest(PdfPurchaseRequest $pdfPr): Collection
    {
        Log::info('--- Checking findNextApproversForPdfPurchaseRequest ---');
        Log::info("PR ID: {$pdfPr->id}, Status: {$pdfPr->status}, Next Rank: {$pdfPr->current_rank_level}");

        if (in_array($pdfPr->status, ['completed', 'rejected'])) {
            Log::info('Condition failed: PR is completed or rejected.');
            return collect();
        }

        $nextRankLevel = $pdfPr->current_rank_level;
        $sectionId = $pdfPr->requester->sections->first()->id ?? null;
        $isRequestingStage = $pdfPr->status === 'pending_approval';

        $groupName = $isRequestingStage ? 'Phòng Đề Nghị' : 'Phòng Mua';
        $targetGroupId = Group::where('name', $groupName)->value('id');

        Log::info("Target Group: {$groupName}, Target Group ID: {$targetGroupId}");
        Log::info("Requester Section ID: {$sectionId}");

        if (!$targetGroupId) {
            Log::error("Group '{$groupName}' not found for PDF PR ID {$pdfPr->id}");
            return collect();
        }

        if ($nextRankLevel < 4 && !$sectionId) {
            Log::warning('Condition failed: Next rank is < 4 and sectionId is missing.');
            return collect();
        }

        $approverQuery = User::query()
            ->whereHas('assignments', function ($q) use ($nextRankLevel, $targetGroupId) {
                $q->whereHas('approvalRank', fn($r) => $r->where('rank_level', $nextRankLevel))
                    ->where('group_id', $targetGroupId);
            });

        if ($nextRankLevel < 4) {
            $approverQuery->whereHas('sections', fn($q) => $q->where('sections.id', $sectionId));
        }

        if ($groupName === 'Phòng Đề Nghị' && $nextRankLevel === 4 && !$pdfPr->requires_director_approval) {
            Log::info('Condition failed: Phòng Đề Nghị, rank 4, but director approval not required.');
            return collect();
        }

        $approvers = $approverQuery->get();
        Log::info("Found {$approvers->count()} next approvers.");
        return $approvers;
    }
}
