<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SupportController extends Controller
{
    /**
     * Hiển thị trang thông tin hỗ trợ.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Trả về view 'support.index'
        // Bạn có thể truyền thêm dữ liệu ra view nếu cần, ví dụ:
        // $supportEmail = 'support@example.com';
        // return view('support.index', compact('supportEmail'));
        return view('support.index');
    }
}
