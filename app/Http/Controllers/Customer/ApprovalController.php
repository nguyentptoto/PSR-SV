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
use App\Jobs\SendRejectionNotification; // Bạn có thể tạo Job này để thông báo từ chối
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

class ApprovalController extends Controller
{
    /**
     * ✅ TỐI ƯU: Hiển thị danh sách các phiếu đang chờ người dùng hiện tại duyệt.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $sections = Section::orderBy('name')->get();
        $requesters = User::whereIn('id', PurchaseRequest::select('requester_id')->distinct())->orderBy('name')->get();

        $query = PurchaseRequest::query()
            ->with(['requester', 'branch', 'section'])
            ->whereIn('status', ['pending_approval', 'purchasing_approval']);

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

        // Lấy tất cả các phiếu có khả năng liên quan
        $allPotentialRequests = $query->latest()->get();

        // Dùng Policy để lọc chính xác những phiếu người dùng có quyền duyệt
        $pendingRequests = $allPotentialRequests->filter(function ($pr) use ($user) {
            return $user->can('approve', $pr);
        });

        // Phân trang thủ công sau khi lọc
        $page = Paginator::resolveCurrentPage('page');
        $perPage = 15;
        $paginatedRequests = new LengthAwarePaginator(
            $pendingRequests->forPage($page, $perPage),
            $pendingRequests->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('users.approvals.index', [
            'pendingRequests' => $paginatedRequests,
            'sections' => $sections,
            'requesters' => $requesters,
        ]);
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
            ->where('status', '!=', 'rejected')
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
     * ✅ REFACTORED: Phê duyệt một phiếu đề nghị.
     */
    public function approve(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorize('approve', $purchaseRequest);

        try {
            $this->performApprovalLogic($purchaseRequest, $request->input('comment', 'Đã phê duyệt'));
            return redirect()->route('users.approvals.index')->with('success', 'Phê duyệt phiếu thành công.');
        } catch (\Exception $e) {
            Log::error("Lỗi khi duyệt phiếu #{$purchaseRequest->id}: " . $e->getMessage());
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

            // Gửi thông báo từ chối cho người tạo phiếu
            if ($purchaseRequest->requester) {
                 // Để tối ưu, bạn nên tạo một Job riêng cho việc từ chối để mail có nội dung phù hợp
                 // SendRejectionNotification::dispatch($purchaseRequest, $purchaseRequest->requester);
            }

            return redirect()->route('users.approvals.index')->with('success', 'Đã từ chối phiếu.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }

    /**
     * ✅ REFACTORED: Duyệt nhiều phiếu cùng lúc.
     */
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
            if (!$purchaseRequest || !$request->user()->can('approve', $purchaseRequest)) {
                $failedCount++;
                continue;
            }

            try {
                $this->performApprovalLogic($purchaseRequest, 'Duyệt hàng loạt');
                $approvedCount++;
            } catch (\Exception $e) {
                Log::error("Lỗi duyệt hàng loạt phiếu #{$id}: " . $e->getMessage());
                $failedCount++;
            }
        }

        $message = "Đã duyệt thành công {$approvedCount} phiếu.";
        if ($failedCount > 0) {
            $message .= " Có {$failedCount} phiếu không thể duyệt do lỗi hoặc không đủ quyền.";
        }

        return redirect()->route('users.approvals.index')->with('success', $message);
    }

    /**
     * ✅ NEW HELPER: Logic duyệt phiếu cốt lõi.
     */
    private function performApprovalLogic(PurchaseRequest $purchaseRequest, ?string $comment)
    {
        DB::transaction(function () use ($purchaseRequest, $comment) {
            $user = Auth::user();
            $currentRankLevel = $purchaseRequest->current_rank_level;
            $isRequestingGroup = $purchaseRequest->status === 'pending_approval';

            ApprovalHistory::create([
                'purchase_request_id' => $purchaseRequest->id,
                'user_id' => $user->id,
                'rank_at_approval' => 'Cấp ' . $currentRankLevel, // Tên vai trò có thể được làm chi tiết hơn
                'action' => 'approved',
                'signature_image_path' => $user->signature_image_path ?? 'no-signature.png',
                'comment' => $comment,
            ]);

            // Logic chuyển cấp duyệt
            if ($isRequestingGroup) {
                $isFinalLevel = $purchaseRequest->requires_director_approval ? ($currentRankLevel >= 4) : ($currentRankLevel >= 3);
                if ($isFinalLevel) {
                    $purchaseRequest->status = 'purchasing_approval';
                    $purchaseRequest->current_rank_level = 2; // Bắt đầu ở cấp 2 của phòng mua
                } else {
                    $purchaseRequest->current_rank_level++;
                }
            } else { // purchasing_approval
                if ($currentRankLevel >= 4) { // Giả sử cấp 4 là cấp cuối của phòng mua
                    $purchaseRequest->status = 'completed';
                } else {
                    $purchaseRequest->current_rank_level++;
                }
            }

            $purchaseRequest->save();

           $nextApprovers = $this->findNextApprovers($purchaseRequest);
foreach ($nextApprovers as $approver) {
    // Dispatch một job cho MỖI người duyệt.
    // Lúc này, $approver là một đối tượng User duy nhất, đúng yêu cầu của Job.
    SendApprovalNotification::dispatch($purchaseRequest, $approver);
}
        });
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

        if (!$targetGroupId) {
            return collect();
        }

        $approverQuery = User::query()
            ->whereHas('assignments', function ($q) use ($nextRankLevel, $branchId, $targetGroupId) {
                $q->whereHas('approvalRank', fn($r) => $r->where('rank_level', $nextRankLevel))
                    ->where('branch_id', $branchId)
                    ->where('group_id', $targetGroupId);
            });

        if ($isRequestingStage) {
            $approverQuery->whereHas('sections', fn($q) => $q->where('sections.id', $sectionId));
        }

        if ($isRequestingStage && $nextRankLevel == 4 && !$pr->requires_director_approval) {
            return collect();
        }

        return $approverQuery->get();
    }
}
