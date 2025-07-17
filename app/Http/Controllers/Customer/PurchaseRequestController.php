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
class PurchaseRequestController extends Controller
{
    /**
     * Hiển thị danh sách các phiếu đề nghị của người dùng.
     */
    public function index()
    {
        $purchaseRequests = PurchaseRequest::where('requester_id', Auth::id())
                                            ->with(['branch', 'executingDepartment'])
                                            ->latest()
                                            ->paginate(15);
        return view('users.purchase_requests.index', compact('purchaseRequests'));
    }

    /**
     * Hiển thị form tạo phiếu đề nghị mới.
     */
    public function create()
    {
        $user = Auth::user();
        $user->load('sections', 'mainBranch');
        $executingDepartments = ExecutingDepartment::orderBy('name')->get();
        return view('users.purchase_requests.create', compact('user', 'executingDepartments'));
    }

    /**
     * Lưu phiếu đề nghị mới vào database.
     */




     public function store(Request $request)
    {
        // Log dữ liệu để debug


        // Xác thực, bỏ các trường tổng
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
            'items' => 'required|array|min:1',
            'items.*.item_code' => 'required|string|max:255',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.order_quantity' => 'required|numeric|min:0.001',
            'items.*.estimated_price' => 'required|numeric|min:0',
            // Thêm validation cho inventory_quantity nếu bạn muốn nó là số và có giá trị mặc định 0
            'items.*.inventory_quantity' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $prData = $request->except(['_token', 'items', 'attachment_file']);

            // Tính tổng trên server
            $totalAmount = 0;
            $totalOrderQuantity = 0;
            $totalInventoryQuantity = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += $item['order_quantity'] * $item['estimated_price'];
                $totalOrderQuantity += $item['order_quantity'];
                // Sử dụng null coalescing operator để đảm bảo giá trị là số hoặc 0
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

            $purchaseRequest = PurchaseRequest::create($prData);

            foreach ($validated['items'] as $itemData) {
                $purchaseRequest->items()->create([
                    'item_code' => $itemData['item_code'],
                    'item_name' => $itemData['item_name'],
                    'old_item_code' => $itemData['old_item_code'] ?? null,
                    'order_quantity' => $itemData['order_quantity'],
                    'order_unit' => $itemData['order_unit'] ?? 'N/A',
                    'inventory_quantity' => $itemData['inventory_quantity'] ?? 0, // Đảm bảo luôn là số
                    'inventory_unit' => $itemData['inventory_unit'] ?? 'N/A',
                    'r3_price' => $itemData['r3_price'] ?? 0, // Đảm bảo luôn là số
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

            $nextApprovers = $this->findNextApprovers($purchaseRequest);
            if ($nextApprovers->isNotEmpty()) {
                SendApprovalNotification::dispatch($purchaseRequest, $nextApprovers);
            }

            return redirect()->route('users.purchase-requests.index')->with('success', 'Tạo phiếu đề nghị thành công!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Đã xảy ra lỗi: ' . $e->getMessage())->withInput();
        }
    }




    /**
     * Hiển thị chi tiết một phiếu đề nghị.
     */
    public function show(Request $request, PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load('requester.jobTitle', 'branch', 'section', 'executingDepartment', 'items', 'approvalHistories.user');
        $from = $request->query('from', 'my-requests');
        return view('users.purchase_requests.show', compact('purchaseRequest', 'from'));
    }

    /**
     * Hiển thị form chỉnh sửa phiếu đề nghị.
     */
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

    /**
     * Cập nhật thông tin phiếu đề nghị.
     */
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
            'items' => 'required|array|min:1',
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
            return back()->with('error', 'Đã xảy ra lỗi khi cập nhật: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Xóa một phiếu đề nghị.
     */
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

    /**
     * Xử lý việc export ra file Excel.
     */
    public function exportExcel(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load('requester', 'branch', 'executingDepartment', 'items', 'approvalHistories.user');
        $fileName = 'PR_' . $purchaseRequest->pia_code . '.xlsx';
        return Excel::download(new PurchaseRequestExport($purchaseRequest), $fileName);
    }

    /**
     * Xử lý việc export ra file PDF.
     */
    public function exportPdf(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load('requester', 'branch', 'executingDepartment', 'items', 'approvalHistories.user');
        $fileName = 'PR-' . $purchaseRequest->pia_code . '.pdf';
        return Excel::download(new PurchaseRequestExport($purchaseRequest), $fileName, ExcelWriter::MPDF);
    }

    /**
     * Hàm phụ trợ để tìm người duyệt tiếp theo.
     */
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

        if (!$targetGroupId) return collect();

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
}
