<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\UsersImport;
use App\Models\ApprovalRank;
use App\Models\Branch;
use App\Models\Group;
use App\Models\JobTitle;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use App\Exports\UsersExport;
use Illuminate\Support\Collection;
use ZipArchive;
use Maatwebsite\Excel\Validators\ValidationException;

class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request)
    {
        $branches = Branch::all();
        $sections = Section::all();
        $perPage = $request->input('per_page', 15);

        $query = User::query()->with([
            'mainBranch', 'sections', 'assignments.group', 'assignments.branch', 'assignments.approvalRank'
        ]);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', 'like', '%' . $request->employee_id . '%');
        }
        if ($request->filled('branch_id')) {
            $query->where('main_branch_id', $request->branch_id);
        }
        if ($request->filled('section_id')) {
            $query->whereHas('sections', function ($q) use ($request) {
                $q->where('sections.id', $request->section_id);
            });
        }

        $users = $query->latest()->paginate($perPage);
        return view('admin.users.index', compact('users', 'branches', 'sections', 'perPage'));
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', User::class);
        $query = User::query()->with([
            'mainBranch',
            'sections',
            'assignments.branch',
            'assignments.group',
            'assignments.approvalRank'
        ]);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', 'like', '%' . $request->employee_id . '%');
        }
        if ($request->filled('branch_id')) {
            $query->where('main_branch_id', $request->branch_id);
        }
        if ($request->filled('section_id')) {
            $query->whereHas('sections', function ($q) use ($request) {
                $q->where('sections.id', $request->section_id);
            });
        }

        $usersToExport = $query->latest()->get();
        $fileName = 'danh-sach-nguoi-dung-' . now()->format('Y-m-d') . '.xlsx';
        return Excel::download(new UsersExport($usersToExport), $fileName);
    }

    public function create()
    {
        $branches = Branch::all();
        $sections = Section::all();
        $groups = Group::all();
        $ranks = ApprovalRank::where('rank_level', '>', 0)->get();
        $jobTitles = JobTitle::all();
        $managers = User::whereHas('assignments.approvalRank', fn($q) => $q->where('rank_level', '>=', 2))->orderBy('name')->get();
        return view('admin.users.create', compact('branches', 'sections', 'groups', 'ranks', 'jobTitles', 'managers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'employee_id' => 'required|string|max:255|unique:users',
            'prs_id' => 'nullable|string|max:50|unique:users,prs_id',
            'job_title_id' => 'required|exists:job_titles,id',
            'main_branch_id' => 'required|exists:branches,id',
            'manager_id' => 'nullable|exists:users,id',
            'status' => 'required|boolean',
            'signature_image' => 'nullable|image|mimes:png,jpg,jpeg|max:1024',
            'sections' => 'required|array',
            'sections.*' => 'exists:sections,id',
            'assignments' => 'nullable|array',
            'assignments.*' => 'nullable|exists:approval_ranks,id',
        ]);

        DB::beginTransaction();
        try {
            $createData = $request->only(['name', 'email', 'employee_id', 'prs_id', 'main_branch_id', 'status', 'job_title_id', 'manager_id']);
            $createData['password'] = Hash::make('12345678');

            if ($request->hasFile('signature_image')) {
                $imageFile = $request->file('signature_image');
                $employeeId = $validated['employee_id'];
                $extension = $imageFile->getClientOriginalExtension();
                $newFileName = $employeeId . '.' . $extension;
                $path = $imageFile->storeAs('signatures', $newFileName, 'public');
                $createData['signature_image_path'] = $path;
            }

            $user = User::create($createData);
            $user->sections()->attach($validated['sections']);

            if (!empty($validated['assignments'])) {
                foreach ($validated['assignments'] as $group_id => $approval_rank_id) {
                    if (!empty($approval_rank_id)) {
                        $user->assignments()->create([
                            'group_id' => $group_id,
                            'branch_id' => $validated['main_branch_id'],
                            'approval_rank_id' => $approval_rank_id,
                        ]);
                    }
                }
            }

            DB::commit();
            return redirect()->route('admin.users.index')->with('success', 'Tạo người dùng thành công.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi tạo người dùng: ' . $e->getMessage());
            return back()->with('error', 'Đã xảy ra lỗi: ' . $e->getMessage())->withInput();
        }
    }

    public function show(User $user)
<<<<<<< HEAD
{
    // Tải các quan hệ cần thiết, đảm bảo gọi đúng tên 'sections' (số nhiều)
    $user->load([
        'mainBranch' => function ($query) {
            $query->whereNotNull('id');
        },
        'jobTitle' => function ($query) {
            $query->whereNotNull('id');
        },
        'sections',
        'assignments.branch',
        'assignments.group',
        'assignments.approvalRank'
    ]);

    $userAssignments = $user->assignments;
    $userGroupIds = $userAssignments->pluck('group_id')->unique()->toArray();
    $userMaxRankLevel = $userAssignments->pluck('approvalRank.rank_level')->max() ?? 0;

    // Lấy danh sách ID các phòng ban mà người dùng này thuộc về
    $userSectionIds = $user->sections->pluck('id')->toArray();

    // Kiểm tra các điều kiện cần thiết, bao gồm cả việc người dùng phải có phòng ban
    if (empty($userGroupIds) || !$user->main_branch_id || empty($userSectionIds)) {
        $purchasingSuperiors = collect([]);
        $requestingSuperiors = collect([]);
        return view('admin.users.show', compact('user', 'purchasingSuperiors', 'requestingSuperiors'));
    }

    // Truy vấn tìm cấp trên
    $superiors = User::where('id', '!=', $user->id)
        // Dùng whereHas để tìm những người dùng có chung ít nhất một phòng ban
        ->whereHas('sections', function ($query) use ($userSectionIds) {
            $query->whereIn('sections.id', $userSectionIds);
        })
        // Giữ nguyên logic tìm cấp trên dựa trên assignment
        ->whereHas('assignments', function ($query) use ($userGroupIds, $userMaxRankLevel, $user) {
            $query->whereIn('group_id', $userGroupIds)
                ->where('branch_id', $user->main_branch_id)
                ->whereHas('approvalRank', function ($subQuery) use ($userMaxRankLevel) {
                    $subQuery->where('rank_level', '>', $userMaxRankLevel);
                });
        })->with(['assignments.approvalRank', 'assignments.group'])->get();

    // Khởi tạo các collection để chứa cấp trên đã phân loại
    $requestingSuperiors = collect([]);
    $purchasingSuperiors = collect([]);

    // Lặp qua danh sách cấp trên để phân loại vào đúng nhóm
    foreach ($superiors as $superior) {
        foreach ($superior->assignments as $assignment) {
            // Điều kiện này để đảm bảo chỉ lấy đúng assignment liên quan
            if ($assignment->branch_id == $user->main_branch_id && in_array($assignment->group_id, $userGroupIds) && $assignment->approvalRank && $assignment->approvalRank->rank_level > $userMaxRankLevel) {
                $rankName = $assignment->approvalRank->name ?? 'Không xác định';

                if ($assignment->group_id == 1) { // Phòng Đề Nghị
                    if (!isset($requestingSuperiors[$rankName])) {
                        $requestingSuperiors[$rankName] = collect([]);
                    }
                    if (!$requestingSuperiors[$rankName]->contains('id', $superior->id)) {
                        $requestingSuperiors[$rankName]->push($superior);
                    }
                } elseif ($assignment->group_id == 2) { // Phòng Mua
                    if (!isset($purchasingSuperiors[$rankName])) {
                        $purchasingSuperiors[$rankName] = collect([]);
                    }
                    if (!$purchasingSuperiors[$rankName]->contains('id', $superior->id)) {
                        $purchasingSuperiors[$rankName]->push($superior);
                    }
                }
            }
        }
    }

    // Lấy thứ tự các cấp bậc để sắp xếp
    $rankOrder = \App\Models\ApprovalRank::orderBy('rank_level')->pluck('name')->flip();

    // Sắp xếp danh sách cấp trên theo rank_level
    $requestingSuperiors = $requestingSuperiors->sortBy(function ($users, $rankName) use ($rankOrder) {
        return $rankOrder[$rankName] ?? 999;
    });

    $purchasingSuperiors = $purchasingSuperiors->sortBy(function ($users, $rankName) use ($rankOrder) {
        return $rankOrder[$rankName] ?? 999;
    });

    // Trả về view với dữ liệu đã xử lý
    return view('admin.users.show', compact('user', 'purchasingSuperiors', 'requestingSuperiors'));
}
    public function edit(User $user)
{
    $user->load('sections', 'assignments'); // Chỉ cần load assignment là đủ
    $branches = Branch::all();
    $sections = Section::all();
    $groups = Group::all();
    $ranks = ApprovalRank::where('rank_level', '>', 0)->get();
    $jobTitles = JobTitle::all();

    $userSections = $user->sections->pluck('id')->toArray();

    // ✅ DÒNG ĐÃ SỬA: Tạo mảng với key là group_id và value là approval_rank_id
    $userAssignments = $user->assignments->pluck('approval_rank_id', 'group_id')->all();

    return view('admin.users.edit', compact('user', 'branches', 'sections', 'groups', 'ranks', 'userSections', 'userAssignments', 'jobTitles'));
}

    public function update(Request $request, User $user)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        'employee_id' => 'required|string|max:255|unique:users,employee_id,' . $user->id,
        'prs_id' => 'nullable|string|max:50|unique:users,prs_id,' . $user->id,
        'password' => ['nullable', 'confirmed', Rules\Password::min(8)],
        'job_title_id' => 'required|exists:job_titles,id',
        'main_branch_id' => 'required|exists:branches,id',
        'status' => 'required|boolean',
        'signature_image' => 'nullable|image|mimes:png,jpg,jpeg|max:1024',
        'sections' => 'required|array',
        'sections.*' => 'exists:sections,id',
        'assignments' => 'nullable|array', // Giữ nguyên validation
        'assignments.*' => 'nullable|exists:approval_ranks,id',
    ]);

    DB::beginTransaction();
    try {
        $updateData = $request->only(['name', 'email', 'employee_id', 'prs_id', 'main_branch_id', 'status', 'job_title_id']);
=======
    {
        $user->load('mainBranch', 'jobTitle', 'sections', 'assignments.approvalRank', 'assignments.group', 'assignments.branch');

        $userRankLevel = $user->assignments->first()->approvalRank->rank_level ?? null;
        $userSectionIds = $user->sections->pluck('id');

        $requestingSuperiors = $this->findSuperiorsInGroup($user, 'Phòng Đề Nghị', $userRankLevel, $userSectionIds);
        $purchasingSuperiors = $this->findSuperiorsInGroup($user, 'Phòng Mua', $userRankLevel, $userSectionIds);

        $rankOrder = ApprovalRank::orderBy('rank_level')->pluck('name')->flip();

        $requestingSuperiors = $requestingSuperiors->sortBy(function ($users, $rankName) use ($rankOrder) {
            return $rankOrder[$rankName] ?? 999;
        });

        $purchasingSuperiors = $purchasingSuperiors->sortBy(function ($users, $rankName) use ($rankOrder) {
            return $rankOrder[$rankName] ?? 999;
        });

        return view('admin.users.show', compact('user', 'requestingSuperiors', 'purchasingSuperiors'));
    }

    private function findSuperiorsInGroup(User $user, string $groupName, ?int $userRankLevel, Collection $userSectionIds): Collection
    {
        if (is_null($userRankLevel)) {
            return collect();
        }
        $groupId = Group::where('name', $groupName)->value('id');
        if (!$groupId) {
            return collect();
        }

        $superiorsQuery = User::where('id', '!=', $user->id)
            ->whereHas('assignments', function ($query) use ($userRankLevel, $groupId) {
                $query->where('group_id', $groupId)
                      ->whereHas('approvalRank', function ($q) use ($userRankLevel) {
                          $q->where('rank_level', '>', $userRankLevel);
                      });
            });

        if ($userSectionIds->isNotEmpty()) {
            $superiorsQuery->whereHas('sections', function ($q) use ($userSectionIds) {
                $q->whereIn('sections.id', $userSectionIds);
            });
        } else {
            return collect();
        }

        return $superiorsQuery->with('assignments.approvalRank')->get()->flatMap(function ($superior) use ($groupId) {
            return $superior->assignments->where('group_id', $groupId)->map(function ($assignment) use ($superior) {
                return [
                    'rank_name' => $assignment->approvalRank->name,
                    'user' => $superior
                ];
            });
        })->groupBy('rank_name')->map(fn($group) => $group->pluck('user')->unique('id'));
    }

    public function edit(User $user)
    {
        $user->load('sections', 'assignments');
        $branches = Branch::all();
        $sections = Section::all();
        $groups = Group::all();
        $ranks = ApprovalRank::where('rank_level', '>', 0)->get();
        $jobTitles = JobTitle::all();
        $managers = User::where('id', '!=', $user->id)->whereHas('assignments.approvalRank', fn($q) => $q->where('rank_level', '>=', 2))->orderBy('name')->get();
        $userSections = $user->sections->pluck('id')->toArray();
        $userAssignments = $user->assignments->pluck('approval_rank_id', 'group_id')->all();
        return view('admin.users.edit', compact('user', 'branches', 'sections', 'groups', 'ranks', 'userSections', 'userAssignments', 'jobTitles', 'managers'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'employee_id' => 'required|string|max:255|unique:users,employee_id,' . $user->id,
            'prs_id' => 'nullable|string|max:50|unique:users,prs_id,' . $user->id,
            'password' => ['nullable', 'confirmed', Rules\Password::min(8)],
            'job_title_id' => 'required|exists:job_titles,id',
            'main_branch_id' => 'required|exists:branches,id',
            'manager_id' => 'nullable|exists:users,id',
            'status' => 'required|boolean',
            'signature_image' => 'nullable|image|mimes:png,jpg,jpeg|max:1024',
            'sections' => 'required|array',
            'sections.*' => 'exists:sections,id',
            'assignments' => 'nullable|array',
            'assignments.*' => 'nullable|exists:approval_ranks,id',
        ]);

        DB::beginTransaction();
        try {
            $updateData = $request->only(['name', 'email', 'employee_id', 'prs_id', 'main_branch_id', 'status', 'job_title_id', 'manager_id']);
>>>>>>> 008a4b41ca5eda2e1bb01a13d8f90c7b4f76a3ab

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('signature_image')) {
            if ($user->signature_image_path) {
                Storage::disk('public')->delete($user->signature_image_path);
            }
            $imageFile = $request->file('signature_image');
            $employeeId = $validated['employee_id'];
            $extension = $imageFile->getClientOriginalExtension();
            $newFileName = $employeeId . '.' . $extension;
            $path = $imageFile->storeAs('signatures', $newFileName, 'public');
            $updateData['signature_image_path'] = $path;
        }

<<<<<<< HEAD
        $user->update($updateData);
        $user->sections()->sync($validated['sections']);

        // ✅ BỌC TOÀN BỘ LOGIC XỬ LÝ ASSIGNMENT TRONG LỆNH IF NÀY
        // Chỉ cập nhật assignments nếu trường này được gửi đi từ form
        if ($request->has('assignments')) {
            $user->assignments()->delete(); // Xóa các phân quyền cũ

            if (!empty($validated['assignments'])) {
                foreach ($validated['assignments'] as $group_id => $approval_rank_id) {
                    if (!empty($approval_rank_id)) {
                        $user->assignments()->create([
                            'group_id' => $group_id,
                            'branch_id' => $validated['main_branch_id'],
                            'approval_rank_id' => $approval_rank_id,
                        ]);
                    }
                }
            }
=======
            if ($request->hasFile('signature_image')) {
                if ($user->signature_image_path) {
                    Storage::disk('public')->delete($user->signature_image_path);
                }
                $imageFile = $request->file('signature_image');
                $employeeId = $validated['employee_id'];
                $extension = $imageFile->getClientOriginalExtension();
                $newFileName = $employeeId . '.' . $extension;
                $path = $imageFile->storeAs('signatures', $newFileName, 'public');
                $updateData['signature_image_path'] = $path;
            }

            $user->update($updateData);
            $user->sections()->sync($validated['sections']);

            if ($request->has('assignments')) {
                $user->assignments()->delete();
                if (!empty($validated['assignments'])) {
                    foreach ($validated['assignments'] as $group_id => $approval_rank_id) {
                        if (!empty($approval_rank_id)) {
                            $user->assignments()->create([
                                'group_id' => $group_id,
                                'branch_id' => $validated['main_branch_id'],
                                'approval_rank_id' => $approval_rank_id,
                            ]);
                        }
                    }
                }
            }

            DB::commit();
            return redirect()->route('admin.users.index')->with('success', 'Cập nhật người dùng thành công.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi cập nhật người dùng: ' . $e->getMessage());
            return back()->with('error', 'Đã xảy ra lỗi: ' . $e->getMessage())->withInput();
>>>>>>> 008a4b41ca5eda2e1bb01a13d8f90c7b4f76a3ab
        }

        DB::commit();
        return redirect()->route('admin.users.index')->with('success', 'Cập nhật người dùng thành công.');
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Lỗi cập nhật người dùng: ' . $e->getMessage());
        return back()->with('error', 'Đã xảy ra lỗi: ' . $e->getMessage())->withInput();
    }
<<<<<<< HEAD
}
=======

>>>>>>> 008a4b41ca5eda2e1bb01a13d8f90c7b4f76a3ab
    public function destroy(User $user)
    {
        DB::beginTransaction();
        try {
            if ($user->signature_image_path) {
                Storage::disk('public')->delete($user->signature_image_path);
            }
            $user->delete();
            DB::commit();
            return redirect()->route('admin.users.index')->with('success', 'Xóa người dùng thành công.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi khi xóa người dùng: ' . $e->getMessage());
            return redirect()->route('admin.users.index')->with('error', 'Không thể xóa người dùng này vì có ràng buộc dữ liệu quan trọng.');
        }
    }

    public function showImportForm()
    {
        $this->authorize('create', User::class);
        return view('admin.users.import');
    }

    public function handleImport(Request $request)
    {
        $this->authorize('create', User::class);
        $request->validate(['import_file' => 'required|file|mimes:zip']);

        $file = $request->file('import_file');
        $zip = new ZipArchive;
        $tempPath = storage_path('app/temp/import_' . time());

        if ($zip->open($file->getRealPath()) === TRUE) {
            File::makeDirectory($tempPath, 0755, true, true);
            $zip->extractTo($tempPath);
            $zip->close();

            $filesInZip = File::allFiles($tempPath);
            $excelFile = null;
            $signaturesPath = $tempPath . '/signatures';

            foreach ($filesInZip as $fileInZip) {
                if (in_array($fileInZip->getExtension(), ['xlsx', 'xls', 'csv'])) {
                    $excelFile = $fileInZip->getRealPath();
                    break;
                }
            }

            if (!$excelFile) {
                File::deleteDirectory($tempPath);
                return back()->with('error', 'Không tìm thấy file Excel (.xlsx, .xls, .csv) trong file ZIP.');
            }

            try {
                Excel::import(new UsersImport($signaturesPath), $excelFile);
            } catch (ValidationException $e) {
                File::deleteDirectory($tempPath);
                $failures = $e->failures();
                $errorMsg = 'Lỗi dữ liệu trong file Excel: <br>';
                foreach ($failures as $failure) {
                    $errorMsg .= '- Dòng ' . $failure->row() . ': ' . implode(', ', $failure->errors()) . '<br>';
                }
                return back()->with('error', $errorMsg);
            } catch (\Exception $e) {
                File::deleteDirectory($tempPath);
                return back()->with('error', 'Đã xảy ra lỗi trong quá trình import: ' . $e->getMessage());
            }

            File::deleteDirectory($tempPath);
            return back()->with('success', 'Import danh sách người dùng thành công!');
        } else {
            return back()->with('error', 'Không thể mở file ZIP.');
        }
    }

    public function toggleStatus(User $user)
    {
        $this->authorize('update', $user);
        try {
            $user->status = !$user->status;
            $user->save();
            $message = $user->status ? 'Kích hoạt tài khoản thành công.' : 'Vô hiệu hóa tài khoản thành công.';
            return redirect()->route('admin.users.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Lỗi khi thay đổi trạng thái người dùng: ' . $e->getMessage());
            return back()->with('error', 'Đã xảy ra lỗi khi thay đổi trạng thái người dùng.');
        }
    }
}
