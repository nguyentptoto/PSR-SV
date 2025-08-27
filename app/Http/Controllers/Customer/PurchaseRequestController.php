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
            'attachment_file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
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

            // ... bên trong hàm store()
            $nextApprovers = $this->findNextApprovers($purchaseRequest);
            if ($nextApprovers->isNotEmpty()) {
                foreach ($nextApprovers as $approver) {
                    SendApprovalNotification::dispatch($purchaseRequest, $approver);
                }
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
            'attachment_file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
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

        Log::info('--- Kết thúc Debug ImportPreview Request ---');


        $request->validate([
            'excel_file' => 'required|file|mimes:xls,xlsx|max:10240',
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



            // Bước 4: Lưu dữ liệu đã import (bao gồm đường dẫn file tạm) vào session
            $sessionId = Str::uuid()->toString();
            // Lưu cả đường dẫn thư mục tạm cho việc cleanup sau này
            $sessionData = [
                'purchase_requests' => $importedData,
                'temp_attachments_dir' => null // Set to null as no ZIP is processed
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
        $tempAttachmentsDir = $sessionData['temp_attachments_dir']; // This will be null now


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
        $createdCount = 0;
        $failedCount = 0;
        $errors = [];

        $requester = Auth::user();
        $branch = $requester->mainBranch;
        $section = $requester->sections->first();

        foreach ($importedPurchaseRequests as $prIndex => $prWrapper) {
            $prDataFromSession = $prWrapper['pr_data'];
            $itemsDataFromSession = $prWrapper['items'];
            $prFormData = $request->input("prs.{$prIndex}");

            $finalPrData = array_merge($prDataFromSession, $prFormData);

            $finalPrData['requester_id'] = $requester->id;
            $finalPrData['branch_id'] = $branch->id;
            $finalPrData['section_id'] = $section->id;
            $finalPrData['requires_director_approval'] = (bool) ($finalPrData['requires_director_approval'] ?? false);

            // SỬA ĐỔI QUAN TRỌNG: Thêm status và current_rank_level
            $finalPrData['status'] = 'pending_approval';
            $finalPrData['current_rank_level'] = 2;

            $itemsDataFromForm = $prFormData['items'] ?? $itemsDataFromSession;

            $processedItemsData = [];
            foreach ($itemsDataFromForm as $item) {
                $orderQuantity = (float)($item['order_quantity'] ?? 0);
                $estimatedPrice = (float)($item['estimated_price'] ?? 0);
                $item['subtotal'] = $orderQuantity * $estimatedPrice;
                $processedItemsData[] = $item;
            }

            DB::beginTransaction();
            try {
                if (PurchaseRequest::where('pia_code', $finalPrData['pia_code'])->exists()) {
                    throw new \Exception("Mã phiếu PR_NO '{$finalPrData['pia_code']}' đã tồn tại.");
                }

                $purchaseRequest = PurchaseRequest::create($finalPrData);
                $purchaseRequest->items()->createMany($processedItemsData);

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

                $nextApprovers = $this->findNextApprovers($purchaseRequest);
                if ($nextApprovers->isNotEmpty()) {
                    foreach ($nextApprovers as $approver) {
                        SendApprovalNotification::dispatch($purchaseRequest, $approver);
                    }
                }

            } catch (\Exception $e) {
                DB::rollBack();
                $errors[] = "Lỗi khi lưu phiếu '{$finalPrData['pia_code']}': " . $e->getMessage();
                $failedCount++;
            }
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


    private function storeFile($file, $piaCode)
    {
        Log::info("DEBUG: Bắt đầu lưu file: {$file->getClientOriginalName()} cho PR: {$piaCode}");
        $extension = $file->getClientOriginalExtension();
        $newFileName = $piaCode . '_' . time() . '.' . $extension;
        $finalPath = $file->storeAs('pr_attachments', $newFileName, 'public'); // Lưu vào thư mục public disk
        Log::info("DEBUG: File đã lưu vào: {$finalPath}");
        return $finalPath;
    }




}
