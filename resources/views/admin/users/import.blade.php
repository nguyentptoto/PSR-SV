@extends('admin')
@section('title', 'Import Người dùng')

@section('content')
<div class="card card-success">
    <div class="card-header">
        <h3 class="card-title">Import Danh sách Người dùng từ Excel</h3>
        <div class="card-tools">
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Quay lại</a>
        </div>
    </div>
    <form action="{{ route('admin.users.import.handle') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="card-body">
            {{-- Hiển thị thông báo --}}
            @if(session('success'))
            <div class="alert alert-success">{!! session('success') !!}</div>
            @endif
            @if(session('error'))
            <div class="alert alert-danger">{!! session('error') !!}</div>
            @endif
            @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- Hướng dẫn chi tiết --}}
            <div class="callout callout-info">
                <h5><i class="fas fa-info-circle"></i> Hướng dẫn Import</h5>
                <p><strong>Bước 1:</strong> Chuẩn bị một thư mục trên máy tính của bạn.</p>
                <p><strong>Bước 2:</strong> Bên trong thư mục đó, đặt 2 thứ:</p>
                <ul>
                    <li>File Excel chứa danh sách người dùng.</li>
                    <li>Một thư mục con có tên chính xác là `signatures`.</li>
                </ul>
                <p><strong>Bước 3:</strong> Đặt tất cả các file ảnh chữ ký vào thư mục `signatures`. **Tên mỗi file ảnh phải trùng với "Toto ID"** trong file Excel (ví dụ: `TOTO-001.png`).</p>
                <p><strong>Bước 4:</strong> Nén (Zip) thư mục ở Bước 1 lại thành một file `.zip` duy nhất.</p>
                <p><strong>Bước 5:</strong> Chọn file `.zip` đó và nhấn "Bắt đầu Import".</p>
            </div>

            {{-- Ô upload file ZIP --}}
            <div class="form-group">
                <label for="import_file">Chọn file .ZIP để import</label>
                <div class="custom-file">
                    <input type="file" class="custom-file-input" id="import_file" name="import_file" required accept=".zip">
                    <label class="custom-file-label" for="import_file">Chọn file...</label>
                </div>
            </div>

        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-success">Bắt đầu Import</button>
        </div>
    </form>
</div>
@endsection
