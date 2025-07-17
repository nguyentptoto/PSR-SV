<?php

namespace App\Exports;

use App\Models\PurchaseRequest;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PurchaseRequestExport implements WithEvents, WithDrawings
{
    protected $pr;
    protected $approvalHistory;

    public function __construct(PurchaseRequest $purchaseRequest)
    {
        $this->pr = $purchaseRequest;
        $this->approvalHistory = $purchaseRequest->approvalHistories->keyBy('rank_at_approval');
    }

    public function drawings(): array
    {
        $drawings = [];
        $signatureCoordinates = [
            'Requester' => 'D6',
            'Manager (Requesting)' => 'F6',
            'General Manager' => 'H6',          // Ô của C3
            'Director (Requesting)' => 'J6',    // Ô của C4
            'Manager (Purchasing)' => 'L6',
            'Director (Purchasing)' => 'N6',
        ];

        foreach ($this->approvalHistory as $rankName => $history) {
            $user = $history->user;

            if ($user && $user->signature_image_path && $user->signature_image_path !== 'no-signature.png') {
                $path = storage_path('app/public/' . $user->signature_image_path);

                if (file_exists($path)) {
                    if ($rankName === 'General Manager' && !$this->pr->requires_director_approval) {
                        // Chèn chữ ký vào ô của General Manager (C3)
                        $gmDrawing = new Drawing();
                        $gmDrawing->setName('GM Signature')
                            ->setPath($path)
                            ->setCoordinates($signatureCoordinates['General Manager'])
                            ->setHeight(80)
                            ->setWidth(80);
                        $drawings[] = $gmDrawing;

                        // Sao chép chữ ký vào ô của Director (C4)
                        $directorDrawing = clone $gmDrawing;
                        $directorDrawing->setName('Director Signature (from GM)')
                            ->setCoordinates($signatureCoordinates['Director (Requesting)']);
                        $drawings[] = $directorDrawing;
                    }
                    else {
                        $coordinate = $signatureCoordinates[$rankName] ?? null;
                        if ($coordinate) {
                            $drawing = new Drawing();
                            $drawing->setName($rankName . ' Signature')
                                ->setPath($path)
                                ->setCoordinates($coordinate)
                                ->setHeight(80)
                                ->setWidth(80);
                            $drawings[] = $drawing;
                        }
                    }
                }
            }
        }
        return $drawings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->applyStyles($sheet);
                $this->drawData($sheet);
                $pageSetup = $sheet->getPageSetup();
                $pageSetup->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageMargins()->setTop(0.75)->setRight(0.25)->setBottom(0.75)->setLeft(0.25);
            }
        ];
    }

    private function applyStyles(Worksheet $sheet): void
    {
        // ... (Không thay đổi)
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(12);
        $sheet->getColumnDimension('I')->setWidth(12);
        $sheet->getColumnDimension('J')->setWidth(12);
        $sheet->getColumnDimension('K')->setWidth(12);
        $sheet->getColumnDimension('L')->setWidth(12);
        $sheet->getColumnDimension('M')->setWidth(12);
        $sheet->getColumnDimension('N')->setWidth(12);
        $sheet->getColumnDimension('O')->setWidth(12);
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('DejaVu Sans')->setSize(10);
    }

    private function drawPageHeader(Worksheet $sheet, int $startRow): void
    {
        // ... (Không thay đổi)
        $sheet->getRowDimension($startRow)->setRowHeight(60);

        $prNoText = 'PR NO.: ' . $this->pr->pia_code;
        $urgentCheck = ($this->pr->priority === 'urgent') ? '☒' : '☐';
        $normalCheck = ($this->pr->priority === 'normal') ? '☒' : '☐';
        $quotationCheck = ($this->pr->priority === 'quotation_only') ? '☒' : '☐';
        $line1 = "Urgent/Khẩn cấp " . $urgentCheck;
        $line2 = "Normal/Bình thường " . $normalCheck;
        $line3 = "Quotation only/Báo giá " . $quotationCheck;
        $priorityText = $line1 . "\n" . $line2 . "\n" . $line3;
        $combinedPrAndPriorityText = $prNoText . "\n\n" . $priorityText;
        $sheet->mergeCells('A' . $startRow . ':C' . $startRow)->setCellValue('A' . $startRow, $combinedPrAndPriorityText);
        $sheet->getStyle('A' . $startRow)->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setWrapText(true);

        $sheet->mergeCells('E' . $startRow . ':I' . $startRow)->setCellValue('E' . $startRow, "PURCHASE REQUISITION\nGIẤY ĐỀ NGHỊ MUA HÀNG");
        $titleStyle = $sheet->getStyle('E' . $startRow);
        $titleStyle->getFont()->setBold(true)->setSize(14);
        $titleStyle->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $dateString = 'Date of issue PR/Ngày phát hành:' . ($this->pr->created_at ? $this->pr->created_at->format('Y.m.d') : now('Asia/Ho_Chi_Minh')->format('Y.m.d'));
        $combinedText = $dateString . "\n(For Requester)\nFormal Approval NO.";
        $cellRange = 'L' . $startRow . ':O' . $startRow;
        $sheet->mergeCells($cellRange)->setCellValue('L' . $startRow, $combinedText);
        $headerStyle = $sheet->getStyle($cellRange);
        $headerStyle->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);
    }

    private function drawItemsTableHeader(Worksheet $sheet, int $startRow): void
    {
        // ... (Không thay đổi)
        $sheet->getRowDimension($startRow)->setRowHeight(30);
        $sheet->setCellValue('A' . $startRow, "NO\nSTT");
        $headerText = "Item Code/Mã hàng\nDescription/Tên hàng";
        $sheet->mergeCells('B' . $startRow . ':C' . $startRow)->setCellValue('B' . $startRow, $headerText);
        $otherHeaders = [
            'D' => "Old material\nMã hàng cũ", 'E' => "Order unit Q'ty\nSL Đặt", 'F' => "Order unit\nĐV đặt hàng",
            'G' => "Base unit Q'ty\nSố lượng quản lý kho", 'H' => "Base unit\nĐ/v quản lý kho", 'I' => "R3 Unit Price\nGiá R3",
            'J' => "Estimated Unit Price\nGiá dự tính", 'K' => "Est Total amount\nTotal giá dự tính", 'L' => "Currency\nTiền tệ",
            'M' => "Requests Delivery Date\nNgày Y/C giao hàng", 'N' => "Cost Center\nMã phòng sử dụng", 'O' => "Plant\n Hệ"
        ];
        foreach ($otherHeaders as $col => $text) {
            $sheet->setCellValue($col . $startRow, $text);
        }
        $styleRange = 'A' . $startRow . ':O' . $startRow;
        $headerStyle = $sheet->getStyle($styleRange);
        $headerStyle->getFont()->setSize(8);
        $headerStyle->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $headerStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function drawData(Worksheet $sheet): void
    {
        // --- PHẦN 1: Vẽ các thành phần cố định và trang đầu tiên ---
        $this->drawPageHeader($sheet, 1);
        $approvalStartRow = 3;

        // --- Vẽ layout khối phê duyệt ---
        $sheet->getStyle('A' . $approvalStartRow . ':O' . ($approvalStartRow + 8))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->mergeCells('A' . $approvalStartRow . ':C' . ($approvalStartRow + 2))->setCellValue('A' . $approvalStartRow, "Requesting Dept. Code\nMã phòng đề nghị");
        $sheet->getStyle('A' . $approvalStartRow . ':C' . ($approvalStartRow + 2))->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->mergeCells('D' . $approvalStartRow . ':K' . $approvalStartRow)->setCellValue('D' . $approvalStartRow, "Requesting Dept.\nPhòng đề nghị");
        $sheet->mergeCells('L' . $approvalStartRow . ':O' . $approvalStartRow)->setCellValue('L' . $approvalStartRow, "Purchasing Dept.\nPhòng mua");
        $sheet->getStyle('A' . $approvalStartRow . ':O' . $approvalStartRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9D9D9');
        $sheet->getStyle('D' . $approvalStartRow . ':O' . $approvalStartRow)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->mergeCells('D' . ($approvalStartRow + 1) . ':E' . ($approvalStartRow + 2))->setCellValue('D' . ($approvalStartRow + 1), "Requester\nNhân viên");
        $sheet->mergeCells('F' . ($approvalStartRow + 1) . ':G' . ($approvalStartRow + 2))->setCellValue('F' . ($approvalStartRow + 1), "Manager\nTrưởng phòng");
        $sheet->mergeCells('H' . ($approvalStartRow + 1) . ':I' . ($approvalStartRow + 2))->setCellValue('H' . ($approvalStartRow + 1), "General Manager\nFormal Approval");
        $sheet->mergeCells('J' . ($approvalStartRow + 1) . ':K' . ($approvalStartRow + 2))->setCellValue('J' . ($approvalStartRow + 1), "Director\nGiám đốc");
        $sheet->mergeCells('L' . ($approvalStartRow + 1) . ':M' . ($approvalStartRow + 2))->setCellValue('L' . ($approvalStartRow + 1), "Manager\nTrưởng phòng");
        $sheet->mergeCells('N' . ($approvalStartRow + 1) . ':O' . ($approvalStartRow + 2))->setCellValue('N' . ($approvalStartRow + 1), "Director\nGiám đốc");
        $sheet->getStyle('D' . ($approvalStartRow + 1) . ':O' . ($approvalStartRow + 2))->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->mergeCells('D' . ($approvalStartRow + 3) . ':E' . ($approvalStartRow + 6));
        $sheet->mergeCells('F' . ($approvalStartRow + 3) . ':G' . ($approvalStartRow + 6));
        $sheet->mergeCells('H' . ($approvalStartRow + 3) . ':I' . ($approvalStartRow + 6));
        $sheet->mergeCells('J' . ($approvalStartRow + 3) . ':K' . ($approvalStartRow + 6));
        $sheet->mergeCells('L' . ($approvalStartRow + 3) . ':M' . ($approvalStartRow + 6));
        $sheet->mergeCells('N' . ($approvalStartRow + 3) . ':O' . ($approvalStartRow + 6));

        // --- Ghi thông tin phòng ban ---
        $sheet->mergeCells('A' . ($approvalStartRow + 3) . ':C' . ($approvalStartRow + 3))->setCellValue('A' . ($approvalStartRow + 3), $this->pr->executingDepartment->code);
        $sheet->getStyle('A' . ($approvalStartRow + 3) . ':C' . ($approvalStartRow + 3))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->mergeCells('A' . ($approvalStartRow + 4) . ':C' . ($approvalStartRow + 4))->setCellValue('A' . ($approvalStartRow + 4), $this->pr->executingDepartment->name);
        $sheet->getStyle('A' . ($approvalStartRow + 4) . ':C' . ($approvalStartRow + 4))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $requesterDetails = "{$this->pr->requester->prs_id}\n{$this->pr->requester->name}";
        $sheet->mergeCells('A' . ($approvalStartRow + 5) . ':C' . ($approvalStartRow + 7))->setCellValue('A' . ($approvalStartRow + 5), $requesterDetails);
        $sheet->getStyle('A' . ($approvalStartRow + 5) . ':C' . ($approvalStartRow + 7))->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(($approvalStartRow + 3))->setRowHeight(28);
        $sheet->getRowDimension(($approvalStartRow + 4))->setRowHeight(28);

        // ======================= BẮT ĐẦU KHỐI ĐÃ SỬA LỖI =======================
        $history = $this->approvalHistory;
        $infoRow = $approvalStartRow + 7;

        $sheet->getRowDimension($infoRow)->setRowHeight(28);

        $sheet->mergeCells('D' . $infoRow . ':E' . $infoRow);
        $sheet->mergeCells('F' . $infoRow . ':G' . $infoRow);
        $sheet->mergeCells('H' . $infoRow . ':I' . $infoRow);
        $sheet->mergeCells('J' . $infoRow . ':K' . $infoRow);
        $sheet->mergeCells('L' . $infoRow . ':M' . $infoRow);
        $sheet->mergeCells('N' . $infoRow . ':O' . $infoRow);

        // Hàm lấy Tên và Ngày
        $getApprovalInfo = fn($rank) => isset($history[$rank])
            ? ($history[$rank]->user->name . "\n" . $history[$rank]->created_at->format('H:i d/m/Y'))
            : '';

        // Ghi thông tin cho các vai trò thông thường
        $sheet->setCellValue('D' . $infoRow, $getApprovalInfo('Requester'));
        $sheet->setCellValue('F' . $infoRow, $getApprovalInfo('Manager (Requesting)'));
        $sheet->setCellValue('L' . $infoRow, $getApprovalInfo('Manager (Purchasing)'));
        $sheet->setCellValue('N' . $infoRow, $getApprovalInfo('Director (Purchasing)'));

        // Xử lý logic đặc biệt cho GM (C3) và Director (C4)
        $gmInfo = $getApprovalInfo('General Manager');
        $sheet->setCellValue('H' . $infoRow, $gmInfo); // Luôn điền thông tin của GM vào ô của GM

        // Nếu GM đã duyệt VÀ không yêu cầu Director duyệt, sao chép thông tin của GM vào ô Director
        if (!empty($gmInfo) && !$this->pr->requires_director_approval) {
            $sheet->setCellValue('J' . $infoRow, $gmInfo);
        } else {
            // Ngược lại, điền thông tin của Director như bình thường (sẽ rỗng nếu chưa duyệt)
            $sheet->setCellValue('J' . $infoRow, $getApprovalInfo('Director (Requesting)'));
        }

        // Định dạng chung cho cả dòng
        $infoStyle = $sheet->getStyle('D' . $infoRow . ':O' . $infoRow);
        $infoStyle->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $infoStyle->getFont()->setSize(8);
        // ======================= KẾT THÚC KHỐI ĐÃ SỬA LỖI =======================

        // --- KHỞI TẠO BIẾN VÀ VẼ BẢNG ITEMS ---
        $items = $this->pr->items->all();
        $totalItems = count($items);
        $itemsPerPageFirst = 10;
        $itemsPerPageSubsequent = 17;
        $currentItemIndex = 0;
        $currentRow = 12;
        $pageIndex = 1;

        $this->drawItemsTableHeader($sheet, $currentRow);
        $currentRow++;

        $isFirstPageLastPage = $totalItems <= $itemsPerPageFirst;
        $itemsToDrawOnFirstPage = min($totalItems, $itemsPerPageFirst);
        $itemsLeftAfterFirstPage = $totalItems - $itemsToDrawOnFirstPage;
        if (!$isFirstPageLastPage && $itemsLeftAfterFirstPage > $itemsPerPageSubsequent && ($itemsLeftAfterFirstPage - $itemsPerPageSubsequent == 1)) {
            $itemsToDrawOnFirstPage--;
        }

        for ($i = 0; $i < $itemsToDrawOnFirstPage; $i++) {
            $item = $items[$i];
            $itemCodeAndName = " " . $item->item_code . "\n " . $item->item_name;
            $rowData = [$i + 1, $itemCodeAndName, null, $item->old_item_code, $item->order_quantity, $item->order_unit, $item->inventory_quantity, $item->inventory_unit, $item->r3_price, $item->estimated_price, $item->subtotal, $this->pr->currency, $this->pr->requested_delivery_date->format('Y.m.d'), $item->using_dept_code, $item->plant_system];
            $sheet->fromArray($rowData, null, 'A' . $currentRow, true);
            $sheet->mergeCells('B' . $currentRow . ':C' . $currentRow);
            $sheet->getRowDimension($currentRow)->setRowHeight(25);
            $sheet->getStyle('A' . $currentRow . ':O' . $currentRow)->getFont()->setSize(7);
            $sheet->getStyle('A' . $currentRow . ':O' . $currentRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('B' . $currentRow . ':C' . $currentRow)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('D' . $currentRow . ':O' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $currentRow++;
        }

        if (!$isFirstPageLastPage) {
            $emptyRowsToAdd = $itemsPerPageFirst - $itemsToDrawOnFirstPage;
            if ($emptyRowsToAdd > 0) {
                for ($j = 0; $j < $emptyRowsToAdd; $j++) {
                    $sheet->getRowDimension($currentRow)->setRowHeight(25);
                    $sheet->getStyle('A' . $currentRow . ':O' . $currentRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $currentRow++;
                }
            }
        }

        $this->drawPageFooter($sheet, $currentRow);
        $currentRow += 4;
        $this->drawManualPageNumberFooter($sheet, $currentRow, $pageIndex);
        $currentRow++;
        $currentItemIndex = $itemsToDrawOnFirstPage;

        // --- PHẦN 2: Vòng lặp cho các trang tiếp theo ---
        while ($currentItemIndex < $totalItems) {
            $pageIndex++;
            $sheet->setBreak('A' . ($currentRow - 1), Worksheet::BREAK_ROW);
            $this->drawPageHeader($sheet, $currentRow);
            $currentRow += 2;
            $this->drawItemsTableHeader($sheet, $currentRow);
            $currentRow++;

            $itemsOnThisPage = $itemsPerPageSubsequent;
            $itemsLeft = $totalItems - $currentItemIndex;
            $isLastPage = $itemsLeft <= $itemsOnThisPage;

            if (!$isLastPage && $itemsLeft > $itemsOnThisPage && ($itemsLeft - $itemsOnThisPage == 1)) {
                $itemsOnThisPage--;
            }

            $endItemIndex = min($currentItemIndex + $itemsOnThisPage, $totalItems);
            $itemsDrawnOnThisPage = 0;

            for ($i = $currentItemIndex; $i < $endItemIndex; $i++) {
                $item = $items[$i];
                $itemCodeAndName = " " . $item->item_code . "\n " . $item->item_name;
                $rowData = [$i + 1, $itemCodeAndName, null, $item->old_item_code, $item->order_quantity, $item->order_unit, $item->inventory_quantity, $item->inventory_unit, $item->r3_price, $item->estimated_price, $item->subtotal, $this->pr->currency, $this->pr->requested_delivery_date->format('Y.m.d'), $item->using_dept_code, $item->plant_system];
                $sheet->fromArray($rowData, null, 'A' . $currentRow, true);
                $sheet->mergeCells('B' . $currentRow . ':C' . $currentRow);
                $sheet->getRowDimension($currentRow)->setRowHeight(25);
                $sheet->getStyle('A' . $currentRow . ':O' . $currentRow)->getFont()->setSize(7);
                $sheet->getStyle('A' . $currentRow . ':O' . $currentRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('B' . $currentRow . ':C' . $currentRow)->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('D' . $currentRow . ':O' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $currentRow++;
                $itemsDrawnOnThisPage++;
            }

            if (!$isLastPage) {
                $emptyRowsToAdd = $itemsPerPageSubsequent - $itemsDrawnOnThisPage;
                if ($emptyRowsToAdd > 0) {
                     for ($j = 0; $j < $emptyRowsToAdd; $j++) {
                        $sheet->getRowDimension($currentRow)->setRowHeight(25);
                        $sheet->getStyle('A' . $currentRow . ':O' . $currentRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                        $currentRow++;
                    }
                }
            }

            $this->drawPageFooter($sheet, $currentRow);
            $currentRow += 4;
            $this->drawManualPageNumberFooter($sheet, $currentRow, $pageIndex);
            $currentRow++;
            $currentItemIndex = $endItemIndex;
        }
    }

    private function drawPageFooter(Worksheet $sheet, int $footerStartRow): void
    {
        // ... (Không thay đổi)
        $sheet->mergeCells('A' . $footerStartRow . ':D' . $footerStartRow)->setCellValue('A' . $footerStartRow, 'SubTotal');
        $sheet->setCellValue('E' . $footerStartRow, number_format($this->pr->total_order_quantity ?? 0));
        $sheet->setCellValue('G' . $footerStartRow, number_format($this->pr->total_inventory_quantity ?? 0));
        $sheet->mergeCells('L' . $footerStartRow . ':M' . $footerStartRow)->setCellValue('L' . $footerStartRow, 'Grand Total:');
        $sheet->mergeCells('N' . $footerStartRow . ':O' . $footerStartRow)->setCellValue('N' . $footerStartRow, number_format($this->pr->total_amount, 2));

        $styleRange = 'A' . $footerStartRow . ':O' . $footerStartRow;
        $sheet->getStyle($styleRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle($styleRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9D9D9');
        $sheet->getStyle($styleRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A' . $footerStartRow . ':D' . $footerStartRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('E' . $footerStartRow . ':G' . $footerStartRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $grandTotalStyle = $sheet->getStyle('L' . $footerStartRow . ':M' . $footerStartRow);
        $grandTotalStyle->getFont()->setBold(true);
        $grandTotalStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $totalAmountStyle = $sheet->getStyle('N' . $footerStartRow . ':O' . $footerStartRow);
        $totalAmountStyle->getFont()->setBold(true);
        $totalAmountStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $remarksRow = $footerStartRow + 1;
        $sheet->mergeCells('A' . $remarksRow . ':O' . $remarksRow)->setCellValue('A' . $remarksRow, 'Remarks: ' . ($this->pr->remarks ?? ''));
        $sheet->getRowDimension($remarksRow)->setRowHeight(60);
        $styleRange = 'A' . $remarksRow . ':O' . $remarksRow;
        $sheet->getStyle($styleRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);
        $sheet->getStyle($styleRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }
    private function drawManualPageNumberFooter(Worksheet $sheet, int $startRow, int $currentPage): int
    {
        // ... (Không thay đổi)
        $sheet->mergeCells('A' . $startRow . ':O' . $startRow);
        $sheet->setCellValue('A' . $startRow, 'Page ' . $currentPage);
        $style = $sheet->getStyle('A' . $startRow . ':O' . $startRow);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $style->getFont()->setItalic(true)->setSize(9);
        return 1;
    }
}
