<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// ✅ SỬA ĐỔI: Implement thêm các interface mới
class UsersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected $users;

    public function __construct($users)
    {
        $this->users = $users;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        // ✅ SỬA ĐỔI: Dữ liệu đã được eager-load từ Controller, chỉ cần trả về collection.
        // Việc này giúp tránh lỗi "Call to a member function load() on array".
        return $this->users;
    }

    /**
     * Định nghĩa các cột tiêu đề cho file Excel
     */
    public function headings(): array
    {
        return [
            'ID',
            'Tên',
            'Email',
            'Mã nhân viên',
            'PRS ID',
            'Số điện thoại',
            'Chi nhánh chính',
            'Trạng thái',
            'Phòng ban',
            'Quyền hạn được gán', // ✅ THÊM MỚI: Cột quyền hạn
            'Ngày tạo',
        ];
    }

    /**
     * Ánh xạ dữ liệu từ mỗi user vào các cột tương ứng
     * @param mixed $user
     * @return array
     */
    public function map($user): array
    {
        // ✅ THÊM MỚI: Xử lý để hiển thị danh sách quyền hạn
        $assignmentsText = $user->assignments->map(function ($assignment) {
            $group = $assignment->group->name ?? 'N/A';
            $branch = $assignment->branch->name ?? 'N/A';
            $rank = $assignment->approvalRank->name ?? 'Không có';
            return "{$group}: {$branch} (Cấp: {$rank})";
        })->implode("\n"); // Dùng ký tự xuống dòng để Wrap Text hoạt động

        return [
            $user->id,
            $user->name,
            $user->email,
            $user->employee_id,
            $user->prs_id,
            $user->phone,
            $user->mainBranch->name ?? '',
            $user->status ? 'Hoạt động' : 'Khóa',
            $user->sections->pluck('name')->implode(', '),
            $assignmentsText, // Thêm dữ liệu quyền hạn vào cột mới
            $user->created_at->format('d/m/Y H:i:s'),
        ];
    }

    /**
     * ✅ THÊM MỚI: Định nghĩa chiều rộng cho các cột
     */
    public function columnWidths(): array
    {
        return [
            'A' => 5,   // ID
            'B' => 25,  // Tên
            'C' => 30,  // Email
            'D' => 15,  // Mã NV
            'E' => 15,  // PRS ID
            'F' => 15,  // SĐT
            'G' => 20,  // Chi nhánh
            'H' => 12,  // Trạng thái
            'I' => 30,  // Phòng ban
            'J' => 45,  // Quyền hạn (cột mới, rộng hơn)
            'K' => 20,  // Ngày tạo
        ];
    }

    /**
     * Áp dụng style cho dòng tiêu đề
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Áp dụng style cho dòng đầu tiên (dòng tiêu đề)
            1    => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFFFF00'], // Mã màu ARGB cho màu vàng
                ]
            ],
        ];
    }

    /**
     * ✅ THÊM MỚI: Đăng ký sự kiện để áp dụng style chung (căn giữa, wrap text)
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Lấy toàn bộ vùng dữ liệu của sheet
                $cellRange = 'A1:' . $event->sheet->getDelegate()->getHighestColumn() . $event->sheet->getDelegate()->getHighestRow();

                // Áp dụng style căn giữa và wrap text cho toàn bộ sheet
                $event->sheet->getDelegate()->getStyle($cellRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle($cellRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getDelegate()->getStyle($cellRange)->getAlignment()->setWrapText(true);
            },
        ];
    }
}
