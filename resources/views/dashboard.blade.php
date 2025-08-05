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

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Thông tin quyền hạn của bạn</h3>
            </div>
            <div class="card-body">

            @php
                $mainBranchAssignments = $user->assignments->where('branch_id', $user->main_branch_id);
            @endphp

            @if ($mainBranchAssignments->isNotEmpty())
                <ul class="list-group">
                    @foreach ($mainBranchAssignments as $assignment)
                        <li class="list-group-item">
                            Tại chi nhánh chính <strong>{{ $assignment->branch->name }}</strong>,
                            trong nhóm <strong>{{ $assignment->group->name }}</strong>,
                            bạn có quyền <strong>{{ $assignment->approvalRank->name }}</strong>.
                        </li>
                    @endforeach
                </ul>
            @else
                <p>Bạn chưa được gán quyền hạn nào tại chi nhánh chính.</p>
            @endif
        </div>
        </div>

        {{-- Ví dụ hiển thị một phần tử chỉ dành cho admin --}}
        @can('is-admin')
            <div class="alert alert-success mt-3">
                <i class="icon fas fa-check"></i>
                Bạn có quyền quản trị viên cao nhất.
            </div>
        @endcan
    </div>
@endsection
