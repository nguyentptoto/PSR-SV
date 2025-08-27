@extends('admin')

@section('title', 'Quản lý người dùng')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active" aria-current="page">
        Quản lý người dùng
    </li>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Danh sách người dùng</h5>
                <div class="btn-toolbar" role="toolbar">
                    <div class="btn-group me-2" role="group">
                        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Thêm mới
                        </a>
                    </div>
                    <div class="btn-group me-2" role="group">
                        <a href="{{ route('admin.users.import.show') }}" class="btn btn-success">
                            <i class="bi bi-file-earmark-arrow-up"></i> Import
                        </a>

                    </div>
                    <div class="btn-group" role="group">
                        <a href="{{ route('admin.users.export') }}" id="export-btn" class="btn btn-info">
                            <i class="bi bi-file-earmark-excel"></i> Export
                        </a>
                    </div>

                </div>
            </div>
        </div>
        <div class="card-body">
            {{-- Form tìm kiếm/lọc --}}
            <form method="GET" action="{{ route('admin.users.index') }}" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="employee_id" class="form-label">Mã nhân viên</label>
                        <input type="text" class="form-control" id="employee_id" name="employee_id"
                            value="{{ request('employee_id') }}" placeholder="Nhập mã nhân viên...">
                    </div>
                    <div class="col-md-3">
                        <label for="branch_id" class="form-label">Chi nhánh chính</label>
                        <select class="form-select" id="branch_id" name="branch_id">
                            <option value="">-- Tất cả chi nhánh --</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}"
                                    {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="section_id" class="form-label">Phòng ban chuyên môn</label>
                        <select class="form-select" id="section_id" name="section_id">
                            <option value="">-- Tất cả phòng ban --</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}"
                                    {{ request('section_id') == $section->id ? 'selected' : '' }}>
                                    {{ $section->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    {{-- CỘT MỚI: CHỌN SỐ LƯỢNG BẢN GHI --}}
                    {{-- <div class="col-md-1">
                        <label for="per_page" class="form-label">Hiển thị</label>
                        <select class="form-select" id="per_page" name="per_page" onchange="this.form.submit()">
                            <option value="15" {{ request('per_page', 15) == 15 ? 'selected' : '' }}>15</option>
                            <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                        </select>
                    </div> --}}
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-info me-2">
                            <i class="bi bi-search"></i> Lọc
                        </button>
                        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
            <hr>


            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {!! session('error') !!}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Tên</th>
                            <th>Email</th>
                            <th>Mã nhân viên</th>
                            <th>SAP ID</th>
                            <th>Chức vụ</th> {{-- ✅ THAY ĐỔI --}}
                            <th>Chi nhánh chính</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Ngày cập nhật</th>
                            <th style="width: 150px;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->employee_id }}</td>
                                <td>{{ $user->prs_id ?? 'N/A' }}</td>
                                <td>{{ $user->jobTitle->name ?? 'N/A' }}</td> {{-- ✅ THAY ĐỔI --}}
                                <td>{{ $user->mainBranch->name ?? 'N/A' }}</td>
                                <td>
                                    @if ($user->status)
                                        <span class="badge text-bg-success">Hoạt động</span>
                                    @else
                                        <span class="badge text-bg-danger">Khóa</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $user->created_at->format('d/m/Y H:i:s') }}
                                </td>
                                <td title="{{ $user->updated_at->format('d/m/Y H:i:s') }}">
                                    {{ $user->updated_at->diffForHumans() }}
                                </td>
                                <td>
                                    <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-info btn-sm"
                                        title="Xem chi tiết">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-primary btn-sm">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    @if (Auth::id() !== $user->id)
                                        {{-- Ngăn hiển thị nút cho tài khoản của chính mình --}}
                                        <form action="{{ route('admin.users.toggleStatus', $user->id) }}" method="POST"
                                            class="d-inline toggle-status-form">
                                            @csrf
                                            @method('PATCH') {{-- Sử dụng PATCH cho việc cập nhật một phần --}}

                                            @if ($user->status)
                                                {{-- Nếu user đang hoạt động, hiển thị nút Khóa --}}
                                                <button type="button" class="btn btn-warning btn-sm toggle-status-btn"
                                                    title="Vô hiệu hóa tài khoản" data-action="disable">
                                                    <i class="bi bi-lock-fill"></i>
                                                </button>
                                            @else
                                                {{-- Nếu user đang bị khóa, hiển thị nút Mở khóa --}}
                                                <button type="button" class="btn btn-success btn-sm toggle-status-btn"
                                                    title="Kích hoạt tài khoản" data-action="enable">
                                                    <i class="bi bi-unlock-fill"></i>
                                                </button>
                                            @endif
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                {{-- ✅ SỬA LỖI: Cập nhật colspan cho đúng số cột --}}
                                <td colspan="11" class="text-center">Không có dữ liệu phù hợp với bộ lọc.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-center mt-4">
                {{ $users->withQueryString()->links() }}
            </div>

        </div>
    </div>


    <!-- Modal hiển thị chi tiết người dùng -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">Chi tiết người dùng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="userModalBody">
                    {{-- Nội dung chi tiết sẽ được JS chèn vào đây --}}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>
@endsection
