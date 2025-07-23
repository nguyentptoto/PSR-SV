<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\ApprovalHistory;
use App\Models\ExecutingDepartment;
use App\Models\User;
use App\Models\Group;
use App\Models\ApprovalRank;
use App\Jobs\SendApprovalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PurchaseRequestExport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Imports\PurchaseRequestsImport;
use ZipArchive;


class PurchaseRequestController extends Controller
{
    public function index()
    {
        $purchaseRequests = PurchaseRequest::where('requester_id', Auth::id())
            ->with(['branch', 'executingDepartment'])
            ->latest()
            ->paginate(15);
        return view('users.purchase_requests.index', compact('purchaseRequests'));
    }

    public function create()
    {
        $user = Auth::user();
        $user->load('sections', 'mainBranch');
        $executingDepartments = ExecutingDepartment::orderBy('name')->get();
        return view('users.purchase_requests.create', compact('user', 'executingDepartments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pia_code' => 'required|string|max:255|unique:purchase_requests,pia_code',
            'section_id' => 'required|exists:sections,id',
            'executing_department_id' => 'required|exists:executing_departments,id',
            'branch_id' => 'required|exists:branches,id',
            'sap_release_date' => 'nullable|date',
            'requested_delivery_date' => 'required|date',
            'currency' => 'required|string|max:10',
            'requires_director_approval' => 'nullable|boolean',
            'priority' => 'nullable|string|in:urgent,normal,quotation_only',
            'remarks' => 'nullable|string|max:2000',
            'attachment_file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,png,zip|max:10240',
            'sap_request_date' => 'nullable|date',
            'po_number' => 'nullable|string|max:255',
            'po_date' => 'nullable|date',
            'sap_created_by' => 'nullable|string|max:255',

            'items' => 'required|array|min:1',
            'items.*.item_code' => 'required|string|max:255',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.old_item_code' => 'nullable|string|max:255',
            'items.*.order_quantity' => 'required|numeric|min:0.001',
            'items.*.estimated_price' => 'required|numeric|min:0',
            'items.*.inventory_quantity' => 'nullable|numeric|min:0',
            'items.*.order_unit' => 'nullable|string|max:20',
            'items.*.inventory_unit' => 'nullable|string|max:20',
            'items.*.r3_price' => 'nullable|numeric|min:0',
            'items.*.using_dept_code' => 'nullable|string|max:255',
            'items.*.plant_system' => 'nullable|string|max:255',
            'items.*.purchase_group' => 'nullable|string|max:20',
            'items.*.legacy_item_code' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $prData = $request->except(['_token', 'items', 'attachment_file']);

            $totalAmount = 0;
            $totalOrderQuantity = 0;
            $totalInventoryQuantity = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += $item['order_quantity'] * $item['estimated_price'];
                $totalOrderQuantity += $item['order_quantity'];
                $totalInventoryQuantity += ($item['inventory_quantity'] ?? 0);
            }
            $prData['total_amount'] = $totalAmount;
            $prData['total_order_quantity'] = $totalOrderQuantity;
            $prData['total_inventory_quantity'] = $totalInventoryQuantity;

            if ($request->hasFile('attachment_file')) {
                $file = $request->file('attachment_file');
                $piaCode = $validated['pia_code'];
                $extension = $file->getClientOriginalExtension();
                $newFileName = $piaCode . '.' . $extension;
                $prData['attachment_path'] = $file->storeAs('pr_attachments', $newFileName, 'public');
            }

            $prData['requester_id'] = Auth::id();
            $prData['status'] = 'pending_approval';
            $prData['current_rank_level'] = 2;
            $prData['requires_director_approval'] = $request->boolean('requires_director_approval');

            $prData['sap_request_date'] = $validated['sap_request_date'] ?? null;
            $prData['po_number'] = $validated['po_number'] ?? null;
            $prData['po_date'] = $validated['po_date'] ?? null;
            $prData['sap_created_by'] = $validated['sap_created_by'] ?? null;


            $purchaseRequest = PurchaseRequest::create($prData);

            foreach ($validated['items'] as $itemData) {
                $purchaseRequest->items()->create([
                    'item_code' => $itemData['item_code'],
                    'item_name' => $itemData['item_name'],
                    'old_item_code' => $itemData['old_item_code'] ?? null,
                    'order_quantity' => $itemData['order_quantity'],
                    'order_unit' => $itemData['order_unit'] ?? 'N/A',
                    'inventory_quantity' => $itemData['inventory_quantity'] ?? 0,
                    'inventory_unit' => $itemData['inventory_unit'] ?? 'N/A',
                    'r3_price' => $itemData['r3_price'] ?? 0,
                    'estimated_price' => $itemData['estimated_price'],
                    'subtotal' => $itemData['order_quantity'] * $itemData['estimated_price'],
                    'using_dept_code' => $itemData['using_dept_code'] ?? null,
                    'plant_system' => $itemData['plant_system'] ?? null,
                    'purchase_group' => $itemData['purchase_group'] ?? null,
                    'legacy_item_code' => $itemData['legacy_item_code'] ?? null,
                ]);
            }

            ApprovalHistory::create([
                'purchase_request_id' => $purchaseRequest->id,
                'user_id' => Auth::id(),
                'rank_at_approval' => 'Requester',
                'action' => 'created',
                'signature_image_path' => Auth::user()->signature_image_path ?? 'no-signature.png',
                'comment' => 'Tạo phiếu đề nghị mới',
            ]);

            DB::commit();

            $nextApprovers = $this->findNextApprovers($purchaseRequest);
            if ($nextApprovers->isNotEmpty()) {
                SendApprovalNotification::dispatch($purchaseRequest, $nextApprovers);
            }

            return redirect()->route('users.purchase-requests.index')->with('success', 'Tạo phiếu đề nghị thành công!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error creating purchase request: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Đã xảy ra lỗi: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load('requester.jobTitle', 'branch', 'section', 'executingDepartment', 'items', 'approvalHistories.user');
        $from = $request->query('from', 'my-requests');
        return view('users.purchase_requests.show', compact('purchaseRequest', 'from'));
    }

    public function edit(PurchaseRequest $purchaseRequest)
    {
        abort_if($purchaseRequest->requester_id !== Auth::id() && !Auth::user()->can('is-admin'), 403);
        abort_if($purchaseRequest->status !== 'pending_approval', 403, 'Phiếu đã được duyệt, không thể chỉnh sửa.');

        $user = Auth::user();
        $user->load('sections');
        $executingDepartments = ExecutingDepartment::orderBy('name')->get();
        $purchaseRequest->load('items', 'branch');
        return view('users.purchase_requests.edit', compact('purchaseRequest', 'user', 'executingDepartments'));
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        abort_if($purchaseRequest->requester_id !== Auth::id() && !Auth::user()->can('is-admin'), 403);
        abort_if($purchaseRequest->status !== 'pending_approval', 403, 'Phiếu đã được duyệt, không thể chỉnh sửa.');

        $validated = $request->validate([
            'pia_code' => ['required', 'string', 'max:255', Rule::unique('purchase_requests')->ignore($purchaseRequest->id)],
            'section_id' => 'required|exists:sections,id',
            'executing_department_id' => 'required|exists:executing_departments,id',
            'branch_id' => 'required|exists:branches,id',
            'requested_delivery_date' => 'required|date',
            'total_amount' => 'required|numeric|min:0',
            'total_order_quantity' => 'required|numeric|min:0',
            'total_inventory_quantity' => 'required|numeric|min:0',
            'priority' => 'nullable|string|in:urgent,normal,quotation_only',
            'remarks' => 'nullable|string|max:2000',
            'requires_director_approval' => 'nullable|boolean',
            'attachment_file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,png,zip|max:10240',
            'sap_request_date' => 'nullable|date',
            'po_number' => 'nullable|string|max:255',
            'po_date' => 'nullable|date',
            'sap_created_by' => 'nullable|string|max:255',

            'items' => 'required|array|min:1',
            'items.*.item_code' => 'required|string|max:255',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.old_item_code' => 'nullable|string|max:255',
            'items.*.order_quantity' => 'required|numeric|min:0.001',
            'items.*.estimated_price' => 'required|numeric|min:0',
            'items.*.inventory_quantity' => 'nullable|numeric|min:0',
            'items.*.order_unit' => 'nullable|string|max:20',
            'items.*.inventory_unit' => 'nullable|string|max:20',
            'items.*.r3_price' => 'nullable|numeric|min:0',
            'items.*.using_dept_code' => 'nullable|string|max:255',
            'items.*.plant_system' => 'nullable|string|max:255',
            'items.*.purchase_group' => 'nullable|string|max:20',
            'items.*.legacy_item_code' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $updateData = $request->except(['_token', '_method', 'items', 'attachment_file']);
            $updateData['requires_director_approval'] = $request->boolean('requires_director_approval');

            if ($request->hasFile('attachment_file')) {
                if ($purchaseRequest->attachment_path) {
                    Storage::disk('public')->delete($purchaseRequest->attachment_path);
                }
                $file = $request->file('attachment_file');
                $piaCode = $validated['pia_code'];
                $extension = $file->getClientOriginalExtension();
                $newFileName = $piaCode . '.' . $extension;
                $updateData['attachment_path'] = $file->storeAs('pr_attachments', $newFileName, 'public');
            }

            $updateData['sap_request_date'] = $validated['sap_request_date'] ?? null;
            $updateData['po_number'] = $validated['po_number'] ?? null;
            $updateData['po_date'] = $validated['po_date'] ?? null;
            $updateData['sap_created_by'] = $validated['sap_created_by'] ?? null;

            $purchaseRequest->update($updateData);

            $purchaseRequest->items()->delete();

            foreach ($validated['items'] as $itemData) {
                $purchaseRequest->items()->create([
                    'item_code' => $itemData['item_code'],
                    'item_name' => $itemData['item_name'],
                    'old_item_code' => $itemData['old_item_code'] ?? null,
                    'order_quantity' => $itemData['order_quantity'],
                    'order_unit' => $itemData['order_unit'] ?? 'N/A',
                    'inventory_quantity' => $itemData['inventory_quantity'] ?? 0,
                    'inventory_unit' => $itemData['inventory_unit'] ?? 'N/A',
                    'r3_price' => $itemData['r3_price'] ?? 0,
                    'estimated_price' => $itemData['estimated_price'],
                    'subtotal' => $itemData['order_quantity'] * $itemData['estimated_price'],
                    'using_dept_code' => $itemData['using_dept_code'] ?? null,
                    'plant_system' => $itemData['plant_system'] ?? null,
                    'purchase_group' => $itemData['purchase_group'] ?? null,
                    'legacy_item_code' => $itemData['legacy_item_code'] ?? null,
                ]);
            }

            ApprovalHistory::create([
                'purchase_request_id' => $purchaseRequest->id,
                'user_id' => Auth::id(),
                'rank_at_approval' => 'Editor',
                'action' => 'updated',
                'signature_image_path' => Auth::user()->signature_image_path ?? 'no-signature.png',
                'comment' => 'Cập nhật thông tin phiếu.',
            ]);

            DB::commit();
            return redirect()->route('users.purchase-requests.index')->with('success', 'Cập nhật phiếu đề nghị thành công!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error updating purchase request: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Đã xảy ra lỗi khi cập nhật: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        abort_if($purchaseRequest->requester_id !== Auth::id() && !Auth::user()->can('is-admin'), 403);
        abort_if($purchaseRequest->status !== 'pending_approval', 403, 'Phiếu đã được duyệt, không thể xóa.');

        if ($purchaseRequest->attachment_path) {
            Storage::disk('public')->delete($purchaseRequest->attachment_path);
        }
        $purchaseRequest->delete();
        return redirect()->route('users.purchase-requests.index')->with('success', 'Xóa phiếu đề nghị thành công.');
    }

    public function exportExcel(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load('requester', 'branch', 'executingDepartment', 'items', 'approvalHistories.user');
        $fileName = 'PR_' . $purchaseRequest->pia_code . '.xlsx';
        return Excel::download(new PurchaseRequestExport($purchaseRequest), $fileName);
    }

    public function exportPdf(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load('requester', 'branch', 'executingDepartment', 'items', 'approvalHistories.user');
        $fileName = 'PR-' . $purchaseRequest->pia_code . '.pdf';
        return Excel::download(new PurchaseRequestExport($purchaseRequest), $fileName, ExcelWriter::MPDF);
    }

    private function findNextApprovers(PurchaseRequest $pr): Collection
    {
        if (in_array($pr->status, ['completed', 'rejected'])) {
            return collect();
        }

        $nextRankLevel = $pr->current_rank_level;
        $branchId = $pr->branch_id;
        $sectionId = $pr->section_id;
        $isRequestingStage = $pr->status === 'pending_approval';

        $groupName = $isRequestingStage ? 'Phòng Đề Nghị' : 'Phòng Mua';
        $targetGroupId = Group::where('name', $groupName)->value('id');

        if (!$targetGroupId)
            return collect();

        $approverQuery = User::query()
            ->whereHas('assignments', function ($q) use ($nextRankLevel, $branchId, $targetGroupId) {
                $q->whereHas('approvalRank', fn($r) => $r->where('rank_level', $nextRankLevel))
                    ->where('branch_id', $branchId)
                    ->where('group_id', $targetGroupId);
            });

        if ($isRequestingStage && $nextRankLevel < 4) {
            $approverQuery->whereHas('sections', fn($q) => $q->where('sections.id', $sectionId));
        }

        if ($nextRankLevel == 4 && !$pr->requires_director_approval && $isRequestingStage) {
            return collect();
        }

        return $approverQuery->get();
    }

     public function importPreview(Request $request)
    {
        // GHI CÁC THÔNG TIN REQUEST VÀO LOG ĐỂ DEBUG
        Log::info('--- Bắt đầu Debug ImportPreview Request ---');
        Log::info('Toàn bộ dữ liệu request:', $request->all());
        Log::info('Thông tin files được upload:', $request->files->all());

        if ($request->hasFile('excel_file')) {
            Log::info('Excel File Name:', [$request->file('excel_file')->getClientOriginalName()]);
            Log::info('Excel File MimeType:', [$request->file('excel_file')->getMimeType()]);
            Log::info('Excel File Size (bytes):', [$request->file('excel_file')->getSize()]);
        }
        if ($request->hasFile('attachment_zip_file')) {
            Log::info('ZIP File Name:', [$request->file('attachment_zip_file')->getClientOriginalName()]);
            Log::info('ZIP File MimeType:', [$request->file('attachment_zip_file')->getMimeType()]);
            Log::info('ZIP File Size (bytes):', [$request->file('attachment_zip_file')->getSize()]);
        }
        Log::info('--- Kết thúc Debug ImportPreview Request ---');


        $request->validate([
            'excel_file' => 'required|file|mimes:xls,xlsx|max:10240',
            'attachment_zip_file' => 'nullable|file|mimes:zip|max:51200', // Validate file ZIP (e.g., 50MB)
        ]);

        $import = new PurchaseRequestsImport();
        $tempDirForAttachments = null; // Khởi tạo biến lưu thư mục tạm

        try {
            Log::info('Before Excel import.'); // Debug: Trước khi import Excel
            // Bước 1: Import dữ liệu từ Excel
            Excel::import($import, $request->file('excel_file'));
            Log::info('After Excel import. Import errors: ', $import->getErrors()); // Debug: Sau khi import Excel
            Log::info('After Excel import. Imported data count: ', [count($import->getImportedData())]);


            $importErrors = $import->getErrors();
            $importedData = $import->getImportedData();

            if (empty($importedData)) {
                $errors = $importErrors;
                if (empty($errors)) {
                    $errors[] = 'Không tìm thấy dữ liệu phiếu hợp lệ nào trong file Excel.';
                }
                return response()->json(['success' => false, 'message' => 'Không tìm thấy dữ liệu phiếu hợp lệ nào trong file Excel.', 'errors' => $errors], 400);
            }

            // Bước 2: Xử lý file ZIP nếu có
            $attachmentMap = []; // Map PIA_CODE => temporary_file_path
            $tempDirForAttachments = null; // Đường dẫn đến thư mục tạm giải nén

            Log::info('Checking for attachment_zip_file presence...'); // Debug: Kiểm tra sự hiện diện của ZIP

            if ($request->hasFile('attachment_zip_file')) {
                Log::info('attachment_zip_file is present. Starting ZIP processing.'); // Debug: ZIP có mặt, bắt đầu xử lý
                $zipFile = $request->file('attachment_zip_file');
                $tempDirForAttachments = 'pr_temp_attachments/' . Str::uuid()->toString();
                Storage::disk('local')->makeDirectory($tempDirForAttachments); // Tạo thư mục tạm

                Log::info('Attempting to open ZIP file: ' . $zipFile->getRealPath());

                $zip = new ZipArchive;
                if ($zip->open($zipFile->getRealPath()) === TRUE) {
                    $zip->extractTo(Storage::disk('local')->path($tempDirForAttachments));
                    $zip->close();
                    Log::info('ZIP extracted to: ' . $tempDirForAttachments);

                    // Đọc tất cả các file trong thư mục tạm và tạo map PIA_CODE -> path
                    // Sử dụng allFiles() để duyệt qua các file trong thư mục con nếu có
                    $extractedFiles = Storage::disk('local')->allFiles($tempDirForAttachments);
                    Log::info('Extracted files list from temp dir: ', $extractedFiles);

                    foreach ($extractedFiles as $filePath) {
                        $fileNameWithoutExtension = pathinfo($filePath, PATHINFO_FILENAME);
                        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

                        // Đảm bảo không có khoảng trắng ở đầu/cuối tên file trước khi dùng regex
                        $cleanedFileName = trim($fileNameWithoutExtension);
                        Log::info("Processing extracted file: Filename: '{$cleanedFileName}'. Full path: {$filePath}"); // Debug: Xử lý file trích xuất

                        // Cố gắng trích xuất PIA_CODE từ tên file
                        // Regex: Bắt đầu chuỗi, tùy chọn PR_, sau đó 10+ chữ số (nhóm 1), tùy chọn gạch ngang/gạch dưới theo sau bất kỳ ký tự nào, kết thúc chuỗi.
                        if (preg_match('/^(?:PR_)?(\d{10,})(?:[-_].*)?$/i', $cleanedFileName, $matches)) {
                            $piaCodeFromFile = $matches[1]; // Lấy phần số là PIA_CODE
                            // Kiểm tra trùng lặp: nếu có nhiều file cho cùng một PR_NO, chỉ lấy file đầu tiên hoặc theo quy tắc ưu tiên
                            if (!isset($attachmentMap[$piaCodeFromFile])) {
                                $attachmentMap[$piaCodeFromFile] = $filePath; // Lưu đường dẫn tạm
                                Log::info("Matched file in ZIP: '{$cleanedFileName}' -> Extracted PIA Code: '{$piaCodeFromFile}'. Mapped Path: {$filePath}");
                            } else {
                                Log::warning("Đã tìm thấy nhiều file cho PR_NO {$piaCodeFromFile} trong ZIP. Chỉ lấy file đầu tiên: " . $attachmentMap[$piaCodeFromFile]);
                            }
                        } else {
                            Log::warning("File trong ZIP không khớp định dạng PR_NO: Filename: '{$cleanedFileName}'. Path: {$filePath}. Regex did not match.");
                        }
                    }
                    Log::info('Final attachment map created:', $attachmentMap);

                } else {
                    $zipError = $zip->status ? $zip->getStatusString() : 'Không xác định';
                    Log::error("Không thể mở file ZIP hoặc giải nén lỗi. Lỗi ZIP: {$zipError}. Path: " . $zipFile->getRealPath());
                    throw new \Exception('Không thể mở file ZIP hoặc giải nén lỗi: ' . $zipError);
                }
            } else {
                Log::info('attachment_zip_file is NOT present. Skipping ZIP processing.'); // Debug: ZIP không có mặt
            }


            // Bước 3: Gán đường dẫn file đính kèm tạm vào dữ liệu phiếu
            foreach ($importedData as &$prData) { // Dùng & để thay đổi trực tiếp mảng gốc
                // Đảm bảo pia_code từ Excel cũng được trim() để khớp với piaCodeFromFile đã trim() trong regex
                $excelPiaCode = trim($prData['pia_code']);
                $prData['temporary_attachment_path'] = $attachmentMap[$excelPiaCode] ?? null;
                Log::info("Mapping attachment for PR: '{$excelPiaCode}'. Found in map: " . ($prData['temporary_attachment_path'] ? 'Yes' : 'No') . ". Mapped path: " . ($prData['temporary_attachment_path'] ?? 'N/A'));
            }
            unset($prData); // Xóa tham chiếu sau vòng lặp

            // Bước 4: Lưu dữ liệu đã import (bao gồm đường dẫn file tạm) vào session
            $sessionId = Str::uuid()->toString();
            // Lưu cả đường dẫn thư mục tạm cho việc cleanup sau này
            $sessionData = [
                'purchase_requests' => $importedData,
                'temp_attachments_dir' => $tempDirForAttachments // Lưu đường dẫn thư mục tạm
            ];
            $request->session()->put('imported_purchase_requests_' . $sessionId, $sessionData);


            // Flash messages to session for the redirect
            $messagesToFlash = [];
            if (!empty($importErrors)) {
                $messagesToFlash[] = ['type' => 'warning', 'text' => 'Đã đọc file Excel, nhưng có cảnh báo:<br>' . implode('<br>', $importErrors)];
            } else {
                $messagesToFlash[] = ['type' => 'success', 'text' => 'Đọc file Excel thành công! Vui lòng kiểm tra các phiếu trước khi tạo.'];
            }
            $request->session()->flash('imported_messages', $messagesToFlash);

            return response()->json(['success' => true, 'message' => 'Đọc file Excel thành công! Chuyển đến trang xem trước.', 'redirect_url' => route('users.purchase-requests.import-preview', ['session_id' => $sessionId])]);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errors = [];
            foreach ($failures as $failure) {
                $errors[] = "Dòng " . $failure->row() . ": " . implode(", ", $failure->errors()) . " (Cột: " . ($failure->attribute() ?? 'N/A') . ")";
            }
            return response()->json(['success' => false, 'message' => 'Lỗi validation khi đọc file Excel.', 'errors' => $errors], 422);
        } catch (\Exception $e) {
            // Đảm bảo thư mục tạm được xóa nếu có lỗi trước khi lưu vào session
            if ($tempDirForAttachments && Storage::disk('local')->exists($tempDirForAttachments)) {
                Storage::disk('local')->deleteDirectory($tempDirForAttachments);
            }
            Log::error("Error importing purchase requests excel file for preview: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Đã xảy ra lỗi khi đọc file Excel: ' . $e->getMessage(), 'errors' => []], 500);
        }
    }

     public function showImportPreview(Request $request)
    {
        $sessionId = $request->query('session_id');
        $sessionData = $request->session()->get('imported_purchase_requests_' . $sessionId);

        if (!$sessionId || !$sessionData) {
            return redirect()->route('users.purchase-requests.create')->with('error', 'Không tìm thấy dữ liệu xem trước hoặc phiên làm việc đã hết hạn. Vui lòng import lại.');
        }

        $importedPurchaseRequests = $sessionData['purchase_requests'];
        $tempAttachmentsDir = $sessionData['temp_attachments_dir']; // Lấy đường dẫn thư mục tạm

        $executingDepartments = ExecutingDepartment::orderBy('name')->get();
        $user = Auth::user();

        // Pass tempAttachmentsDir to the view if needed for client-side processing/display (e.g., direct links if accessible)
        // For security, do not make these temporary files publicly accessible via direct URLs.
        return view('users.purchase_requests.import_preview', compact('importedPurchaseRequests', 'executingDepartments', 'user', 'sessionId', 'tempAttachmentsDir'));
    }

     public function createFromImport(Request $request)
    {
        $sessionId = $request->input('session_id');
        $sessionData = $request->session()->get('imported_purchase_requests_' . $sessionId);

        if (!$sessionId || !$sessionData) {
            return response()->json(['success' => false, 'message' => 'Phiên làm việc đã hết hạn hoặc dữ liệu không tồn tại. Vui lòng import lại.'], 400);
        }

        $importedPurchaseRequests = $sessionData['purchase_requests'];
        $tempAttachmentsDir = $sessionData['temp_attachments_dir'];

        $createdCount = 0;
        $failedCount = 0;
        $errors = [];
        $successfulPiaCodes = [];

        $requester = Auth::user();
        $branch = $requester->mainBranch;
        $section = $requester->sections->first();

        // Lấy lại các PIA codes từ DB một lần nữa để tránh race condition
        $existingPiaCodes = PurchaseRequest::whereIn('pia_code', collect($importedPurchaseRequests)->pluck('pia_code')->toArray())
            ->pluck('pia_code')
            ->toArray();

        foreach ($importedPurchaseRequests as $prData) {
            if (in_array($prData['pia_code'], $existingPiaCodes) || PurchaseRequest::where('pia_code', $prData['pia_code'])->exists()) {
                $errors[] = "Mã phiếu PR_NO '{$prData['pia_code']}' đã tồn tại trong hệ thống. Bỏ qua tạo phiếu này.";
                $failedCount++;
                continue;
            }

            $prData['requester_id'] = $requester->id;
            $prData['branch_id'] = $branch->id;
            $prData['section_id'] = $section->id;

            $itemsData = $prData['items'];
            $temporaryAttachmentPath = $prData['temporary_attachment_path'] ?? null; // Lấy đường dẫn tạm của file đính kèm

            unset($prData['items']);
            unset($prData['temporary_attachment_path']); // Xóa khỏi prData trước khi tạo PurchaseRequest

            DB::beginTransaction();
            try {
                $purchaseRequest = PurchaseRequest::create($prData);
                $purchaseRequest->items()->createMany($itemsData);

                // Di chuyển file đính kèm tạm sang thư mục vĩnh viễn
                if ($temporaryAttachmentPath && Storage::disk('local')->exists($temporaryAttachmentPath)) {
                    $fileName = pathinfo($temporaryAttachmentPath, PATHINFO_BASENAME);
                    $newAttachmentPath = 'pr_attachments/' . $purchaseRequest->pia_code . '_' . time() . '.' . pathinfo($fileName, PATHINFO_EXTENSION);

                    // Copy từ local disk (temp) sang public disk (permanent)
                    Storage::disk('public')->put($newAttachmentPath, Storage::disk('local')->get($temporaryAttachmentPath));

                    // Cập nhật đường dẫn file đính kèm vào phiếu PR
                    $purchaseRequest->update(['attachment_path' => $newAttachmentPath]);
                }

                ApprovalHistory::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'user_id' => Auth::id(),
                    'rank_at_approval' => 'Requester',
                    'action' => 'created',
                    'signature_image_path' => Auth::user()->signature_image_path ?? 'no-signature.png',
                    'comment' => 'Tạo phiếu đề nghị mới từ import Excel.',
                ]);
                DB::commit();
                $createdCount++;
                $successfulPiaCodes[] = $prData['pia_code'];

                $nextApprovers = $this->findNextApprovers($purchaseRequest);
                if ($nextApprovers->isNotEmpty()) {
                    SendApprovalNotification::dispatch($purchaseRequest, $nextApprovers);
                }

            } catch (\Exception $e) {
                DB::rollBack();
                $errors[] = "Lỗi khi lưu phiếu '{$prData['pia_code']}': " . $e->getMessage();
                Log::error("Lỗi khi tạo phiếu từ import '{$prData['pia_code']}': " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                $failedCount++;
            }
        }

        // Bước cuối: Xóa thư mục tạm chứa file đính kèm (nếu tồn tại) và session data
        if ($tempAttachmentsDir && Storage::disk('local')->exists($tempAttachmentsDir)) {
            Storage::disk('local')->deleteDirectory($tempAttachmentsDir);
        }
        $request->session()->forget('imported_purchase_requests_' . $sessionId);

        $message = "Đã tạo thành công {$createdCount} phiếu đề nghị.";
        if ($failedCount > 0) {
            $message .= " Thất bại {$failedCount} phiếu.";
        }

        return response()->json([
            'success' => ($createdCount > 0 && $failedCount == 0),
            'message' => $message,
            'errors' => $errors,
            'successful_pia_codes' => $successfulPiaCodes,
            'redirect_url' => route('users.purchase-requests.index')
        ]);
    }



    public function bulkExportPdf(Request $request)
    {
        $request->validate([
            'request_ids' => 'required|array|min:1',
            'request_ids.*' => 'exists:purchase_requests,id',
        ]);

        $prIds = $request->input('request_ids');
        $zipFileName = 'tong-hop-phieu-de-nghi-' . now()->format('Y-m-d-His') . '.zip';
        $zip = new ZipArchive();

        // Tạo một file ZIP tạm thời trong bộ nhớ
        $tempZipPath = tempnam(sys_get_temp_dir(), 'zip');

        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            return back()->with('error', 'Không thể tạo file nén.');
        }

        foreach ($prIds as $id) {
            $pr = PurchaseRequest::with('requester', 'branch', 'section', 'executingDepartment', 'items', 'approvalHistories.user')->find($id);
            if ($pr) {
                $pdfFileName = 'PR_' . $pr->pia_code . '.pdf';

                // Tạo nội dung file PDF trong bộ nhớ (dưới dạng chuỗi)
                $pdfContent = Excel::raw(new PurchaseRequestExport($pr), ExcelWriter::MPDF);

                // Thêm trực tiếp nội dung chuỗi đó vào file ZIP
                $zip->addFromString($pdfFileName, $pdfContent);
            }
        }

        $zip->close();

        // Tải file ZIP về cho người dùng và tự động xóa sau khi tải xong
        return response()->download($tempZipPath, $zipFileName)->deleteFileAfterSend(true);
    }
}
