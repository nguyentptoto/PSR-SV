@extends('admin')
@section('title', 'Chi tiết Người dùng: ' . $user->name)

@section('content')
    <div class="row">
        {{-- Cột thông tin cơ bản --}}
        <div class="col-md-6">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Thông tin Người dùng</h3>
                </div>
                <div class="card-body">
                    <p><strong>Tên:</strong> {{ $user->name }}</p>
                    <p><strong>Chức vụ:</strong> {{ $user->jobTitle->name ?? 'N/A' }}</p>
                    <p><strong>Email:</strong> {{ $user->email }}</p>
                    <p><strong>Mã nhân viên:</strong> {{ $user->employee_id }}</p>
                    <p><strong>Chi nhánh chính:</strong> {{ $user->mainBranch->name ?? 'N/A' }}</p>
                    <p><strong>Phòng ban:</strong>
                        @foreach ($user->sections as $section)
                            <span class="badge bg-secondary">{{ $section->name }}</span>
                        @endforeach
                    </p>
                    @if ($user->signature_image_path)
                        <p><strong>Chữ ký:</strong></p>
                        <img src="{{ Storage::url($user->signature_image_path) }}" alt="Chữ ký" height="150">
                    @endif
                </div>
            </div>
        </div>

        {{-- Cột thông tin phân quyền và cấp trên --}}
        <div class="col-md-6">
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">Phân quyền & Chuỗi phê duyệt</h3>
                </div>
                <div class="card-body">
                    <h5>Quyền hạn được gán</h5>
                     <ul class="list-group list-group-flush mb-3">
                    @forelse($user->assignments as $assignment)
                        <li class="list-group-item">
                            Tại <strong>{{ $assignment->branch->name }}</strong>, trong nhóm <strong>{{ $assignment->group->name }}</strong>, có <strong>{{ $assignment->approvalRank->name }}</strong>.
                        </li>
                    @empty
                        <li class="list-group-item">Chưa có quyền hạn nào.</li>
                    @endforelse
                </ul>

                    <hr>

                     {{-- Chuỗi duyệt Phòng Đề Nghị --}}
                <h5 class="mt-3"><i class="fas fa-tasks"></i> Cấp trên trong <span class="text-primary">Phòng Đề Nghị</span></h5>
                <ul class="list-group list-group-flush mb-3">
                    @forelse($requestingSuperiors as $rankName => $superiorUsers)
                        <li class="list-group-item">
                            <strong>{{ $rankName }}:</strong>
                            <ul class="list-unstyled mt-2 ps-3">
                                @foreach($superiorUsers as $superiorUser)
                                    <li class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <span class="fw-bold">{{ $superiorUser->name }}</span>
                                            <small class="d-block text-muted">Mã NV: {{ $superiorUser->employee_id }}</small>
                                        </div>
                                        <div class="btn-group">
                                            <a href="{{ route('admin.users.show', $superiorUser->id) }}" class="btn btn-sm btn-outline-info" title="Xem chi tiết"><i class="bi bi-eye"></i></a>
                                            <a href="{{ route('admin.users.edit', $superiorUser->id) }}" class="btn btn-sm btn-outline-primary" title="Chỉnh sửa"><i class="bi bi-pencil-square"></i></a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @empty
                        <li class="list-group-item">Không có cấp trên trong nhóm này.</li>
                    @endforelse
                </ul>

                {{-- Chuỗi duyệt Phòng Mua --}}
                <h5 class="mt-4"><i class="fas fa-shopping-cart"></i> Cấp trên trong <span class="text-success">Phòng Mua</span></h5>
                <ul class="list-group list-group-flush">
                    @forelse($purchasingSuperiors as $rankName => $superiorUsers)
                         <li class="list-group-item">
                            <strong>{{ $rankName }}:</strong>
                            <ul class="list-unstyled mt-2 ps-3">
                                @foreach($superiorUsers as $superiorUser)
                                    <li class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <span class="fw-bold">{{ $superiorUser->name }}</span>
                                            <small class="d-block text-muted">Mã NV: {{ $superiorUser->employee_id }}</small>
                                        </div>
                                        <div class="btn-group">
                                            <a href="{{ route('admin.users.show', $superiorUser->id) }}" class="btn btn-sm btn-outline-info" title="Xem chi tiết"><i class="bi bi-eye"></i></a>
                                            <a href="{{ route('admin.users.edit', $superiorUser->id) }}" class="btn btn-sm btn-outline-primary" title="Chỉnh sửa"><i class="bi bi-pencil-square"></i></a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @empty
                        <li class="list-group-item">Không có cấp trên trong nhóm này.</li>
                    @endforelse
                </ul>
                </div>



                <div class="card-footer">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Quay lại danh sách</a>
                    {{-- ✅ THÊM MỚI: Nút chỉnh sửa người dùng hiện tại --}}
                    <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-primary">
                        <i class="bi bi-pencil-square"></i> Chỉnh sửa Người dùng này
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
