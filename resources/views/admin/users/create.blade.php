@extends('admin')

{{-- Tiêu đề trang --}}
@section('title', 'Tạo Người dùng mới')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Quản lý người dùng</a></li>
    <li class="breadcrumb-item active" aria-current="page">
        Thêm mới
    </li>
@endsection
@section('content')
<form action="{{ route('admin.users.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Tạo người dùng mới</h3>
            <div class="card-tools">
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Quay lại</a>
            </div>
        </div>
        {{-- Gọi file form chung vào --}}
        @include('admin.users._form')
    </div>
</form>
@endsection
