{{-- File: resources/views/admin/_partials/toast.blade.php --}}

@if (session()->has('notification'))
    <script>
        // Đợi cho trang tải xong hoàn toàn rồi mới thực thi
        document.addEventListener('DOMContentLoaded', function () {
            // Cấu hình chung cho Toast của SweetAlert2
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end', // Vị trí ở góc trên bên phải
                showConfirmButton: false,
                timer: 3000, // Tự động tắt sau 3 giây
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.onmouseenter = Swal.stopTimer;
                    toast.onmouseleave = Swal.resumeTimer;
                }
            });

            // Gọi Toast với loại (icon) và thông báo từ session
            Toast.fire({
                icon: '{{ session('notification')['type'] }}', // 'success' hoặc 'error'
                title: '{{ session('notification')['message'] }}'
            });
        });
    </script>
@endif
