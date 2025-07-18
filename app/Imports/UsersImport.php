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
                    return;
                }

                $mainBranchId = $branches[trim($row['chi_nhanh_chinh'])] ?? null;
                if (!$mainBranchId) {
                    return;
                }

                $jobTitleId = $jobTitles[trim($row['phan_quyen_tren_sap'])] ?? null;

                // ✅ SỬA ĐỔI: Logic xử lý ảnh chữ ký để giữ nguyên tên file
                $signatureDbPath = null;
                if ($this->signaturesPath && !empty($employeeId)) {
                    // Tìm file ảnh không phân biệt extension (png, jpg, jpeg)
                    $signatureFiles = File::glob($this->signaturesPath . '/' . $employeeId . '.*');

                    if (!empty($signatureFiles)) {
                        $imageFile = new IlluminateFile($signatureFiles[0]);

                        // Lấy tên file gốc (ví dụ: M012345.png)
                        $newFileName = $imageFile->getBasename();

                        // Sử dụng putFileAs để lưu với tên file tùy chỉnh
                        $path = Storage::disk('public')->putFileAs(
                            'signatures', // Thư mục lưu
                            $imageFile,   // File cần lưu
                            $newFileName  // Tên file mới
                        );

                        $signatureDbPath = $path;
                    }
                }

                // Chuẩn bị dữ liệu để cập nhật hoặc tạo mới
                $userData = [
                    'name' => trim($row['last_name'] ?? '') . ' ' . trim($row['frist_name'] ?? ''),
                    'email' => trim($row['e_mail_address']),
                    'prs_id' => $prsId,
                    'job_title_id' => $jobTitleId,
                    'main_branch_id' => $mainBranchId,
                    'status' => true,
                ];
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
                    $userData['employee_id'] = $employeeId;
                    $user->update($userData);
                } else {
                    $userData['employee_id'] = $employeeId;
                    $userData['password'] = Hash::make('12345678');
                    $user = User::create($userData);
                }

                // Gán phòng ban
                if (!empty($row['department'])) {
                    $sectionCodes = explode(',', $row['department']);
                    $sectionIds = collect($sectionCodes)->map(fn($code) => $sections[trim($code)] ?? null)->filter();
                    $user->sections()->sync($sectionIds);
                }

                // Gán quyền hạn
                $user->assignments()->delete();
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
