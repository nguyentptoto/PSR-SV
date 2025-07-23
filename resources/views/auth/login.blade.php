@include('head')


<body class="login-page bg-body-secondary app-loaded">

    <canvas id="fish-canvas"></canvas>
    <div class="cursor-dot"></div>
    <div class="cursor-outline"></div>
      <button id="theme-toggle" class="theme-control-button" title="Đổi giao diện">
        <i class="bi bi-stars"></i>
    </button>
    <button id="night-mode-toggle" class="theme-control-button" title="Chế độ Ngày/Đêm">
        <i class="bi bi-moon-stars-fill"></i>
    </button>
    <div class="login-box">
        <div class="card card-outline card-primary shadow-lg rounded-4">
            <div class="card-header border-0 bg-transparent pt-4">
                <a href="{{ route('login') }}" class="link-dark text-center">
                    <img src="{{ asset('assets/img/logo.png') }}" alt="Logo"
                        style="max-width: 150px; height: auto;">
                </a>
            </div>
            <div class="card-body login-card-body p-4">
                <p class="login-box-msg">Đăng nhập vào hệ thống<br><span style="font-size: 0.95em;">システムにログイン</span></p>
                <form class="login-form" method="POST" action="{{ route('login') }}">
                    @csrf
                    @error('employee_id')
                        <div class="alert alert-danger p-2 mb-2 text-center">{{ $message }}</div>
                    @enderror
                    <div class="input-group mb-3">
                        <div class="form-floating">
                            <input id="employee_id" type="text" class="form-control" name="employee_id"
                                value="{{ old('employee_id') }}" required placeholder="Mã Nhân Viên">
                            <label for="employee_id">Mã Nhân Viên \ 社員番号</label>
                        </div>
                        <div class="input-group-text"><span class="bi bi-person-vcard"></span></div>
                    </div>
                    <div class="input-group mb-3">
                        <div class="form-floating">
                            <input id="loginPassword" type="password" class="form-control" name="password" required
                                placeholder="Password">
                            <label for="loginPassword">Mật khẩu \ パスワード</label>
                        </div>
                        <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
                    </div>
                    <div class="row align-items-center">
                            <div class="col-5">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Đăng nhập</button>


                            </div>
                        </div>
                        <div class="col-7 text-end">
                            <p class="mb-0">
                                <a href="http://172.30.134.15/sabbatical/public/index.php/reset-password">Quên mật
                                    khẩu?</a>
                            </p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('script')
    @include('admin._partials.toast')



</body>

</html>
