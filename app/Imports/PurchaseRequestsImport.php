<?php

namespace App\Imports;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Section;
use App\Models\ExecutingDepartment;
use App\Models\Branch;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PurchaseRequestsImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    private $errors = [];
    private $importedData = [];

    public function collection(Collection $rows)
    {
        // Gỡ bình luận dòng này để xem các tiêu đề cột được Maatwebsite/Excel xử lý như thế nào trong log của Laravel
        // Điều này cực kỳ hữu ích để debug nếu bạn gặp lỗi không tìm thấy cột
        // if ($rows->isNotEmpty() && $rows->first() instanceof \ArrayAccess) {
        //     Log::info('Excel Headers (processed by WithHeadingRow):', $rows->first()->keys()->toArray());
        // }

        // Lấy thông tin prs_id của người dùng đang đăng nhập
        $loggedInUserPrsId = Auth::user()->prs_id;

        // Nhóm các dòng theo cột PR_NO linh hoạt:
        $groupedItems = $rows->groupBy(function($item) {
            return (string)($item['purchreq'] ?? $item['purch_req'] ?? $item['purch.req.'] ?? '');
        });

        $requester = Auth::user();
        if (!$requester) {
            $this->errors[] = "Lỗi xác thực: Không tìm thấy người yêu cầu. Vui lòng đăng nhập lại.";
            return;
        }

        $branch = $requester->mainBranch;
        if (!$branch) {
            $this->errors[] = "Không tìm thấy thông tin nhà máy (Plant) của người dùng. Vui lòng kiểm tra cấu hình người dùng.";
            return;
        }

        $section = $requester->sections->first();
        if (!$section) {
            $this->errors[] = "Không tìm thấy thông tin phòng ban của bạn (Section). Vui lòng kiểm tra cấu hình người dùng.";
            return;
        }

        // Lấy danh sách các mã PR_NO đã tồn tại trong DB để kiểm tra trùng lặp
        $existingPiaCodes = PurchaseRequest::whereIn('pia_code', array_keys($groupedItems->toArray()))
                                            ->pluck('pia_code')
                                            ->toArray();

        foreach ($groupedItems as $piaCode => $items) {
            $priority = (string)($firstItemRow['priority'] ?? null); // <-- CẬP NHẬT DÒNG NÀY

            // Bỏ qua nếu PR_NO trống hoặc đã tồn tại
            if (empty($piaCode)) {
                $this->errors[] = "Dữ liệu chứa một nhóm mặt hàng không có 'PR_NO' (cột 'Purch.Req.' hoặc tương đương). Nhóm này sẽ bị bỏ qua.";
                continue;
            }
            if (in_array($piaCode, $existingPiaCodes)) {
                $this->errors[] = "Mã phiếu PR_NO '{$piaCode}' đã tồn tại trong hệ thống. Phiếu này sẽ bị bỏ qua.";
                continue;
            }

            // Lấy thông tin chung của phiếu từ dòng đầu tiên của nhóm
            $firstItemRow = $items->first();
            $originalExcelRowFirstItem = $rows->search($firstItemRow) + 2;

            // Ánh xạ ExecutingDepartmentCode
            $executingDepartmentCode = (string)($firstItemRow['requisnr'] ?? $firstItemRow['requisnr.'] ?? $firstItemRow['requesting'] ?? '');
            $executingDepartmentCode = trim($executingDepartmentCode);

            $executingDepartment = ExecutingDepartment::where('code', $executingDepartmentCode)->first();
            if (empty($executingDepartmentCode) || !$executingDepartment) {
                $this->errors[] = "Mã phòng ban yêu cầu '{$executingDepartmentCode}' (cột 'Requisnr.' hoặc 'Requesting') không hợp lệ hoặc không tồn tại cho phiếu '{$piaCode}' (Dòng: {$originalExcelRowFirstItem}).";
                continue;
            }

            // Ánh xạ Requested Delivery Date
            $requestedDeliveryDate = null;
            $deliveryDateExcel = $firstItemRow['delivdt'] ?? $firstItemRow['deliv.dt'] ?? $firstItemRow['deliv. date'] ?? $firstItemRow['deliv_date'] ?? null;
            if (!empty($deliveryDateExcel)) {
                try {
                    if (is_numeric($deliveryDateExcel)) {
                        $requestedDeliveryDate = Carbon::createFromTimestamp((($deliveryDateExcel - 25569) * 86400));
                    } else {
                        $requestedDeliveryDate = Carbon::parse($deliveryDateExcel);
                    }
                } catch (\Exception $e) {
                    $this->errors[] = "Ngày yêu cầu giao hàng '{$deliveryDateExcel}' (cột 'Deliv.dt' hoặc 'Deliv. Date') không hợp lệ tại phiếu '{$piaCode}' (Dòng: {$originalExcelRowFirstItem}). Lỗi: {$e->getMessage()}.";
                    continue;
                }
            } else {
                $this->errors[] = "Ngày yêu cầu giao hàng (cột 'Deliv.dt' hoặc 'Deliv. Date') là bắt buộc tại phiếu '{$piaCode}' (Dòng: {$originalExcelRowFirstItem}).";
                continue;
            }

            // Ánh xạ Currency
            $currency = (string)($firstItemRow['crcy'] ?? $firstItemRow['currency'] ?? 'VND');

            // Ánh xạ SAP Request Date
            $sapRequestDate = null;
            $requestDateExcel = $firstItemRow['reqdate'] ?? $firstItemRow['req.date'] ?? $firstItemRow['req_date'] ?? null;
            if (!empty($requestDateExcel)) {
                 try {
                    if (is_numeric($requestDateExcel)) {
                        $sapRequestDate = Carbon::createFromTimestamp((($requestDateExcel - 25569) * 86400));
                    } else {
                        $sapRequestDate = Carbon::parse($requestDateExcel);
                    }
                } catch (\Exception $e) {
                    $this->errors[] = "Ngày yêu cầu (Req.Date) '{$requestDateExcel}' không hợp lệ tại phiếu '{$piaCode}' (Dòng: {$originalExcelRowFirstItem}). Lỗi: {$e->getMessage()}.";
                }
            }

            // Ánh xạ PO Number
            $poNumber = (string)($firstItemRow['po'] ?? null);

            // Ánh xạ PO Date
            $poDate = null;
            $poDateExcel = $firstItemRow['po_date'] ?? $firstItemRow['po date'] ?? null;
            if (!empty($poDateExcel)) {
                try {
                    if (is_numeric($poDateExcel)) {
                        $poDate = Carbon::createFromTimestamp((($poDateExcel - 25569) * 86400));
                    } else {
                        $poDate = Carbon::parse($poDateExcel);
                    }
                } catch (\Exception $e) {
                    $this->errors[] = "Ngày PO (PO Date) '{$poDateExcel}' không hợp lệ tại phiếu '{$piaCode}' (Dòng: {$originalExcelRowFirstItem}). Lỗi: {$e->getMessage()}.";
                }
            }

            // Ánh xạ Created By: log là 'created'. Fallback về prs_id của người dùng đang đăng nhập.
            $sapCreatedBy = (string)($firstItemRow['created'] ?? null);
            if (empty($sapCreatedBy)) {
                $sapCreatedBy = $loggedInUserPrsId;
            }

            $prData = [
                'requester_id' => $requester->id,
                'branch_id' => $branch->id,
                'section_id' => $section->id,
                'executing_department_id' => $executingDepartment->id,
                'pia_code' => $piaCode,
                // CẬP NHẬT DÒNG NÀY ĐỂ GÁN GIÁ TRỊ TỪ SAP REQUEST DATE
                'sap_release_date' => $sapRequestDate ? $sapRequestDate->format('Y-m-d') : null,
                'requested_delivery_date' => $requestedDeliveryDate->format('Y-m-d'),
                'currency' => $currency,
                'requires_director_approval' => false,
                'priority' => $priority,
                'remarks' => null,
                'attachment_path' => null,
                'status' => 'pending_approval',
                'current_rank_level' => 2,
                'sap_request_date' => $sapRequestDate ? $sapRequestDate->format('Y-m-d') : null,
                'po_number' => $poNumber,
                'po_date' => $poDate ? $poDate->format('Y-m-d') : null,
                'sap_created_by' => $sapCreatedBy,
                'total_amount' => 0,
                'total_order_quantity' => 0,
                'total_inventory_quantity' => 0,
            ];

            $currentPrTotalAmount = 0;
            $currentPrTotalOrderQuantity = 0;
            $currentPrTotalInventoryQuantity = 0;
            $itemsForCurrentPr = [];

            foreach ($items as $itemData) {
                $originalExcelRow = $rows->search($itemData) + 2;

                // Ánh xạ Material
                $itemCode = (string)($itemData['material'] ?? '');
                // Ánh xạ Item Name
                $itemName = (string)($itemData['short_text'] ?? $itemData['description'] ?? '');
                // Ánh xạ Quantity
                $orderQuantity = (float)($itemData['quantity'] ?? 0);
                // Ánh xạ Total Val.
                $totalVal = (float)($itemData['total_val'] ?? $itemData['total val.'] ?? 0);

                // Tính toán estimated_price
                $estimatedPrice = (float)($itemData['estimated'] ?? 0);
                if ($estimatedPrice == 0 && $orderQuantity > 0 && $totalVal > 0) {
                    $estimatedPrice = round($totalVal / $orderQuantity, 2);
                } else if ($estimatedPrice == 0 && $orderQuantity == 0 && $totalVal > 0) {
                     $this->errors[] = "Dòng {$originalExcelRow} (PR: {$piaCode}): Số lượng đặt (Quantity) bằng 0, không thể tính giá dự tính từ 'Total Val.'. Giá dự tính sẽ được đặt là 0.";
                }

                // Validation cho từng item
                if (empty($itemCode)) {
                    $this->errors[] = "Dòng {$originalExcelRow} (PR: {$piaCode}): Mã hàng (Material) không được trống.";
                    continue;
                }
                if (empty($itemName)) {
                    $this->errors[] = "Dòng {$originalExcelRow} (PR: {$piaCode}): Tên hàng (Short Text/Description) không được trống.";
                    continue;
                }
                if ($orderQuantity <= 0) {
                    $this->errors[] = "Dòng {$originalExcelRow} (PR: {$piaCode}): Số lượng đặt (Quantity) phải là số dương.";
                    continue;
                }
                if ($estimatedPrice < 0) {
                    $this->errors[] = "Dòng {$originalExcelRow} (PR: {$piaCode}): Giá dự tính phải là số không âm.";
                    continue;
                }

                $subtotal = $orderQuantity * $estimatedPrice;

                // Ánh xạ Plant
                $plant = (string)($itemData['plnt'] ?? $itemData['plant'] ?? '');
                // Ánh xạ SLoc
                $sloc = (string)($itemData['sloc'] ?? '');
                $plantSystem = trim($plant . ' ' . $sloc);

                // Ánh xạ PGr
                $purchaseGroup = (string)($itemData['pgr'] ?? null);

                // Ánh xạ A
                $legacyItemCode = (string)($itemData['a'] ?? null);

                $itemsForCurrentPr[] = [
                    'item_code' => $itemCode,
                    'item_name' => $itemName,
                    'old_item_code' => (string)($itemData['item'] ?? null),
                    'order_quantity' => $orderQuantity,
                    'order_unit' => (string)($itemData['un'] ?? $itemData['unit'] ?? 'PC'),
                    'inventory_quantity' => (float)($itemData['inventory_quantity'] ?? $orderQuantity),
                    'inventory_unit' => (string)($itemData['inventory_unit'] ?? $itemData['un'] ?? $itemData['unit'] ?? 'PC'),
                    'r3_price' => (float)($itemData['r3_price'] ?? 0),
                    'estimated_price' => $estimatedPrice,
                    'subtotal' => $subtotal,
                    'using_dept_code' => (string)($itemData['trackingno'] ?? null),
                    'plant_system' => $plantSystem,
                    'purchase_group' => $purchaseGroup,
                    'legacy_item_code' => $legacyItemCode,
                ];

                $currentPrTotalAmount += $subtotal;
                $currentPrTotalOrderQuantity += $orderQuantity;
                $currentPrTotalInventoryQuantity += ((float)($itemData['inventory_quantity'] ?? $orderQuantity));
            }

            if (empty($itemsForCurrentPr)) {
                $this->errors[] = "Phiếu '{$piaCode}' không có mặt hàng hợp lệ nào để xem trước sau khi kiểm tra dữ liệu.";
                continue;
            }

            $prData['total_amount'] = $currentPrTotalAmount;
            $prData['total_order_quantity'] = $currentPrTotalOrderQuantity;
            $prData['total_inventory_quantity'] = $currentPrTotalInventoryQuantity;
            $prData['items'] = $itemsForCurrentPr;

            $this->importedData[] = $prData;
        }
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getImportedData(): array
    {
        return $this->importedData;
    }
}
