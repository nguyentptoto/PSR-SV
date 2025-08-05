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
            ->with(['requester.sections', 'requester.mainBranch'])
            ->where('status', 'pending_approval');

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

        // Lấy thông tin vị trí chữ ký mặc định từ model
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

        // Sửa: Giải mã chuỗi JSON một cách an toàn
 // SỬA LỖI NÀY: KIỂM TRA ĐỂ GIẢI MÃ CHUỖI JSON
        if ($lastSignedHistory && is_string($lastSignedHistory->signature_position)) {
             $lastPosition = json_decode($lastSignedHistory->signature_position, true);
        } else {
             $lastPosition = $lastSignedHistory->signature_position ?? null;
        }
        // Lấy tọa độ x của chữ ký gần nhất
        $currentX = $lastPosition ? ($lastPosition['x'] ?? $x) : $x;

        // Tính toán tọa độ x mới cho chữ ký hiện tại
        $offsetX = ($lastPosition['width'] ?? $width) + 12;
        $nextX = $currentX + $offsetX;

         $now = now()->format('H:i:s d/m/Y ');

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
                // In ảnh chữ ký của người phê duyệt hiện tại
                $pdf->Image($userSignaturePath, $nextX, $y, $width, $height, '', '', '', false, 300, '', false, false, false);
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetTextColor(0, 0, 0);
                $textY_time = $y + $height + 4;
                $pdf->Text($nextX, $textY_time, $now);

                // Xử lý trường hợp chữ ký duplicate
                if ($currentRankLevel == 3 && !$model->requires_director_approval) {
                    $nextX_duplicate = $nextX + $offsetX;
                    $pdf->Image($userSignaturePath, $nextX_duplicate, $y, $width, $height, '', '', '', false, 300, '', false, false, false);
                    $pdf->Text($nextX_duplicate, $textY_time, $now);
                    $nextX = $nextX_duplicate;
                }
            }
        }

        $signedPdfFileName = 'PR_' . $model->pia_code  . '.pdf';
        $signedPdfPath = 'pr_pdfs/signed/' . $signedPdfFileName;
        $outputFilePath = Storage::disk('public')->path($signedPdfPath);
        Storage::disk('public')->makeDirectory('pr_pdfs/signed');
        $pdf->Output($outputFilePath, 'F');

        if ($model->signed_pdf_path && Storage::disk('public')->exists($model->signed_pdf_path)) {
            Storage::disk('public')->delete($model->signed_pdf_path);
        }

        $rankAtApproval = 'Unknown';
        foreach ($user->assignments as $assignment) {
            if ($assignment->approvalRank->rank_level === $currentRankLevel) {
                $rankAtApproval = $assignment->group->name . ' Cấp ' . $assignment->approvalRank->rank_level;
                break;
            }
        }

        $signaturePositionData = json_encode([
            'x' => $nextX, 'y' => $y, 'width' => $width, 'height' => $height, 'page' => $page,
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

        if ($currentRankLevel == 3) {
            if ($model->requires_director_approval) {
                $model->current_rank_level = 4;
            } else {
                $model->status = 'purchasing_approval';
                $model->current_rank_level = 2;
            }
        } elseif ($currentRankLevel == 4) {
            $model->status = 'purchasing_approval';
            $model->current_rank_level = 2;
        } else {
            $model->current_rank_level++;
        }

        $model->signature_pos_x = $nextX;
        $model->signature_pos_y = $y;
        $model->signature_width = $width;
        $model->signature_height = $height;
        $model->signature_page = $page;
        $model->signed_pdf_path = $signedPdfPath;
        $model->save();

        $nextApprovers = $this->findNextApproversForPdfPurchaseRequest($model);
        if ($nextApprovers->isNotEmpty()) {
            foreach ($nextApprovers as $approver) {
                SendPdfApprovalNotification::dispatch($model, $approver);
            }
        }

        DB::commit(); // Sửa: Commit transaction khi thành công

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Perform Approval Error for PDF PR {$model->id}: " . $e->getMessage());
        throw $e;
    }

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

        $requester = $model->requester;
        if ($requester) {
            SendPdfApprovalNotification::dispatch($model, $requester);
        }
    }

    public function userCanApprovePdf($user, $pdfPr): bool
    {
        if ($pdfPr->status !== 'pending_approval') {
            return false;
        }

        $userAssignments = $user->assignments;
        $requiredRankLevel = $pdfPr->current_rank_level;
        $pdfPrBranchId = $pdfPr->requester->mainBranch->id ?? null;
        $pdfPrSectionId = $pdfPr->requester->sections->first()->id ?? null;

        foreach ($userAssignments as $assignment) {
            if ($assignment->approvalRank->rank_level === $requiredRankLevel && $assignment->branch_id === $pdfPrBranchId) {
                if ($assignment->group->name === 'Phòng Đề Nghị') {
                    if ($requiredRankLevel < 4) {
                        if ($user->sections->contains($pdfPrSectionId)) {
                            return true;
                        }
                    } elseif ($requiredRankLevel === 4) {
                        if ($pdfPr->requires_director_approval) {
                            return true;
                        }
                    }
                } elseif ($assignment->group->name === 'Phòng Mua') {
                    if (in_array($requiredRankLevel, [2, 3])) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function findNextApproversForPdfPurchaseRequest(PdfPurchaseRequest $pdfPr): Collection
    {
        if (in_array($pdfPr->status, ['completed', 'rejected'])) {
            return collect();
        }

        $nextRankLevel = $pdfPr->current_rank_level;
        $branchId = $pdfPr->requester->mainBranch->id ?? null;
        $sectionId = $pdfPr->requester->sections->first()->id ?? null;
        $isRequestingStage = $pdfPr->status === 'pending_approval';
        $groupName = $isRequestingStage ? 'Phòng Đề Nghị' : 'Phòng Mua';
        $targetGroupId = Group::where('name', $groupName)->value('id');

        if (!$targetGroupId || !$branchId) {
            return collect();
        }

        if ($isRequestingStage && !$sectionId) {
            return collect();
        }

        $approverQuery = User::query()
            ->whereHas('assignments', function ($q) use ($nextRankLevel, $branchId, $targetGroupId) {
                $q->whereHas('approvalRank', fn($r) => $r->where('rank_level', $nextRankLevel))
                    ->where('branch_id', $branchId)
                    ->where('group_id', $targetGroupId);
            });

        if ($isRequestingStage) {
            $approverQuery->whereHas('sections', fn($q) => $q->where('sections.id', $sectionId));
        }

        if ($isRequestingStage && $nextRankLevel === 4 && !$pdfPr->requires_director_approval) {
            return collect();
        }

        return $approverQuery->get();
    }
}
