<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Hiển thị form đăng nhập.
     */
    public function create()
    {
        return view('auth.login');
    }

    /**
     * Xử lý yêu cầu đăng nhập.
     */
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'employee_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) { // Thêm $request->boolean('remember') để xử lý "Ghi nhớ"

            if (Auth::user()->status == false) {
                Auth::logout();
                // Thay đổi ở đây: Gửi thông báo lỗi về
                return back()->with('notification', [
                    'type' => 'error',
                    'message' => 'Tài khoản của bạn đã bị vô hiệu hóa.'
                ]);
            }

            $request->session()->regenerate();

            // Thay đổi ở đây: Gửi thông báo thành công khi chuyển hướng
            return redirect()->route('dashboard')->with('notification', [
                'type' => 'success',
                'message' => 'Đăng nhập thành công!'
            ]);
        }

        // Thay đổi ở đây: Gửi thông báo lỗi về
        return back()
            ->withInput($request->only('employee_id', 'remember')) // Giữ lại mã nhân viên đã nhập
            ->with('notification', [
                'type' => 'error',
                'message' => 'Mã nhân viên hoặc mật khẩu không chính xác.'
            ]);
    }

    /**
     * Xử lý yêu cầu đăng xuất.
     */
    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/'); // Chuyển về trang chủ
    }
}
