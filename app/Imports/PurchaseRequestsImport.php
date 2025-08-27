<?php

namespace App\Imports;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Models\ExecutingDepartment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Validators\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class PurchaseRequestsImport extends DefaultValueBinder implements ToCollection, WithHeadingRow, WithValidation, SkipsOnError, WithChunkReading, WithEvents, WithCustomValueBinder
{
    use SkipsErrors;

    private $importedData = [];
    private $rowCounter = 1;

    public function bindValue(Cell $cell, $value)
    {
        $cell->setValueExplicit($value, DataType::TYPE_STRING);
        return true;
    }

    public function collection(Collection $rows)
    {
        $groupedRequests = $rows->groupBy('purchreq');

        foreach ($groupedRequests as $piaCode => $items) {
            $firstItem = $items->first();
            $firstItemRowNumber = $this->rowCounter + $items->keys()->first();

            if (empty($piaCode)) {
                $this->errors[] = "Dòng {$firstItemRowNumber}: Bỏ qua phiếu do Mã Phiếu (Purch.Req.) trống.";
                continue;
            }

            $creatorPrsId = $firstItem['created'] ?? null;
            $requesterUser = $creatorPrsId ? User::where('prs_id', (string)$creatorPrsId)->first() : null;
            $requesterId = $requesterUser->id ?? Auth::id();

            if (!$requesterUser && $creatorPrsId) {
                $this->errors[] = "Mã phiếu {$piaCode} (dòng {$firstItemRowNumber}): Không tìm thấy người tạo (created: {$creatorPrsId}). Phiếu sẽ được gán cho người đang import.";
            }

            $requisnrCode = $firstItem['requisnr'] ?? null;
            $executingDepartment = $requisnrCode ? ExecutingDepartment::where('code', $requisnrCode)->first() : null;

            $prData = [
                'pia_code' => (string)$piaCode,
                'sap_release_date' => $this->excelDateToCarbon($firstItem['req_date'] ?? null),
                'requested_delivery_date' => $this->excelDateToCarbon($firstItem['delivdate'] ?? null),
                'currency' => $firstItem['crcy'] ?? 'VND',
                'sap_request_date' => $this->excelDateToCarbon($firstItem['req_date'] ?? null),
                'sap_created_by' => $creatorPrsId,
                'requester_id' => $requesterId,
                'executing_department_id' => $executingDepartment->id ?? null,
            ];

            $prRules = [
                'pia_code' => ['required', 'string', 'max:255', Rule::unique('purchase_requests', 'pia_code')],
                'requested_delivery_date' => 'required|date',
            ];

            $validator = Validator::make($prData, $prRules);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->errors[] = "Mã phiếu {$piaCode} (dòng {$firstItemRowNumber}): " . $error;
                }
                continue;
            }

            $itemsData = [];
            foreach ($items as $key => $item) {
                $currentItemRowNumber = $this->rowCounter + $key;
                if (empty($item['material'])) continue;

                $orderQuantity = $this->parseNumeric($item['order_unit_qty'] ?? $item['quantity'] ?? null);
                $estTotalAmount = $this->parseNumeric($item['est_total_amount'] ?? null);
                $valnPrice = $this->parseNumeric($item['valn_price'] ?? null);
                $orderUnit = $item['order_unit'] ?? $item['un'] ?? 'N/A';

                $estimatedPrice = $valnPrice;
                if (empty($estimatedPrice) || $estimatedPrice == 0) {
                    $estimatedPrice = (!empty($estTotalAmount) && !empty($orderQuantity) && $orderQuantity != 0) ? ($estTotalAmount / $orderQuantity) : 0;
                }

                $itemData = [
                    'item_code' => $item['material'],
                    'item_name' => $item['short_text'],
                    'old_item_code' => $item['old_material_number'] ?? null,
                    'order_quantity' => $orderQuantity,
                    'order_unit' => $orderUnit,
                    'inventory_quantity' => $this->parseNumeric($item['quantity'] ?? $orderQuantity),
                    'inventory_unit' => $item['un'] ?? $orderUnit,
                    'r3_price' => $this->parseNumeric($item['r3_unit_price'] ?? null),
                    'estimated_price' => $estimatedPrice,
                    'subtotal' => ($orderQuantity ?? 0) * ($estimatedPrice ?? 0),
                    'using_dept_code' => $item['requisnr'] . '-' . ($item['a'] ?? null),
                    'plant_system' => ($item['plnt'] ?? '') . '-' . ($item['sloc'] ?? '')  ?? '',
                ];

                $itemRules = [
                    'item_code' => 'required|string|max:255',
                    'item_name' => 'required|string|max:255',
                    'old_item_code' => 'nullable|string|max:255',
                    'order_quantity' => 'required|numeric|min:0.001',
                    'order_unit' => 'nullable|string|max:20',
                    'inventory_quantity' => 'nullable|numeric|min:0',
                    'inventory_unit' => 'nullable|string|max:20',
                    'r3_price' => 'nullable|numeric|min:0',
                    'estimated_price' => 'required|numeric|min:0',
                    'subtotal' => 'nullable|numeric',
                    'using_dept_code' => 'nullable|string|max:255',
                    'plant_system' => 'nullable|string|max:255',
                ];

                $itemValidator = Validator::make($itemData, $itemRules);
                if ($itemValidator->fails()) {
                    foreach ($itemValidator->errors()->all() as $error) {
                        $this->errors[] = "Mã phiếu {$piaCode}, Mã hàng {$item['material']} (dòng {$currentItemRowNumber}): " . $error;
                    }
                    continue;
                }
                $itemsData[] = $itemData;
            }

            if (empty($itemsData)) {
                $this->errors[] = "Mã phiếu {$piaCode} (dòng {$firstItemRowNumber}): Không có mặt hàng hợp lệ nào.";
                continue;
            }

            $prData['total_order_quantity'] = array_sum(array_column($itemsData, 'order_quantity'));
            $prData['total_inventory_quantity'] = array_sum(array_column($itemsData, 'inventory_quantity'));
            $prData['total_amount'] = array_sum(array_column($itemsData, 'subtotal'));

            $this->importedData[] = [
                'pr_data' => $prData,
                'items' => $itemsData,
            ];
        }
    }

    public function getImportedData(): array
    {
        return $this->importedData;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function rules(): array
    {
        return [
            'purchreq' => 'required|string',
            'material' => 'required|string',
            'short_text' => 'required|string',
            'delivdate' => 'required',
            'requisnr' => 'required|string',
            'sloc' => 'nullable|string',
            'req_date' => 'nullable',
            'plnt' => 'nullable|string',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'purchreq.required' => 'Cột "Purch.Req." không được để trống.',
            'purchreq.string' => 'Cột "Purch.Req." phải là chuỗi.',
            'material.required' => 'Cột "Material" không được để trống.',
            'short_text.required' => 'Cột "Short Text" không được để trống.',
            'delivdate.required' => 'Cột "Deliv.Date" không được để trống.',
            'requisnr.required' => 'Cột "Requisnr." không được để trống.',
            'requisnr.string' => 'Cột "Requisnr." phải là chuỗi.',
            'sloc.string' => 'Cột "SLoc" phải là chuỗi.',
            'plnt.string' => 'Cột "Plnt" phải là chuỗi.',
        ];
    }

    public function onError(\Throwable $e)
    {
        if ($e instanceof ValidationException) {
            $failures = $e->failures();
            foreach ($failures as $failure) {
                $rowNumber = $failure->row();
                $column = $failure->attribute();
                $errorMessages = $failure->errors();
                $value = $failure->values()[$column] ?? 'N/A';

                $logMessage = "Validation Lỗi - Dòng: {$rowNumber}, Cột: '{$column}', Giá trị: '{$value}'. Lỗi: " . implode(', ', $errorMessages);
                Log::error($logMessage);

                $this->errors[] = "Dòng {$rowNumber}: " . implode(', ', $errorMessages) . " (Cột: {$column})";
            }
        } else {
            $this->errors[] = "Lỗi không xác định: " . $e->getMessage();
            Log::critical("Lỗi nghiêm trọng khi import: " . $e->getMessage(), ['exception' => $e]);
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    private function excelDateToCarbon($value)
    {
        if (empty($value)) return null;

        if (is_numeric($value)) {
            if (strlen((string)$value) === 8 && checkdate(substr($value, 4, 2), substr($value, 6, 2), substr($value, 0, 4))) {
                try {
                    return Carbon::createFromFormat('Ymd', $value);
                } catch (\Exception $e) {
                }
            }
            try {
                return Carbon::createFromTimestamp(intval(($value - 25569) * 86400));
            } catch (\Exception $e) {
                return null;
            }
        }

        if (is_string($value)) {
            try {
                if (preg_match('/^\d{4}\.\d{2}\.\d{2}$/', $value)) {
                    return Carbon::createFromFormat('Y.m.d', $value)->startOfDay();
                }
                return Carbon::parse($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    private function parseNumeric($value)
    {
        if (is_null($value) || $value === '') return null;
        $cleanedValue = str_replace(',', '', $value);
        if (is_numeric($cleanedValue)) return (float)$cleanedValue;
        return null;
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function(BeforeSheet $event) {
                $this->rowCounter = 1;
            },
        ];
    }
}
