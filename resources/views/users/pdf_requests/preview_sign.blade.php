@extends('admin')
@section('title', 'Ký Phiếu Đề Nghị PDF')

@section('content')
    {{-- Kiểm tra xem biến được truyền vào có phải là một Collection hay không để đặt tiêu đề phù hợp --}}
    @if(isset($pdfPurchaseRequests) && $pdfPurchaseRequests instanceof \Illuminate\Support\Collection)
        <h2 class="mb-4">Ký Nhiều Phiếu Đề Nghị PDF</h2>
    @else
        <h2 class="mb-4">Ký Phiếu Đề Nghị PDF: {{ $pdfPurchaseRequest->pia_code }}</h2>
    @endif

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
            @if ($userSignaturePath)
                {{-- Kiểm tra nếu biến là một Collection, hiển thị giao diện ký nhiều phiếu --}}
                @if(isset($pdfPurchaseRequests) && $pdfPurchaseRequests instanceof \Illuminate\Support\Collection)
                    <div class="mb-4">
                        <label>Ảnh chữ ký của bạn:</label><br>
                        <img src="{{ asset('storage/' . $userSignaturePath) }}" alt="Chữ ký"
                            style="max-width: 150px; border: 1px solid #ccc;">
                        <p class="text-muted small mt-1">Chữ ký này sẽ được dán vào tất cả các file PDF.</p>
                    </div>

                    <form action="{{ route('users.pdf-requests.sign-submit-batch') }}" method="POST">
                        @csrf
                        {{-- Dùng hidden inputs để truyền tất cả ID của các phiếu PDF --}}
                        @foreach ($pdfPurchaseRequests as $pdfRequest)
                            <input type="hidden" name="pdf_ids[]" value="{{ $pdfRequest->id }}">
                        @endforeach

                        <div class="table-responsive mb-4">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Mã Phiếu</th>
                                        <th>Trạng thái</th>
                                        <th>File PDF Gốc</th>
                                        <th>Vị trí ký (đã lưu)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pdfPurchaseRequests as $pdfRequest)
                                        <tr>
                                            <td>{{ $pdfRequest->pia_code }}</td>
                                            <td>
                                                <span class="badge {{
                                                    $pdfRequest->status === 'pending_approval' ? 'bg-warning' :
                                                    ($pdfRequest->status === 'approved' ? 'bg-success' :
                                                    ($pdfRequest->status === 'rejected' ? 'bg-danger' : 'bg-info'))
                                                }}">
                                                    {{ ucfirst(str_replace('_', ' ', $pdfRequest->status)) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if ($pdfRequest->original_pdf_path)
                                                    <a href="{{ route('users.pdf-requests.view-file', $pdfRequest->id) }}" target="_blank" class="btn btn-sm btn-info">
                                                        <i class="fas fa-file-pdf"></i> Xem File
                                                    </a>
                                                @else
                                                    <span class="text-danger">Không có file</span>
                                                @endif
                                            </td>
                                            <td>
                                                Trang: {{ $pdfRequest->signature_page ?? 'Mặc định' }}<br>
                                                X: {{ $pdfRequest->signature_pos_x ?? 'Mặc định' }} mm,
                                                Y: {{ $pdfPurchaseRequest->signature_pos_y ?? 'Mặc định' }} mm<br>
                                                Rộng: {{ $pdfPurchaseRequest->signature_width ?? 'Mặc định' }} mm,
                                                Cao: {{ $pdfPurchaseRequest->signature_height ?? 'Mặc định' }} mm
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-signature"></i> Ký và Gửi Duyệt Tất Cả Các Phiếu
                        </button>
                    </form>
                {{-- Nếu biến không phải là Collection, hiển thị giao diện ký một phiếu --}}
                @else
                    <div class="row">
                        <div class="col-md-8">
                            <h5>File PDF gốc</h5>
                            @if ($pdfUrl)
                                <div class="embed-responsive embed-responsive-16by9" style="height: 500px;">
                                    <iframe class="embed-responsive-item" src="{{ $pdfUrl }}"
                                        style="width: 100%; height: 100%;" frameborder="0"></iframe>
                                </div>
                            @else
                                <p class="text-danger">Không tìm thấy file PDF gốc.</p>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <h5>Thông tin ký</h5>
                            <div class="mb-3">
                                <label>Ảnh chữ ký của bạn:</label><br>
                                <img src="{{ asset('storage/' . $userSignaturePath) }}" alt="Chữ ký"
                                    style="max-width: 150px; border: 1px solid #ccc;">
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
                            <form action="{{ route('users.pdf-requests.sign-submit', $pdfPurchaseRequest->id) }}"
                                method="POST">
                                @csrf
                                <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-signature"></i> Ký và
                                    Hoàn tất Phiếu</button>
                            </form>
                        </div>
                    </div>
                @endif
            @else
                <div class="alert alert-warning">
                    Không tìm thấy ảnh chữ ký của bạn. Vui lòng <a href="#">cập nhật hồ sơ</a> để thêm ảnh chữ
                    ký trước khi ký.
                </div>
            @endif
        </div>
        <div class="card-footer">
            <a href="{{ route('users.pdf-requests.create') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i>
                Tải lên PDF khác</a>
        </div>
    </div>
@endsection
