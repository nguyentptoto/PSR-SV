<!DOCTYPE html>
<html lang="en"> <!--begin::Head-->

@include('head')

<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">

     <div class="cursor-dot"></div>
    <div class="cursor-outline"></div>
    <div class="app-wrapper">
        <nav class="app-header navbar navbar-expand bg-body">
            <div class="container-fluid">

                <ul class="navbar-nav">
                    <li class="nav-item"> <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"> <i
                                class="bi bi-list"></i> </a> </li>
                    <li class="nav-item d-none d-md-block"> <a href="#" style="font-size: 24px;display: flex;justify-content: center;align-items: center;" class="nav-link">Hệ thống phê duyệt mua hàng</a> </li>

                </ul>

                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a href="#" class="nav-link logo-link">
                            <img src="{{ asset('assets/img/logo.png') }}" class="logo-image" alt="Logo">
                        </a>
                    </li>
                    {{-- Thêm đoạn này để chứa thanh dịch --}}
<li class="nav-item d-flex align-items-center me-2">
    <div id="google_translate_element"></div>
</li>
                    <li class="nav-item dropdown user-menu">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                            {{ Auth::user()->name }}
                            <i class="bi bi-chevron-down ms-1"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                            <li class="user-footer">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-default btn-flat float-end">
                                        Đăng xuất tài khoản người dùng
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>

                </ul>
            </div>
        </nav>
        @include('sidebar')
        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-6">
                            <h3 class="mb-0" style="font-size: 18px">@yield('title')</h3>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                @yield('breadcrumbs')
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div class="app-content">
                <div class="container-fluid">
                    <div class="row">
                        @yield('content')
                    </div>

                </div>
            </div>
        </main>
        <footer class="app-footer">
            <div class="float-end d-none d-sm-inline">Hệ thống hỗ trợ cho nhân viên</div>

            <strong>
                Hệ thống PSR &nbsp;
                <a href="{{ route('support.index') }}" class="text-decoration-none">Hỗ trợ liên hệ</a>.
            </strong>
        </footer>
    </div>


    @include('script')
    @include('admin._partials.toast')
<script type="text/javascript">
function googleTranslateElementInit() {
  new google.translate.TranslateElement({
    // Ngôn ngữ gốc của trang web của bạn
    pageLanguage: 'vi',
    // Chỉ định các ngôn ngữ bạn muốn hiển thị trong danh sách
    includedLanguages: 'en,ja,ko,zh-CN,fr',
    // Giao diện: ngang, dọc, hoặc chỉ có icon
    layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
    // Tự động ẩn banner của Google sau khi dịch
    autoDisplay: false
  }, 'google_translate_element');
}
</script>

<script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
</body>

</html>
