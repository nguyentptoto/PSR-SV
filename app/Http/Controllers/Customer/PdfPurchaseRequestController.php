<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\PdfPurchaseRequest;
use App\Models\PurchaseRequest;
use App\Models\ApprovalHistory;
use App\Models\User;
use App\Models\Group;
use App\Models\ApprovalRank;
use App\Models\ExecutingDepartment;
use App\Http\Controllers\Customer\PdfApprovalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use setasign\Fpdi\Tcpdf\Fpdi;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class PdfPurchaseRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = PdfPurchaseRequest::where('requester_id', Auth::id());

        // Lọc theo pia_code
        if ($request->filled('pia_code')) {
            $query->where('pia_code', 'like', '%' . $request->input('pia_code') . '%');
        }

        // Lọc theo status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Lọc theo người yêu cầu (đã được lọc mặc định là Auth::id())
        // Nếu muốn thêm lọc theo người yêu cầu khác (cho admin), bạn có thể thêm logic ở đây.

        $pdfPurchaseRequests = $query->latest()->paginate(15);

        return view('users.pdf_requests.index', compact('pdfPurchaseRequests'));
    }
   public function create()
    {
        $user = Auth::user();
        $user->load('sections', 'mainBranch');
        $executingDepartments = ExecutingDepartment::orderBy('name')->get();

        $defaultSignaturePositions = [
            'page' => 1,
            'pos_x' => 85,
            'pos_y' => 50,
            'width' => 15,
            'height' => 12,
        ];

        return view('users.pdf_requests.create', compact('user', 'executingDepartments', 'defaultSignaturePositions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pia_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('pdf_purchase_requests', 'pia_code'),
                Rule::unique('purchase_requests', 'pia_code'),
            ],
            'pdf_file' => 'required|file|mimes:pdf|max:20480',
            'remarks' => 'nullable|string|max:2000',
            'signature_pos_x' => 'nullable|numeric',
            'signature_pos_y' => 'nullable|numeric',
            'signature_width' => 'nullable|numeric',
            'signature_height' => 'nullable|numeric',
            'signature_page' => 'nullable|integer|min:1',
            'requires_director_approval' => 'sometimes|boolean',

            // Validation cho 1 file đính kèm duy nhất
            'attachment' => 'nullable|file|mimes:pdf,xlsx,xls,doc,docx|max:10240',
        ]);

        DB::beginTransaction();
        try {
            $file = $request->file('pdf_file');
            $piaCode = $validated['pia_code'];
            $extension = $file->getClientOriginalExtension();
            $newFileName = $piaCode . '_' . time() . '.' . $extension;
            $originalPdfPath = $file->storeAs('pr_pdfs/originals', $newFileName, 'public');

            // Xử lý file đính kèm
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentFile = $request->file('attachment');
                $attachmentExtension = $attachmentFile->getClientOriginalExtension();
               // Tạo tên file đính kèm với tiền tố ATT và mã PR
$newAttachmentName = 'ATT_' . $piaCode  . $attachmentExtension;
                $attachmentPath = $attachmentFile->storeAs('pr_pdfs/attachments', $newAttachmentName, 'public');
            }

            $pdfRequest = PdfPurchaseRequest::create([
                'pia_code' => $piaCode,
                'requester_id' => Auth::id(),
                'original_pdf_path' => $originalPdfPath,
                'remarks' => $validated['remarks'] ?? null,
                'status' => 'pending_approval',
                'current_rank_level' => 1,
                'signature_pos_x' => $validated['signature_pos_x'] ?? null,
                'signature_pos_y' => $validated['signature_pos_y'] ?? null,
                'signature_width' => $validated['signature_width'] ?? null,
                'signature_height' => $validated['signature_height'] ?? null,
                'signature_page' => $validated['signature_page'] ?? null,
                'requires_director_approval' => $request->boolean('requires_director_approval'),
                'attachment_path' => $attachmentPath, // Gán đường dẫn file đính kèm
            ]);

            DB::commit();

            return redirect()->route('users.pdf-requests.preview-sign', $pdfRequest->id)->with('success', 'File PDF và tệp đính kèm đã được tải lên thành công. Vui lòng ký để hoàn tất.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error storing PDF request: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Đã xảy ra lỗi khi tải lên file PDF: ' . $e->getMessage())->withInput();
        }
    }

    public function show(PdfPurchaseRequest $pdfPurchaseRequest)
    {
        $user = Auth::user();

        $pdfApprovalController = app(PdfApprovalController::class);
        $canView = $pdfPurchaseRequest->requester_id === $user->id ||
                   $user->can('is-admin') ||
                   $pdfPurchaseRequest->approvalHistories()->where('user_id', $user->id)->exists() ||
                   $pdfApprovalController->userCanApprovePdf($user, $pdfPurchaseRequest);

        abort_if(!$canView, 403, 'Bạn không có quyền xem phiếu PDF này.');

        $pdfPurchaseRequest->load('requester.jobTitle', 'requester.mainBranch', 'requester.sections', 'approvalHistories.user');

        $originalPdfUrl = route('users.pdf-requests.view-file', $pdfPurchaseRequest->id);
        $signedPdfUrl = $pdfPurchaseRequest->signed_pdf_path ? asset('storage/' . $pdfPurchaseRequest->signed_pdf_path) : null;

        return view('users.pdf_requests.show', compact('pdfPurchaseRequest', 'originalPdfUrl', 'signedPdfUrl'));
    }

    public function edit(PdfPurchaseRequest $pdfPurchaseRequest)
    {
        abort_if($pdfPurchaseRequest->requester_id !== Auth::id(), 403);
        abort_if($pdfPurchaseRequest->status !== 'pending_approval' || $pdfPurchaseRequest->current_rank_level > 1, 403, 'Phiếu PDF đã được ký hoặc gửi đi duyệt, không thể cập nhật.');

        $user = Auth::user();
        $user->load('sections', 'mainBranch');
        $executingDepartments = ExecutingDepartment::orderBy('name')->get();

        return view('users.pdf_requests.edit', compact('pdfPurchaseRequest', 'user', 'executingDepartments'));
    }

    public function update(Request $request, PdfPurchaseRequest $pdfPurchaseRequest)
    {
        abort_if($pdfPurchaseRequest->requester_id !== Auth::id(), 403);
        abort_if($pdfPurchaseRequest->status !== 'pending_approval' || $pdfPurchaseRequest->current_rank_level > 1, 403, 'Phiếu PDF đã được ký hoặc gửi đi duyệt, không thể cập nhật.');

        $validated = $request->validate([
            'pia_code' => ['required', 'string', 'max:255', Rule::unique('pdf_purchase_requests', 'pia_code')->ignore($pdfPurchaseRequest->id)],
            'remarks' => 'nullable|string|max:2000',
            'signature_pos_x' => 'nullable|numeric',
            'signature_pos_y' => 'nullable|numeric',
            'signature_width' => 'nullable|numeric',
            'signature_height' => 'nullable|numeric',
            'signature_page' => 'nullable|integer|min:1',
            'requires_director_approval' => 'nullable',
        ]);

        DB::beginTransaction();
        try {
            $updateData = $request->only([
                'pia_code', 'remarks', 'signature_pos_x', 'signature_pos_y',
                'signature_width', 'signature_height', 'signature_page',
            ]);
            $updateData['requires_director_approval'] = (bool)$request->input('requires_director_approval');

            $pdfPurchaseRequest->update($updateData);

            ApprovalHistory::create([
                'purchase_request_id' => null,
                'pdf_purchase_request_id' => $pdfPurchaseRequest->id,
                'user_id' => Auth::id(),
                'rank_at_approval' => 'Requester',
                'action' => 'updated',
                'signature_image_path' => Auth::user()->signature_image_path ?? 'no-signature.png',
                'comment' => 'Cập nhật thông tin phiếu PDF.',
            ]);

            DB::commit();
            return redirect()->route('users.pdf-requests.index')->with('success', 'Phiếu PDF đã được cập nhật thành công!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error updating PDF request {$pdfPurchaseRequest->id}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Đã xảy ra lỗi khi cập nhật phiếu PDF: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(PdfPurchaseRequest $pdfPurchaseRequest)
    {
        abort_if($pdfPurchaseRequest->requester_id !== Auth::id(), 403);
        abort_if($pdfPurchaseRequest->status !== 'pending_approval' || $pdfPurchaseRequest->current_rank_level > 1, 403, 'Phiếu PDF đã được ký hoặc gửi đi duyệt, không thể xóa.');

        DB::beginTransaction();
        try {
            if ($pdfPurchaseRequest->original_pdf_path) {
                Storage::disk('public')->delete($pdfPurchaseRequest->original_pdf_path);
            }
            if ($pdfPurchaseRequest->signed_pdf_path) {
                Storage::disk('public')->delete($pdfPurchaseRequest->signed_pdf_path);
            }

            $pdfPurchaseRequest->delete();
            DB::commit();
            return redirect()->route('users.pdf-requests.index')->with('success', 'Phiếu PDF đã được xóa thành công!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error deleting PDF request {$pdfPurchaseRequest->id}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Đã xảy ra lỗi khi xóa phiếu PDF: ' . $e->getMessage());
        }
    }

    public function previewSign(PdfPurchaseRequest $pdfPurchaseRequest)
    {
        abort_if($pdfPurchaseRequest->requester_id !== Auth::id(), 403);

        $pdfUrl = route('users.pdf-requests.view-file', $pdfPurchaseRequest->id);
        Log::info('DEBUG: PDF Preview URL generated: ' . $pdfUrl);

        $userSignaturePath = Auth::user()->signature_image_path;

        return view('users.pdf_requests.preview_sign', compact('pdfPurchaseRequest', 'pdfUrl', 'userSignaturePath'));
    }

    public function viewFile(PdfPurchaseRequest $pdfPurchaseRequest)
    {
        $filePath = Storage::disk('public')->path($pdfPurchaseRequest->original_pdf_path);
        Log::info('DEBUG: Attempting to serve PDF. Path from DB: ' . $pdfPurchaseRequest->original_pdf_path);
        Log::info('DEBUG: Full physical path: ' . $filePath);
        Log::info('DEBUG: Does file exist on disk? ' . (file_exists($filePath) ? 'Yes' : 'No'));

        if (!file_exists($filePath)) {
            abort(404, 'File PDF không tồn tại.');
        }

        $headers = [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
        ];

        return response()->file($filePath, $headers);
    }

    public function signAndSubmit(Request $request, PdfPurchaseRequest $pdfPurchaseRequest)
    {
        abort_if($pdfPurchaseRequest->requester_id !== Auth::id(), 403);
        abort_if($pdfPurchaseRequest->status !== 'pending_approval' || $pdfPurchaseRequest->current_rank_level > 1, 403, 'Phiếu đã được xử lý hoặc ký.');

        $userSignaturePath = Auth::user()->signature_image_path;
        if (!$userSignaturePath) {
            return back()->with('error', 'Không tìm thấy ảnh chữ ký của bạn. Vui lòng cập nhật hồ sơ.');
        }
        $signatureImagePath = Storage::disk('public')->path($userSignaturePath);
        if (!file_exists($signatureImagePath)) {
            return back()->with('error', 'Không tìm thấy ảnh chữ ký của bạn. Vui lòng cập nhật hồ sơ.');
        }

        $originalPdfPath = Storage::disk('public')->path($pdfPurchaseRequest->original_pdf_path);
        if (!file_exists($originalPdfPath)) {
            return back()->with('error', 'Không tìm thấy file PDF gốc.');
        }

        $x = $pdfPurchaseRequest->signature_pos_x ?? 85;
        $y = $pdfPurchaseRequest->signature_pos_y ?? 50;
        $width = $pdfPurchaseRequest->signature_width ?? 15;
        $height = $pdfPurchaseRequest->signature_height ?? 12;
        $page = $pdfPurchaseRequest->signature_page ?? 1;

        $firstX = $x;
        $now = now()->format('H:i:s d/m/Y '); // Lấy thời gian hiện tại

        DB::beginTransaction();
        try {
            $pdf = new Fpdi();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetDrawColor(255, 255, 255);
            $pdf->SetLineWidth(0);
            $pdf->SetCellPaddings(0, 0, 0, 0);

            $pageCount = $pdf->setSourceFile($originalPdfPath);

            if ($page > $pageCount) {
                throw new \Exception("Trang ký ({$page}) vượt quá số trang của PDF gốc ({$pageCount}).");
            }

            for ($i = 1; $i <= $pageCount; $i++) {
                $tplId = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tplId);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId);

                if ($i == $page) {
                    $pdf->Image($signatureImagePath, $firstX, $y, $width, $height, '', '', '', false, 300, '', false, false, false);

                    // In thời gian
                    $pdf->SetFont('helvetica', '', 7);
                    $pdf->SetTextColor(0, 0, 0); // Màu đen
                    $textX = $firstX - 3;
                    $textY = $y + $height + 4;
                    $pdf->Text($textX, $textY, $now);
                }
            }


// Tạo tên file mới với mã PR và một chuỗi ngẫu nhiên ngắn gọn
            $signedPdfFileName = 'PR_' . $pdfPurchaseRequest->pia_code .  '.pdf';
            $signedPdfPath = 'pr_pdfs/signed/' . $signedPdfFileName;
            $outputFilePath = Storage::disk('public')->path($signedPdfPath);
            Storage::disk('public')->makeDirectory('pr_pdfs/signed');
            $pdf->Output($outputFilePath, 'F');

            $pdfPurchaseRequest->update([
                'signed_pdf_path' => $signedPdfPath,
                'status' => 'pending_approval',
                'current_rank_level' => 2,
            ]);

            $signaturePositionData = [
                'x' => $firstX,
                'y' => $y,
                'width' => $width,
                'height' => $height,
                'page' => $page,
            ];

            ApprovalHistory::create([
                'purchase_request_id' => null,
                'pdf_purchase_request_id' => $pdfPurchaseRequest->id,
                'user_id' => Auth::id(),
                'rank_at_approval' => 'Requester',
                'action' => 'signed_and_submitted',
                'signature_image_path' => $userSignaturePath ?? 'no-signature.png',
                'comment' => 'Ký và gửi phiếu PDF đi duyệt.',
                'signature_position' => $signaturePositionData,
            ]);

            DB::commit();

            $pdfApprovalController = app(PdfApprovalController::class);
            $nextApprovers = $pdfApprovalController->findNextApproversForPdfPurchaseRequest($pdfPurchaseRequest);

            if ($nextApprovers->isNotEmpty()) {
                 // Sửa đổi: Bỏ comment và sử dụng logic gửi email
            foreach ($nextApprovers as $approver) {
                if ($approver->email) {
                    \Mail::to($approver->email)->queue(new \App\Mail\PurchaseRequestNotification($pdfPurchaseRequest));
                }
            }
                 Log::info('DEBUG: PDF Notification would be dispatched for PR: ' . $pdfPurchaseRequest->id . '. (Email functionality removed)');
            } else {
                Log::info('DEBUG: No next approvers found for PDF PR: ' . $pdfPurchaseRequest->id . '. No notification dispatched.');
            }

            return redirect()->route('users.pdf-requests.index')->with('success', 'Phiếu PDF đã được ký và gửi đi duyệt thành công!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error signing PDF request {$pdfPurchaseRequest->id}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Đã xảy ra lỗi khi ký file PDF: ' . $e->getMessage())->withInput();
        }
    }
}
