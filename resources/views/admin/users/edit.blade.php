@extends('admin')
@section('title', 'Chỉnh sửa Người dùng: ' . $user->name)

@section('content')
<div class="container-fluid">
    {{-- ✅ BƯỚC 1: Form trỏ đến route 'update' và sử dụng method 'PUT' --}}
    <form action="{{ route('admin.users.update', $user->id) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT') {{-- Bắt buộc phải có cho việc cập nhật --}}

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Chỉnh sửa người dùng: {{ $user->name }}</h3>
                <div class="card-tools">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Quay lại</a>
                </div>
            </div>

            @include('admin.users._form')

        </div>
    </form>
</div>
@endsection

{{-- ✅ BƯỚC 3: Thêm script để kích hoạt Select2 --}}
@push('scripts')
<script>
    $(document).ready(function() {
        // Kích hoạt Select2 cho ô chọn phòng ban
        $('#sections-select2').select2({
            theme: 'bootstrap-5',
            placeholder: 'Chọn hoặc tìm kiếm phòng ban...',
            allowClear: true
        });

        // Kích hoạt Select2 cho ô chọn chức vụ
        $('#job-title-select2').select2({
            theme: 'bootstrap-5',
            placeholder: 'Chọn chức vụ',
            allowClear: true
        });

        // Nút bấm chọn/bỏ chọn tất cả phòng ban
        $('#select-all-sections').on('click', function() {
            $("#sections-select2 > option").prop("selected", true).trigger("change");
        });
        $('#deselect-all-sections').on('click', function() {
            $("#sections-select2 > option").prop("selected", false).trigger("change");
        });
    });
</script>
@endpush
