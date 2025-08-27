@extends('admin')

{{-- Cung cấp tiêu đề cho trang --}}
@section('title', 'Bảng điều khiển')

{{-- Cung cấp breadcrumbs cho trang --}}
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active" aria-current="page">
        Bảng điều khiển
    </li>
@endsection

@section('content')
    <div class="container-fluid">
        <p>Chào mừng trở lại, {{ $user->name }}!</p>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Thông tin quyền hạn của bạn</h3>
                    </div>
                    <div class="card-body">
                        @php
                            $mainBranchAssignments = $user->assignments->where('branch_id', $user->main_branch_id);
                        @endphp

                        {{-- ✅ SỬA ĐỔI: Logic hiển thị quyền hạn được nâng cấp --}}
                        @if ($mainBranchAssignments->isNotEmpty() || $assignedRequestsCount > 0)
                            <ul class="list-group">
                                {{-- Hiển thị các quyền hạn chính thức --}}
                                @foreach ($mainBranchAssignments as $assignment)
                                    <li class="list-group-item">
                                        Tại chi nhánh chính <strong>{{ $assignment->branch->name }}</strong>,
                                        trong nhóm <strong>{{ $assignment->group->name }}</strong>,
                                        bạn có quyền <strong>{{ $assignment->approvalRank->name }}</strong>.
                                    </li>
                                @endforeach


                                {{-- ✅ SỬA ĐỔI: Thêm link vào dòng thông báo --}}
                                @if ($assignedRequestsCount > 0)
                                    <a href="{{ route('users.approvals.index') }}"
                                        class="list-group-item list-group-item-action">
                                        <i class="bi bi-info-circle-fill text-info"></i>
                                        Bạn đang được phân công xử lý <strong>{{ $assignedRequestsCount }}</strong> phiếu đề
                                        nghị.
                                    </a>
                                @endif
                            </ul>
                        @else
                            <p>Bạn chưa được gán quyền hạn hoặc phân công nhiệm vụ nào.</p>
                        @endif
                    </div>
                </div>
            </div>


        </div>
    </div>
@endsection
