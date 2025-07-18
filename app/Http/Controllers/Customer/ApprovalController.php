<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\ApprovalHistory;
use App\Models\Group;
use App\Models\ApprovalRank;
use App\Models\User;
use App\Models\Section;
use App\Jobs\SendApprovalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ApprovalController extends Controller
{
    /**
     * Hiển thị danh sách các phiếu đang chờ người dùng hiện tại duyệt.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $user->load('assignments.approvalRank', 'assignments.group', 'sections');

        $sections = Section::orderBy('name')->get();
        $requesters = User::whereIn('id', PurchaseRequest::select('requester_id')->distinct())->orderBy('name')->get();

        $assignments = $user->assignments;
        $userSectionIds = $user->sections->pluck('id');

        $requestingGroupId = Group::where('name', 'Phòng Đề Nghị')->value('id');
        $purchasingGroupId = Group::where('name', 'Phòng Mua')->value('id');

        $query = PurchaseRequest::query()->with(['requester', 'branch', 'section', 'approvalHistories']);

        // Chỉ lấy các phiếu đang trong quá trình duyệt
        $query->whereIn('status', ['pending_approval', 'purchasing_approval']);

        // Áp dụng bộ lọc từ form
        if ($request->filled('pia_code')) {
            $query->where('pia_code', 'like', '%' . $request->pia_code . '%');
        }
        if ($request->filled('requester_id')) {
            $query->where('requester_id', $request->requester_id);
        }
        if ($request->filled('section_id')) {
            $query->where('section_id', $request->section_id);
        }

        // Logic lọc quyền hạn để chỉ lấy phiếu đang chờ duyệt
        $query->where(function ($q) use ($user, $userSectionIds, $assignments, $requestingGroupId, $purchasingGroupId) {
            if ($assignments->isEmpty()) {
                $q->whereRaw('1 = 0'); // Không có quyền, không thấy gì
                return;
            }
            foreach ($assignments as $assignment) {
                $q->orWhere(function ($subQ) use ($userSectionIds, $assignment, $requestingGroupId, $purchasingGroupId) {
                    $rankLevel = $assignment->approvalRank->rank_level;

                    // Nếu là Cấp 4, chỉ lấy những phiếu có tích chọn
                    if ($rankLevel == 4) {
                        $subQ->where('requires_director_approval', true);
                    }

                    $subQ->where('current_rank_level', $rankLevel)
                        ->where('branch_id', $assignment->branch_id);

                    if ($assignment->group_id == $requestingGroupId) {
                        $subQ->where('status', 'pending_approval');
                        // Các cấp dưới Giám đốc phải chung phòng ban
                        if ($rankLevel < 4) {
                            $subQ->whereIn('section_id', $userSectionIds);
                        }
                    } elseif ($assignment->group_id == $purchasingGroupId) {
                        $subQ->where('status', 'purchasing_approval');
                    } else {
                        $subQ->whereRaw('1 = 0');
                    }
                });
            }
        });

        $pendingRequests = $query->distinct()->latest()->paginate(15)->withQueryString();

        return view('users.approvals.index', compact('pendingRequests', 'sections', 'requesters'));
    }

    /**
     * Hiển thị lịch sử các phiếu đã được người dùng duyệt.
     */
    public function history(Request $request)
    {
        $user = Auth::user();
        $sections = Section::orderBy('name')->get();
        $requesters = User::whereIn('id', PurchaseRequest::select('requester_id')->distinct())->orderBy('name')->get();

        $query = PurchaseRequest::query()
            ->with(['requester', 'branch', 'section'])
            ->where('status', '!=', 'rejected') // Không hiển thị phiếu bị từ chối
            ->whereHas('approvalHistories', function ($historyQuery) use ($user) {
                $historyQuery->where('user_id', $user->id)->where('action', 'approved');
            });

        // Áp dụng bộ lọc
        if ($request->filled('pia_code')) {
            $query->where('pia_code', 'like', '%' . $request->pia_code . '%');
        }
        if ($request->filled('requester_id')) {
            $query->where('requester_id', $request->requester_id);
        }
        if ($request->filled('section_id')) {
            $query->where('section_id', $request->section_id);
        }

        $approvedRequests = $query->distinct()->latest()->paginate(15)->withQueryString();

        return view('users.approvals.history', compact('approvedRequests', 'sections', 'requesters'));
    }

    /**
     * Phê duyệt một phiếu đề nghị.
     */
    public function approve(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorize('approve', $purchaseRequest);

        DB::beginTransaction();
        try {
            $user = Auth::user();
            $currentRankLevel = $purchaseRequest->current_rank_level;
            $isRequestingGroup = $purchaseRequest->status === 'pending_approval';

            // Xác định tên vai trò chi tiết để lưu vào lịch sử
            $rankName = 'Cấp ' . $currentRankLevel; // Mặc định
            if ($isRequestingGroup) {
                if ($currentRankLevel == 2)
                    $rankName = 'Manager (Requesting)';
                if ($currentRankLevel == 3)
                    $rankName = 'General Manager';
                if ($currentRankLevel == 4)
                    $rankName = 'Director (Requesting)';
            } else {
                if ($currentRankLevel == 2)
                    $rankName = 'Manager (Purchasing)';
                if ($currentRankLevel == 4)
                    $rankName = 'Director (Purchasing)';
            }

            // Ghi lại lịch sử hành động duyệt
            ApprovalHistory::create([
                'purchase_request_id' => $purchaseRequest->id,
                'user_id' => $user->id,
                'rank_at_approval' => $rankName,
                'action' => 'approved',
                'signature_image_path' => $user->signature_image_path ?? 'no-signature.png',
                'comment' => $request->input('comment', 'Đã phê duyệt'),
            ]);

            // Logic chuyển cấp duyệt
            $nextRank = ApprovalRank::where('rank_level', '>', $currentRankLevel)->orderBy('rank_level', 'asc')->first();

            if ($isRequestingGroup) {
                $isFinalLevel = $purchaseRequest->requires_director_approval ? ($currentRankLevel >= 4) : ($currentRankLevel >= 3);
                if ($isFinalLevel || !$nextRank) {
                    $purchaseRequest->status = 'purchasing_approval';
                    $firstPurchaseRank = ApprovalRank::where('rank_level', 2)->first();
                    $purchaseRequest->current_rank_level = $firstPurchaseRank ? $firstPurchaseRank->rank_level : 999;
                } else {
                    $purchaseRequest->current_rank_level = $nextRank->rank_level;
                }
            } else {
                if ($nextRank && $nextRank->rank_level <= 4) {
                    $purchaseRequest->current_rank_level = $nextRank->rank_level;
                } else {
                    $purchaseRequest->status = 'completed';
                }
            }

            $purchaseRequest->save();
            DB::commit();

            // Tìm người duyệt tiếp theo và gửi mail
            $nextApprovers = $this->findNextApprovers($purchaseRequest);
            if ($nextApprovers->isNotEmpty()) {
                SendApprovalNotification::dispatch($purchaseRequest, $nextApprovers);
            }

            return redirect()->route('users.approvals.index')->with('success', 'Phê duyệt phiếu thành công.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }

    /**
     * Từ chối một phiếu đề nghị.
     */
    public function reject(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorize('approve', $purchaseRequest);
        $request->validate(['comment' => 'required|string|max:500']);

        DB::beginTransaction();
        try {
            $purchaseRequest->status = 'rejected';
            $purchaseRequest->save();

            ApprovalHistory::create([
                'purchase_request_id' => $purchaseRequest->id,
                'user_id' => Auth::id(),
                'rank_at_approval' => 'Cấp ' . $purchaseRequest->current_rank_level,
                'action' => 'rejected',
                'signature_image_path' => Auth::user()->signature_image_path ?? 'no-signature.png',
                'comment' => $request->comment,
            ]);

            DB::commit();
            return redirect()->route('users.approvals.index')->with('success', 'Đã từ chối phiếu.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
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

        if (!$targetGroupId)
            return collect();

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
    public function bulkApprove(Request $request)
    {
        $request->validate([
            'request_ids' => 'required|array|min:1',
            'request_ids.*' => 'exists:purchase_requests,id',
        ]);

        $approvedCount = 0;
        $failedCount = 0;

        foreach ($request->request_ids as $id) {
            $purchaseRequest = PurchaseRequest::find($id);
            if ($purchaseRequest) {
                try {
                    $approveRequest = new Request(['comment' => 'Duyệt hàng loạt']);
                    $this->approve($approveRequest, $purchaseRequest);
                    $approvedCount++;
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Lỗi duyệt hàng loạt phiếu #{$id}: " . $e->getMessage());
                    $failedCount++;
                }
            }
        }

        $message = "Đã duyệt thành công {$approvedCount} phiếu.";
        if ($failedCount > 0) {
            $message .= " Có {$failedCount} phiếu không thể duyệt do lỗi hoặc không đủ quyền.";
        }

        return redirect()->route('users.approvals.index')->with('success', $message);
    }
}

