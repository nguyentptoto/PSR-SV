<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Branch;
use App\Models\Section;
use App\Models\Group;
use App\Models\ApprovalRank;
use App\Models\JobTitle;
use App\Models\Assignment;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Http\File as IlluminateFile;

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
        $sections = Section::pluck('id', 'code');
        $groups = Group::pluck('id', 'name');
        $ranks = ApprovalRank::pluck('id', 'rank_level');
        $jobTitles = JobTitle::pluck('id', 'name');

        foreach ($rows as $row) {
            DB::transaction(function () use ($row, $branches, $sections, $groups, $ranks, $jobTitles) {
                $employeeId = trim($row['toto_id']);
                $prsId = trim($row['sap_user_id']);

                if (empty($employeeId)) {
                    return; // Bỏ qua nếu không có employeeId
                }

                $mainBranchId = $branches[trim($row['chi_nhanh_chinh'])] ?? null;
                if (!$mainBranchId) {
                    // Bạn có thể log lỗi ở đây nếu muốn
                    return; // Bỏ qua nếu không tìm thấy chi nhánh
                }

                $jobTitleId = $jobTitles[trim($row['phan_quyen_tren_sap'])] ?? null;

                // ✅ PHẦN SỬA ĐỔI: Xử lý ảnh chữ ký để giữ nguyên tên file gốc
                $signatureDbPath = null;
                if ($this->signaturesPath && !empty($employeeId)) {
                    // Tìm file ảnh có tên là employeeId và phần mở rộng bất kỳ (png, jpg, jpeg...)
                    $signatureFiles = File::glob($this->signaturesPath . '/' . $employeeId . '.*');

                    if (!empty($signatureFiles)) {
                        // Lấy file đầu tiên tìm được
                        $originalFile = new IlluminateFile($signatureFiles[0]);

                        // Lấy tên file gốc bao gồm cả phần mở rộng (ví dụ: 'M012344.png')
                        $originalFileName = $originalFile->getBasename();

                        // Sử dụng putFileAs để lưu file với tên gốc vào thư mục 'signatures' trên disk 'public'
                        // Hàm này sẽ trả về đường dẫn tương đối, ví dụ: 'signatures/M012344.png'
                        $path = Storage::disk('public')->putFileAs(
                            'signatures',     // Thư mục lưu trữ
                            $originalFile,    // File object cần lưu
                            $originalFileName // Tên file mong muốn
                        );

                        $signatureDbPath = $path;
                    }
                }

                // Chuẩn bị dữ liệu để cập nhật hoặc tạo mới
                $userData = [
                    'name' => trim($row['full_name'] ?? ''),
                    'email' => trim($row['e_mail_address']),
                    'prs_id' => $prsId,
                    'job_title_id' => $jobTitleId,
                    'main_branch_id' => $mainBranchId,
                    'status' => true,
                ];

                // Chỉ thêm đường dẫn ảnh vào dữ liệu nếu upload thành công
                if ($signatureDbPath) {
                    $userData['signature_image_path'] = $signatureDbPath;
                }

                // Tìm kiếm và cập nhật/tạo mới user
                $user = User::where('employee_id', $employeeId)
                            ->orWhere(function($query) use ($prsId) {
                                if (!empty($prsId)) {
                                    $query->where('prs_id', $prsId);
                                }
                            })->first();

                if ($user) {
                    // Nếu tìm thấy user, cập nhật thông tin
                    $userData['employee_id'] = $employeeId; // Đảm bảo employee_id không bị ghi đè nếu user được tìm bằng prs_id
                    $user->update($userData);
                } else {
                    // Nếu không tìm thấy, tạo user mới
                    $userData['employee_id'] = $employeeId;
                    $userData['password'] = Hash::make('12345678'); // Mật khẩu mặc định
                    $user = User::create($userData);
                }

                // Gán phòng ban (sections)
                if (!empty($row['department'])) {
                    $sectionCodes = explode(',', $row['department']);
                    $sectionIds = collect($sectionCodes)->map(fn($code) => $sections[trim($code)] ?? null)->filter();
                    $user->sections()->sync($sectionIds);
                }

                // Gán quyền hạn (assignments)
                $user->assignments()->delete(); // Xóa các assignment cũ trước khi gán mới
                if (!empty($row['phan_cap']) && !empty($row['group'])) {
                     $assignmentGroupId = $groups[trim($row['group'])] ?? null;
                     $assignmentRankId = $ranks[$row['phan_cap']] ?? null;
                     if($assignmentGroupId && $assignmentRankId) {
                         $allBranches = Branch::all();
                         foreach($allBranches as $branch) {
                             Assignment::create([
                                 'user_id' => $user->id,
                                 'branch_id' => $branch->id,
                                 'approval_rank_id' => $assignmentRankId,
                                 'group_id' => $assignmentGroupId,
                             ]);
                         }
                     }
                }
            });
        }
    }
}
