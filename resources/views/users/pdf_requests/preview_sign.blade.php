@extends('admin')
@section('title', 'Ký Phiếu Đề Nghị PDF')

@section('content')
    <h2 class="mb-4">Ký Phiếu Đề Nghị PDF: {{ $pdfPurchaseRequest->pia_code }}</h2>

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <h5 class="alert-heading"><i class="icon fas fa-ban"></i> Lỗi!</h5>
            {{ session('error') }}
        </div>
    @endif
    @if (session('success'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <h5 class="alert-heading"><i class="icon fas fa-check-circle"></i> Thành công!</h5>
            {{ session('success') }}
        </div>
    @endif

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Xem trước và Ký</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h5>File PDF gốc</h5>
                    @if ($pdfUrl)
                        <div class="embed-responsive embed-responsive-16by9" style="height: 500px;">
                            {{-- src="{{ $pdfUrl }}" này thực chất gọi đến route Laravel (users.pdf-requests.view-file) --}}
                            {{-- Route này sẽ đọc file PDF từ thư mục storage/app/public và stream nội dung ra trình duyệt. --}}
                            {{-- Đây là cách an toàn và đúng đắn để hiển thị file từ storage mà không cần public/storage symbolic link --}}
                            <iframe class="embed-responsive-item" src="{{ $pdfUrl }}" style="width: 100%; height: 100%;" frameborder="0"></iframe>
                        </div>
                    @else
                        <p class="text-danger">Không tìm thấy file PDF gốc.</p>
                    @endif
                </div>
                <div class="col-md-4">
                    <h5>Thông tin ký</h5>
                    @if ($userSignaturePath)
                        <div class="mb-3">
                            <label>Ảnh chữ ký của bạn:</label><br>
                            <img src="{{ asset('storage/' . $userSignaturePath) }}" alt="Chữ ký" style="max-width: 150px; border: 1px solid #ccc;">
                            <p class="text-muted small mt-1">Chữ ký này sẽ được dán vào PDF.</p>
                        </div>
                        <p><strong>Vị trí ký đã lưu:</strong></p>
                        <ul>
                            <li>Trang: {{ $pdfPurchaseRequest->signature_page ?? 'Mặc định' }}</li>
                            <li>X: {{ $pdfPurchaseRequest->signature_pos_x ?? 'Mặc định' }} mm</li>
                            <li>Y: {{ $pdfPurchaseRequest->signature_pos_y ?? 'Mặc định' }} mm</li>
                            <li>Rộng: {{ $pdfPurchaseRequest->signature_width ?? 'Mặc định' }} mm</li>
                            <li>Cao: {{ $pdfPurchaseRequest->signature_height ?? 'Mặc định' }} mm</li>
                        </ul>

                        <form action="{{ route('users.pdf-requests.sign-submit', $pdfPurchaseRequest->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-signature"></i> Ký và Hoàn tất Phiếu</button>
                        </form>
                    @else
                        <div class="alert alert-warning">
                            Không tìm thấy ảnh chữ ký của bạn. Vui lòng <a href="#">cập nhật hồ sơ</a> để thêm ảnh chữ ký trước khi ký.
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="card-footer">
            <a href="{{ route('users.pdf-requests.create') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Tải lên PDF khác</a>
        </div>
    </div>
@endsection
