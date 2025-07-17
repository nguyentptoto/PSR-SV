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


use ZipArchive;
use Maatwebsite\Excel\Validators\ValidationException;

class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    // ---- CÁC HÀM XỬ LÝ CRUD ----

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
        return view('admin.users.create', compact('branches', 'sections', 'groups', 'ranks', 'jobTitles'));
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
        'status' => 'required|boolean',
        'signature_image' => 'nullable|image|mimes:png,jpg,jpeg|max:1024',
        'sections' => 'required|array',
        'sections.*' => 'exists:sections,id',
        'assignments' => 'nullable|array',
        'assignments.*' => 'nullable|exists:approval_ranks,id', // Xác thực approval_rank_id
    ]);

    DB::beginTransaction();
    try {
        $createData = $request->only(['name', 'email', 'employee_id', 'prs_id', 'main_branch_id', 'status', 'job_title_id']);
        $createData['password'] = Hash::make('12345678');

        if ($request->hasFile('signature_image')) {
            $path = $request->file('signature_image')->store('signatures', 'public');
            $createData['signature_image_path'] = $path;
        }

        $user = User::create($createData);
        $user->sections()->attach($validated['sections']);

        // Xử lý assignments
        if (!empty($validated['assignments'])) {
            foreach ($validated['assignments'] as $group_id => $approval_rank_id) {
                if (!empty($approval_rank_id)) {
                    \Log::info('Creating assignment:', [
                        'group_id' => $group_id,
                        'branch_id' => $validated['main_branch_id'], // Sử dụng main_branch_id
                        'approval_rank_id' => $approval_rank_id,
                    ]);
                    $user->assignments()->create([
                        'group_id' => $group_id,
                        'branch_id' => $validated['main_branch_id'], // Gán branch_id từ main_branch_id
                        'approval_rank_id' => $approval_rank_id,
                    ]);
                }
            }
        }

        DB::commit();
        return redirect()->route('admin.users.index')->with('success', 'Tạo người dùng thành công.');
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Lỗi tạo người dùng: ' . $e->getMessage());
        return back()->with('error', 'Đã xảy ra lỗi: ' . $e->getMessage())->withInput();
    }
}

public function show(User $user)
{
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

    // Lấy danh sách group_id và rank_level cao nhất của người dùng hiện tại
    $userGroupIds = $user->assignments->pluck('group_id')->unique()->toArray();
    $userMaxRankLevel = $user->assignments->pluck('approvalRank.rank_level')->max() ?? 0;

    // Nếu không có group_id, trả về collection rỗng
    if (empty($userGroupIds)) {
        $purchasingSuperiors = collect([]);
        $requestingSuperiors = collect([]);
        \Log::info('No group IDs for user:', ['user_id' => $user->id]);
        return view('admin.users.show', compact('user', 'purchasingSuperiors', 'requestingSuperiors'));
    }

    // Lấy danh sách cấp trên có cùng group_id và rank_level > userMaxRankLevel
    $superiors = User::where('id', '!=', $user->id) // Loại trừ người dùng hiện tại
        ->whereHas('assignments', function ($query) use ($userGroupIds, $userMaxRankLevel) {
            $query->whereIn('group_id', $userGroupIds)
                  ->whereHas('approvalRank', function ($subQuery) use ($userMaxRankLevel) {
                      $subQuery->where('rank_level', '>', $userMaxRankLevel);
                  });
        })->with(['assignments.approvalRank', 'assignments.group'])->get();

    // Phân loại cấp trên theo group_id và nhóm theo tên cấp bậc
    $requestingSuperiors = collect([]); // Phòng Đề Nghị (group_id = 1)
    $purchasingSuperiors = collect([]); // Phòng Mua (group_id = 2)

    foreach ($superiors as $superior) {
        foreach ($superior->assignments as $assignment) {
            if (in_array($assignment->group_id, $userGroupIds) && $assignment->approvalRank && $assignment->approvalRank->rank_level > $userMaxRankLevel) {
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

    // Debug dữ liệu
    \Log::info('User Group IDs:', $userGroupIds);
    \Log::info('User Max Rank Level:', [$userMaxRankLevel]);
    \Log::info('Superiors:', $superiors->toArray());
    \Log::info('Requesting Superiors:', $requestingSuperiors->toArray());
    \Log::info('Purchasing Superiors:', $purchasingSuperiors->toArray());

    return view('admin.users.show', compact('user', 'purchasingSuperiors', 'requestingSuperiors'));
}
    public function edit(User $user)
    {
        $user->load('sections', 'assignments.approvalRank');
        $branches = Branch::all();
        $sections = Section::all();
        $groups = Group::all();
        $ranks = ApprovalRank::where('rank_level', '>', 0)->get();
        $jobTitles = JobTitle::all();

        $userSections = $user->sections->pluck('id')->toArray();
        $userAssignments = $user->assignments->keyBy(function ($item) {
            return $item->group_id . '-' . $item->branch_id;
        });

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
        'assignments' => 'nullable|array',
        'assignments.*' => 'nullable|exists:approval_ranks,id', // Xác thực approval_rank_id
    ]);

    DB::beginTransaction();
    try {
        $updateData = $request->only(['name', 'email', 'employee_id', 'prs_id', 'main_branch_id', 'status', 'job_title_id']);

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('signature_image')) {
            if ($user->signature_image_path) {
                Storage::disk('public')->delete($user->signature_image_path);
            }
            $path = $request->file('signature_image')->store('signatures', 'public');
            $updateData['signature_image_path'] = $path;
        }

        $user->update($updateData);
        $user->sections()->sync($validated['sections']);

        // Xóa assignments cũ
        $user->assignments()->delete();

        // Tạo assignments mới
        if (!empty($validated['assignments'])) {
            foreach ($validated['assignments'] as $group_id => $approval_rank_id) {
                if (!empty($approval_rank_id)) {
                    \Log::info('Creating assignment:', [
                        'group_id' => $group_id,
                        'branch_id' => $validated['main_branch_id'], // Sử dụng main_branch_id
                        'approval_rank_id' => $approval_rank_id,
                    ]);
                    $user->assignments()->create([
                        'group_id' => $group_id,
                        'branch_id' => $validated['main_branch_id'], // Gán branch_id từ main_branch_id
                        'approval_rank_id' => $approval_rank_id,
                    ]);
                }
            }
        }

        DB::commit();
        return redirect()->route('admin.users.index')->with('success', 'Cập nhật người dùng thành công.');
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Lỗi cập nhật người dùng: ' . $e->getMessage());
        return back()->with('error', 'Đã xảy ra lỗi: ' . $e->getMessage())->withInput();
    }
}
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

    // ---- CÁC HÀM XỬ LÝ IMPORT ----
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
}
