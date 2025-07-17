<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Branch;
use App\Models\Section;
use App\Models\Group;
use App\Models\ApprovalRank;
use App\Models\Assignment;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\JobTitle; // <--- Đảm bảo Model này đã được import và đúng tên

class UsersImport implements ToCollection, WithHeadingRow
{
    private $signaturesPath;

    public function __construct(string $signaturesPath = null)
    {
        $this->signaturesPath = $signaturesPath;
    }

    public function collection(Collection $rows)
    {
        $branches = Branch::pluck('id', 'name');
        $sections = Section::pluck('id', 'code'); // Sử dụng mã phòng ban
        $groups = Group::pluck('id', 'name');
        $ranks = ApprovalRank::pluck('id', 'rank_level');

        // Lấy tất cả JobTitles và ánh xạ theo tên để dễ dàng tìm kiếm ID
        // Đây là bước quan trọng để chuyển đổi tên chức danh từ Excel sang ID
        $jobTitles = JobTitle::pluck('id', 'name'); // Lấy ID theo tên chức danh

        foreach ($rows as $row) {
            DB::transaction(function () use ($row, $branches, $sections, $groups, $ranks, $jobTitles) { // Thêm $jobTitles vào use
                // Bỏ qua hàng nếu không có 'toto_id'
                if (empty($row['toto_id'])) {
                    return;
                }

                $mainBranchId = $branches[trim($row['chi_nhanh_chinh'])] ?? null;
                // Nếu chi nhánh chính không tìm thấy, bỏ qua hàng này
                if (!$mainBranchId) {
                    // Log hoặc thông báo lỗi nếu cần thiết
                    // \Log::warning("Branch '{$row['chi_nhanh_chinh']}' not found for user '{$row['toto_id']}'. Skipping row.");
                    return;
                }

                $signatureDbPath = null;
                if ($this->signaturesPath && !empty($row['chu_ky']) && File::exists($this->signaturesPath . '/' . $row['chu_ky'])) {
                    $newPath = 'signatures/' . uniqid() . '_' . $row['chu_ky'];
                    Storage::disk('public')->put($newPath, File::get($this->signaturesPath . '/' . $row['chu_ky']));
                    $signatureDbPath = $newPath;
                }

                // --- Bắt đầu phần chỉnh sửa cho job_title_id ---
                $jobTitleId = null; // Khởi tạo ID chức danh là null

                // Lấy tên chức danh từ cột Excel 'phan_quyen_tren_sap'
                // Đảm bảo tên cột trong Excel là 'phan_quyen_tren_sap' và chứa giá trị như 'MG', 'AGM', 'WK', v.v.
                $jobTitleNameFromExcel = trim($row['phan_quyen_tren_sap']) ?? null;

                if ($jobTitleNameFromExcel) {
                    // Tìm ID chức danh trong mảng $jobTitles đã được load sẵn
                    $jobTitleId = $jobTitles[$jobTitleNameFromExcel] ?? null;

                    if (!$jobTitleId) {
                        // Xử lý trường hợp không tìm thấy ID chức danh cho tên này
                        // Tùy chọn: Log lỗi, gán ID mặc định, hoặc bỏ qua hàng
                        \Log::warning("Job title '{$jobTitleNameFromExcel}' not found for user '{$row['toto_id']}'. Setting job_title_id to null.");
                        // Nếu job_title_id là NOT NULL trong DB, bạn cần xử lý khác (ví dụ: gán một ID mặc định)
                        // $jobTitleId = JobTitle::where('name', 'Default Job')->value('id');
                    }
                }
                // --- Kết thúc phần chỉnh sửa cho job_title_id ---

                $user = User::updateOrCreate(
                    ['employee_id' => $row['toto_id']],
                    [
                        'name' => ($row['last_name'] ?? '') . ' ' . ($row['frist_name'] ?? ''),
                        'email' => $row['e_mail_address'],
                        'prs_id' => $row['sap_user_id'],
                        'job_title_id' => $jobTitleId, // Sử dụng ID đã tìm được
                        'password' => Hash::make('12345678'), // Mật khẩu mặc định
                        'main_branch_id' => $mainBranchId,
                        'status' => true, // Giả sử trạng thái mặc định là true
                        'signature_image_path' => $signatureDbPath
                    ]
                );

                if (!empty($row['department'])) {
                    $sectionCodes = explode(',', $row['department']);
                    // Thay thế Arrow Function `fn($code) => ...` bằng Anonymous Function cho PHP 7.4
                    $sectionIds = collect($sectionCodes)->map(function ($code) use ($sections) {
                        return $sections[trim($code)] ?? null;
                    })->filter();
                    $user->sections()->sync($sectionIds);
                }

                $user->assignments()->delete();
                if (!empty($row['phan_cap']) && !empty($row['group'])) {
                    $assignmentGroupId = $groups[trim($row['group'])] ?? null;
                    $assignmentRankId = $ranks[$row['phan_cap']] ?? null;
                    if ($assignmentGroupId && $assignmentRankId) {
                        Assignment::create([
                            'user_id' => $user->id,
                            'branch_id' => $mainBranchId,
                            'approval_rank_id' => $assignmentRankId,
                            'group_id' => $assignmentGroupId,
                        ]);
                    }
                }
            });
        }
    }
}
