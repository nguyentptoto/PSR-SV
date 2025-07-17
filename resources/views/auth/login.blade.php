@include('head')

<body class="login-page bg-body-secondary app-loaded">
    <div class="skip-links"><a href="#main" class="skip-link">Skip to main content</a><a href="#navigation" class="skip-link">Skip to navigation</a></div>
    <div class="login-box">
        <div class="card card-outline card-primary">
            <div class="card-header"> <a href="{{ route('login') }}" class="link-dark text-center link-offset-2 link-opacity-100 link-opacity-50-hover">
                    <img src="{{ asset('assets/img/logo.png') }}" alt="Logo">
                </a> </div>
            <div class="card-body login-card-body">
                <p class="login-box-msg">Đăng nhập vào hệ thống</p>
                    <form class="login-form" method="POST" action="{{ route('login') }}">
                    @csrf

                    {{-- Hiển thị thông báo lỗi chung nếu có --}}
                    @error('employee_id')
                    <div class="alert alert-danger p-2 mb-2 text-center">{{ $message }}</div>
                    @enderror

                    {{-- Input cho Mã Nhân Viên --}}
                    <div class="input-group mb-3">
                        <div class="form-floating">
                            <input id="employee_id" type="text" class="form-control" name="employee_id" value="{{ old('employee_id') }}" required placeholder="Mã Nhân Viên">
                            <label for="employee_id">Mã Nhân Viên</label>
                        </div>
                        <div class="input-group-text">
                            <span class="bi bi-person-vcard"></span>
                        </div>
                    </div>

                    {{-- Input cho Mật khẩu --}}
                    <div class="input-group mb-3">
                        <div class="form-floating">
                            <input id="loginPassword" type="password" class="form-control" name="password" required placeholder="Password">
                            <label for="loginPassword">Mật khẩu</label>
                        </div>
                        <div class="input-group-text">
                            <span class="bi bi-lock-fill"></span>
                        </div>
                    </div>

                    <div class="row">

                        <div class="col-5">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Đăng nhập</button>
                            </div>
                        </div>
                        <div class="col-7">
                            <p class="mb-1">
                                <a  href="http://172.30.134.15/sabbatical/public/index.php/reset-password">Quên mật khẩu?</a>
                            </p>
                    </div>
                </form>



            </div> <!-- /.login-card-body -->
        </div>
    </div> <!-- /.login-box --> <!--begin::Third Party Plugin(OverlayScrollbars)-->


    @include('script')
        @include('admin._partials.toast')
    <div id="live-region" class="live-region" aria-live="polite" aria-atomic="true" role="status"></div>

    <div class="cursor-dot"></div>
<div class="cursor-outline"></div>


</body>
