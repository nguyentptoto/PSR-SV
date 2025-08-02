<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\PurchaseRequest;

class DashboardController extends Controller
{
    /**
     * Hiển thị trang dashboard chính của ứng dụng.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();

        // Lấy các số liệu thống kê cho người dùng hiện tại
        $totalRequests = PurchaseRequest::where('requester_id', $user->id)->count();
        $completedRequests = PurchaseRequest::where('requester_id', $user->id)->where('status', 'completed')->count();
        $pendingRequests = PurchaseRequest::where('requester_id', $user->id)->whereIn('status', ['pending_approval', 'purchasing_approval'])->count();
        $rejectedRequests = PurchaseRequest::where('requester_id', $user->id)->where('status', 'rejected')->count();

        // Lấy các phiếu đề nghị gần đây
        $recentRequests = PurchaseRequest::where('requester_id', $user->id)
            ->with('branch')
            ->latest()
            ->take(5)
            ->get();
          // ✅ THÊM MỚI: Đếm số phiếu đang được phân công cho người dùng này
        $assignedRequestsCount = PurchaseRequest::where('assigned_purchaser_id', $user->id)
            ->where('status', 'purchasing_approval') // Chỉ đếm các phiếu đang hoạt động
            ->count();

        // ✅ SỬA LỖI: Thêm biến 'user' vào hàm compact()
        return view('dashboard', compact(
            'user', // Thêm dòng này
            'totalRequests',
            'completedRequests',
            'pendingRequests',
            'rejectedRequests',
            'recentRequests',
            'assignedRequestsCount' // ✅ Truyền biến mới ra view
        ));
    }
}
