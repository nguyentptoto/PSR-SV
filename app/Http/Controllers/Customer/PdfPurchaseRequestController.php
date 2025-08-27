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
use App\Jobs\SendPdfApprovalNotification; // Import job for PDF approval notifications

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

        $pdfPurchaseRequests = $query->latest()->paginate(15);

        // Lấy thông tin người duyệt tiếp theo cho mỗi phiếu và ghi vào log
        $pdfApprovalController = app(PdfApprovalController::class);

        Log::info('--- Bắt đầu log thông tin phiếu và người duyệt tiếp theo ---');
        foreach ($pdfPurchaseRequests as $pr) {
            $approvers = $pdfApprovalController->findNextApproversForPdfPurchaseRequest($pr);
            $approverNames = $approvers->pluck('name')->implode(', ');

            Log::info("Phiếu ID: {$pr->id}, Mã: {$pr->pia_code}, Trạng thái: {$pr->status}, Cấp duyệt: {$pr->current_rank_level}");
            Log::info("  -> Người duyệt tiếp theo: {$approverNames}");
        }
        Log::info('--- Kết thúc log ---');

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
        // 1. Validation mới cho việc upload nhiều file
        $validated = $request->validate([
            'pdf_files' => 'required|array',
            'pdf_files.*' => 'required|file|mimes:pdf|max:20480',
            // VALIDATION ĐÃ ĐƯỢC CẬP NHẬT ĐỂ XỬ LÝ CÁC MẢNG
            'pia_codes' => 'required|array',
            'pia_codes.*' => 'required|string|max:255',
            'remarks_per_file' => 'nullable|array',
            'remarks_per_file.*' => 'nullable|string|max:2000',
            'requires_director_approval_per_file' => 'nullable|array',
            'requires_director_approval_per_file.*' => 'sometimes|boolean',
            'attachments' => 'nullable|array',
            'attachments.*' => 'nullable|file|mimes:pdf,xlsx,xls,doc,docx|max:10240',
            'signature_pos_x' => 'nullable|numeric',
            'signature_pos_y' => 'nullable|numeric',
            'signature_width' => 'nullable|numeric',
            'signature_height' => 'nullable|numeric',
            'signature_page' => 'nullable|integer|min:1',
        ]);

        // KIỂM TRA TRÙNG LẶP MÃ PR TRƯỚC KHI TẠO PHIẾU
        $piaCodesFromForm = $request->input('pia_codes');
        foreach ($piaCodesFromForm as $piaCode) {
            if (PdfPurchaseRequest::where('pia_code', $piaCode)->exists() || PurchaseRequest::where('pia_code', $piaCode)->exists()) {
                return back()->with('error', "Mã phiếu '{$piaCode}' đã tồn tại. Vui lòng đổi tên file hoặc tạo lại.")->withInput();
            }
        }

        DB::beginTransaction();

        try {
            $createdRequests = collect();
            $files = $request->file('pdf_files');
            $piaCodesFromForm = $request->input('pia_codes');
            $remarksFromForm = $request->input('remarks_per_file');
            $requiresApprovalFromForm = $request->input('requires_director_approval_per_file');
            $attachmentsFromForm = $request->file('attachments');

            // Lặp qua từng file PDF được upload, sử dụng index để khớp với dữ liệu từ form
            foreach ($files as $index => $file) {
                $piaCode = $piaCodesFromForm[$index];

                // Xử lý trường hợp mã phiếu trùng lặp
                if (PdfPurchaseRequest::where('pia_code', $piaCode)->exists() || PurchaseRequest::where('pia_code', $piaCode)->exists()) {
                    DB::rollBack();
                    return back()->with('error', "Mã phiếu '{$piaCode}' đã tồn tại. Vui lòng đổi tên file hoặc tạo lại.")->withInput();
                }

                $extension = $file->getClientOriginalExtension();
                $newFileName = $piaCode . '_' . time() . '.' . $extension;
                $originalPdfPath = $file->storeAs('pr_pdfs/originals', $newFileName, 'public');

                // Lấy dữ liệu riêng cho từng phiếu
                $remarks = $remarksFromForm[$index] ?? null;
                $requiresDirectorApproval = isset($requiresApprovalFromForm[$index]) ? true : false;

                // Xử lý file đính kèm riêng cho từng phiếu
                $attachmentPath = null;
                if (isset($attachmentsFromForm[$index])) {
                    $attachmentFile = $attachmentsFromForm[$index];
                    $attachmentExtension = $attachmentFile->getClientOriginalExtension();
                    $newAttachmentName = 'ATT_' . $piaCode . '_' . time() . '.' . $attachmentExtension;
                    $attachmentPath = $attachmentFile->storeAs('pr_pdfs/attachments', $newAttachmentName, 'public');
                }

                $pdfRequest = PdfPurchaseRequest::create([
                    'pia_code' => $piaCode,
                    'requester_id' => Auth::id(),
                    'original_pdf_path' => $originalPdfPath,
                    'remarks' => $remarks,
                    'status' => 'pending_approval',
                    'current_rank_level' => 1,
                    'signature_pos_x' => $validated['signature_pos_x'] ?? null,
                    'signature_pos_y' => $validated['signature_pos_y'] ?? null,
                    'signature_width' => $validated['signature_width'] ?? null,
                    'signature_height' => $validated['signature_height'] ?? null,
                    'signature_page' => $validated['signature_page'] ?? null,
                    'requires_director_approval' => $requiresDirectorApproval,
                    'attachment_path' => $attachmentPath,
                ]);

                $createdRequests->push($pdfRequest);
                event('pdf.request.created', [$pdfRequest, Auth::user()]);
            }

            DB::commit();

            if ($createdRequests->isNotEmpty()) {
                $createdPdfIds = $createdRequests->pluck('id')->toArray();
                $successMessage = 'Đã tạo thành công ' . $createdRequests->count() . ' phiếu đề nghị. Vui lòng xem trước và ký các phiếu.';
                return redirect()->route('users.pdf-requests.preview-sign-batch', ['ids' => $createdPdfIds])
                                 ->with('success', $successMessage);
            } else {
                return redirect()->route('users.pdf-requests.index')->with('success', 'Không có phiếu nào được tạo hoặc có lỗi xảy ra.');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error storing PDF requests: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Đã xảy ra lỗi khi tải lên file PDF: ' . $e->getMessage())->withInput();
        }
    }
    public function previewSignBatch(Request $request)
    {
        $ids = $request->input('ids');
        if (empty($ids) || !is_array($ids)) {
            return redirect()->route('users.pdf-requests.index')->with('error', 'Không có phiếu nào được chọn để xem trước và ký.');
        }

        $pdfPurchaseRequests = PdfPurchaseRequest::whereIn('id', $ids)
                                                ->where('requester_id', Auth::id())
                                                ->get();

        if ($pdfPurchaseRequests->isEmpty()) {
            return redirect()->route('users.pdf-requests.index')->with('error', 'Không tìm thấy phiếu nào hoặc bạn không có quyền truy cập.');
        }

        $userSignaturePath = Auth::user()->signature_image_path;

        // Trả về view với một Collection
        return view('users.pdf_requests.preview_sign', compact('pdfPurchaseRequests', 'userSignaturePath'));
    }

     public function signAndSubmitBatch(Request $request)
    {
        $pdfIds = $request->input('pdf_ids');

        if (empty($pdfIds) || !is_array($pdfIds)) {
            return back()->with('error', 'Không có phiếu nào được chọn để ký.');
        }

        $userSignaturePath = Auth::user()->signature_image_path;
        if (!$userSignaturePath) {
            return back()->with('error', 'Không tìm thấy ảnh chữ ký của bạn. Vui lòng cập nhật hồ sơ.');
        }
        $signatureImagePath = Storage::disk('public')->path($userSignaturePath);
        if (!file_exists($signatureImagePath)) {
            return back()->with('error', 'Không tìm thấy ảnh chữ ký của bạn. Vui lòng cập nhật hồ sơ.');
        }

        $signedCount = 0;
        $errors = [];
        $signedPiaCodes = [];

        foreach ($pdfIds as $pdfId) {
            $pdfPurchaseRequest = PdfPurchaseRequest::find($pdfId);

            if (!$pdfPurchaseRequest || $pdfPurchaseRequest->requester_id !== Auth::id() || $pdfPurchaseRequest->status !== 'pending_approval' || $pdfPurchaseRequest->current_rank_level > 1) {
                $errors[] = "Không thể ký phiếu {$pdfPurchaseRequest->pia_code} vì lý do không hợp lệ.";
                continue;
            }

            $originalPdfPath = Storage::disk('public')->path($pdfPurchaseRequest->original_pdf_path);
            if (!file_exists($originalPdfPath)) {
                $errors[] = "Không tìm thấy file PDF gốc cho phiếu {$pdfPurchaseRequest->pia_code}.";
                continue;
            }

            $x = $pdfPurchaseRequest->signature_pos_x ?? 85;
            $y = $pdfPurchaseRequest->signature_pos_y ?? 50;
            $width = $pdfPurchaseRequest->signature_width ?? 15;
            $height = $pdfPurchaseRequest->signature_height ?? 12;
            $page = $pdfPurchaseRequest->signature_page ?? 1;
        $firstX = $x;

            $now = now()->format('H:i:s d/m/Y ');

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
                    $textX = $firstX - 2; // Điều chỉnh vị trí chữ ký
                    $textY = $y + $height + 4;
                    $pdf->Text($textX, $textY, $now);
                }
                }

                $signedPdfFileName = 'PR_' . $pdfPurchaseRequest->pia_code . '.pdf';
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
                    'x' => $x,
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
                $signedCount++;
                $signedPiaCodes[] = $pdfPurchaseRequest->pia_code;

                $pdfApprovalController = app(PdfApprovalController::class);
                $nextApprovers = $pdfApprovalController->findNextApproversForPdfPurchaseRequest($pdfPurchaseRequest);

                if ($nextApprovers->isNotEmpty()) {
                    foreach ($nextApprovers as $approver) {
                        SendPdfApprovalNotification::dispatch($pdfPurchaseRequest, $approver);
                    }
                    Log::info('DEBUG: PDF Notification jobs have been dispatched for PR: ' . $pdfPurchaseRequest->id);
                } else {
                    Log::info('DEBUG: PDF Notification would be dispatched for PR: ' . $pdfPurchaseRequest->id . '. (Email functionality removed)');
                }
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error signing PDF request {$pdfPurchaseRequest->pia_code}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                $errors[] = "Lỗi khi ký phiếu {$pdfPurchaseRequest->pia_code}: " . $e->getMessage();
            }
        }

        if ($signedCount > 0) {
            $successMessage = "Đã ký và gửi duyệt thành công {$signedCount} phiếu: " . implode(', ', $signedPiaCodes) . ".";
            if (!empty($errors)) {
                $successMessage .= " Có một số lỗi xảy ra với các phiếu khác: " . implode('; ', $errors);
                return redirect()->route('users.pdf-requests.index')->with('warning', $successMessage);
            }
            return redirect()->route('users.pdf-requests.index')->with('success', $successMessage);
        } else {
            return back()->with('error', 'Không có phiếu nào được ký thành công. ' . implode('; ', $errors));
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

        // KIỂM TRA MỚI: Chỉ chặn khi phiếu đang được duyệt (chưa bị từ chối)
        $hasBeenApproved = $pdfPurchaseRequest->approvalHistories()->where('action', 'approved')->exists();
        if ($pdfPurchaseRequest->status === 'pending_approval' && $hasBeenApproved) {
            abort(403, 'Phiếu đã có người duyệt, không thể chỉnh sửa.');
        }

        // Cho phép sửa nếu phiếu ở trạng thái 'rejected'
        if ($pdfPurchaseRequest->status !== 'pending_approval' && $pdfPurchaseRequest->status !== 'rejected') {
            abort(403, 'Chỉ có thể sửa phiếu đang chờ duyệt hoặc đã bị từ chối.');
        }

        $user = Auth::user();
        $user->load('sections', 'mainBranch');
        $executingDepartments = ExecutingDepartment::orderBy('name')->get();

        return view('users.pdf_requests.edit', compact('pdfPurchaseRequest', 'user', 'executingDepartments'));
    }

    public function update(Request $request, PdfPurchaseRequest $pdfPurchaseRequest)
    {
        // Áp dụng logic kiểm tra quyền tương tự hàm edit
        abort_if($pdfPurchaseRequest->requester_id !== Auth::id(), 403);
        $hasBeenApproved = $pdfPurchaseRequest->approvalHistories()->where('action', 'approved')->exists();
        if ($pdfPurchaseRequest->status === 'pending_approval' && $hasBeenApproved) {
            abort(403, 'Phiếu đã có người duyệt, không thể chỉnh sửa.');
        }
        if ($pdfPurchaseRequest->status !== 'pending_approval' && $pdfPurchaseRequest->status !== 'rejected') {
            abort(403, 'Chỉ có thể sửa phiếu đang chờ duyệt hoặc đã bị từ chối.');
        }

        $validated = $request->validate([
            'pia_code' => ['required', 'string', 'max:255', Rule::unique('pdf_purchase_requests', 'pia_code')->ignore($pdfPurchaseRequest->id)],
            'remarks' => 'nullable|string|max:2000',
            'signature_pos_x' => 'nullable|numeric',
            'signature_pos_y' => 'nullable|numeric',
            'signature_width' => 'nullable|numeric',
            'signature_height' => 'nullable|numeric',
            'signature_page' => 'nullable|integer|min:1',
            'requires_director_approval' => 'sometimes|boolean',
            'attachment' => 'nullable|file|mimes:pdf,xlsx,xls,doc,docx|max:10240',
            'original_pdf' => 'nullable|file|mimes:pdf|max:20480', // VALIDATION MỚI CHO FILE PDF GỐC
        ]);

        DB::beginTransaction();
        try {
            // Kiểm tra xem phiếu có bị từ chối không, HOẶC nếu có file mới được tải lên
            $isResubmission = $pdfPurchaseRequest->status === 'rejected' || $request->hasFile('original_pdf');

            // Lấy dữ liệu cần cập nhật
            $updateData = $request->only(['pia_code', 'remarks', 'signature_pos_x', 'signature_pos_y', 'signature_width', 'signature_height', 'signature_page']);
            $updateData['requires_director_approval'] = $request->boolean('requires_director_approval');

            // Xử lý file PDF gốc mới (nếu có)
            if ($request->hasFile('original_pdf')) {
                // Xóa file PDF gốc cũ
                if ($pdfPurchaseRequest->original_pdf_path) {
                    Storage::disk('public')->delete($pdfPurchaseRequest->original_pdf_path);
                }
                // Xóa file PDF đã ký cũ (nếu có)
                if ($pdfPurchaseRequest->signed_pdf_path) {
                    Storage::disk('public')->delete($pdfPurchaseRequest->signed_pdf_path);
                }

                $originalPdfFile = $request->file('original_pdf');
                $extension = $originalPdfFile->getClientOriginalExtension();
                $newFileName = $validated['pia_code'] . '_' . time() . '.' . $extension;
                $updateData['original_pdf_path'] = $originalPdfFile->storeAs('pr_pdfs/originals', $newFileName, 'public');
                $updateData['signed_pdf_path'] = null; // Reset đường dẫn file đã ký
            }

            // Xử lý file đính kèm mới
            if ($request->hasFile('attachment')) {
                if ($pdfPurchaseRequest->attachment_path) {
                    Storage::disk('public')->delete($pdfPurchaseRequest->attachment_path);
                }
                $attachmentFile = $request->file('attachment');
                $attachmentExtension = $attachmentFile->getClientOriginalExtension();
                $newAttachmentName = 'ATT_' . $validated['pia_code'] . '.' . $attachmentExtension;
                $updateData['attachment_path'] = $attachmentFile->storeAs('pr_pdfs/attachments', $newAttachmentName, 'public');
            } elseif ($request->has('remove_attachment')) {
                if ($pdfPurchaseRequest->attachment_path) {
                    Storage::disk('public')->delete($pdfPurchaseRequest->attachment_path);
                    $updateData['attachment_path'] = null;
                }
            }

            // Nếu là gửi lại phiếu bị từ chối HOẶC có file PDF gốc mới được tải lên, RESET quy trình
            if ($isResubmission) {
                $updateData['status'] = 'pending_approval';
                $updateData['current_rank_level'] = 1; // Quay về cấp của người tạo, chờ ký
                // THÊM MỚI: RESET VỊ TRÍ CHỮ KÝ VỀ MẶC ĐỊNH KHI GỬI LẠI
                $updateData['signature_pos_x'] = 85;
                $updateData['signature_pos_y'] = 50;
                $updateData['signature_width'] = 15;
                $updateData['signature_height'] = 12;
                $updateData['signature_page'] = 1;
                // Nếu không có file PDF gốc mới, nhưng đây là resubmission, thì xóa file ký cũ
                if (!$request->hasFile('original_pdf') && $pdfPurchaseRequest->signed_pdf_path) {
                    Storage::disk('public')->delete($pdfPurchaseRequest->signed_pdf_path);
                    $updateData['signed_pdf_path'] = null;
                }
            }

            $pdfPurchaseRequest->update($updateData);

            // Ghi lịch sử cho hành động cập nhật hoặc gửi lại
            ApprovalHistory::create([
                'pdf_purchase_request_id' => $pdfPurchaseRequest->id,
                'user_id' => Auth::id(),
                'rank_at_approval' => 'Requester',
                'action' => $isResubmission ? 'resubmitted_after_rejection' : 'updated',
                'comment' => $isResubmission ? 'Cập nhật và gửi lại phiếu sau khi bị từ chối.' : 'Cập nhật thông tin phiếu PDF.',
            ]);

            DB::commit();

            // Nếu là resubmission hoặc có file gốc mới, chuyển đến trang ký. Nếu không, về trang danh sách.
            if ($isResubmission) {
                return redirect()->route('users.pdf-requests.preview-sign', $pdfPurchaseRequest->id)
                    ->with('success', 'Phiếu đã được cập nhật. Vui lòng ký lại để bắt đầu quy trình duyệt mới.');
            }

            return redirect()->route('users.pdf-requests.index')->with('success', 'Phiếu PDF đã được cập nhật thành công!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error updating PDF request {$pdfPurchaseRequest->id}: " . $e->getMessage());
            return back()->with('error', 'Đã xảy ra lỗi khi cập nhật phiếu PDF: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(PdfPurchaseRequest $pdfPurchaseRequest)
    {
        // 1. Chỉ cho phép người tạo phiếu được quyền xóa
        abort_if($pdfPurchaseRequest->requester_id !== Auth::id(), 403, 'Bạn không có quyền xóa phiếu PDF này.');

        // 2. Kiểm tra xem phiếu đã có người duyệt hay chưa
        $hasBeenApproved = $pdfPurchaseRequest->approvalHistories()->where('action', 'approved')->exists();

        // 3. Áp dụng quy tắc xóa:
        // KHÔNG ĐƯỢC XÓA nếu: phiếu đã được duyệt (và không ở trạng thái bị từ chối) HOẶC phiếu đã hoàn thành.
        // ĐƯỢC XÓA nếu: phiếu chưa có ai duyệt HOẶC phiếu ở trạng thái bị từ chối.
        if (($hasBeenApproved && $pdfPurchaseRequest->status !== 'rejected') || $pdfPurchaseRequest->status === 'completed') {
            return back()->with('error', 'Không thể xóa phiếu đã có người duyệt hoặc đã hoàn thành.');
        }

        DB::beginTransaction();
        try {
            // 4. Xóa các file vật lý khỏi storage
            // Xóa file PDF gốc
            if ($pdfPurchaseRequest->original_pdf_path && Storage::disk('public')->exists($pdfPurchaseRequest->original_pdf_path)) {
                Storage::disk('public')->delete($pdfPurchaseRequest->original_pdf_path);
            }

            // Xóa file PDF đã ký (nếu có)
            if ($pdfPurchaseRequest->signed_pdf_path && Storage::disk('public')->exists($pdfPurchaseRequest->signed_pdf_path)) {
                Storage::disk('public')->delete($pdfPurchaseRequest->signed_pdf_path);
            }

            // Xóa file đính kèm (nếu có)
            if ($pdfPurchaseRequest->attachment_path && Storage::disk('public')->exists($pdfPurchaseRequest->attachment_path)) {
                Storage::disk('public')->delete($pdfPurchaseRequest->attachment_path);
            }
            $pdfPurchaseRequest->approvalHistories()->delete();
            $pdfPurchaseRequest->delete();
            DB::commit();
            return redirect()->route('users.pdf-requests.index')->with('success', 'Phiếu PDF và các file liên quan đã được xóa thành công!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error deleting PDF request {$pdfPurchaseRequest->id}: " . $e->getMessage());
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
                    $textX = $firstX - 2; // Điều chỉnh vị trí chữ ký
                    $textY = $y + $height + 4;
                    $pdf->Text($textX, $textY, $now);
                }
            }


            // Tạo tên file mới với mã PR và một chuỗi ngẫu nhiên ngắn gọn
            $signedPdfFileName = 'PR_' . $pdfPurchaseRequest->pia_code . '.pdf';
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
                foreach ($nextApprovers as $approver) {
                    // Đúng: Dispatch job đúng cho quy trình PDF
                    SendPdfApprovalNotification::dispatch($pdfPurchaseRequest, $approver);
                }
                Log::info('DEBUG: PDF Notification jobs have been dispatched for PR: ' . $pdfPurchaseRequest->id);
            } else {
                Log::info('DEBUG: PDF Notification would be dispatched for PR: ' . $pdfPurchaseRequest->id . '. (Email functionality removed)');
            }

            return redirect()->route('users.pdf-requests.index')->with('success', 'Phiếu PDF đã được ký và gửi đi duyệt thành công!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error signing PDF request {$pdfPurchaseRequest->id}: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Đã xảy ra lỗi khi ký file PDF: ' . $e->getMessage())->withInput();
        }
    }
}
