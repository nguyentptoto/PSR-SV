@extends('admin')
@section('title', 'Chỉnh Sửa Phiếu Đề Nghị PDF')

@section('content')
    <h2 class="mb-4">Chỉnh Sửa Phiếu Đề Nghị (PDF): {{ $pdfPurchaseRequest->pia_code }}</h2>

    {{-- Hiển thị các thông báo lỗi --}}
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <h5 class="alert-heading"><i class="icon fas fa-ban"></i> Lỗi!</h5>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <h5 class="alert-heading"><i class="icon fas fa-ban"></i> Lỗi!</h5>
            {{ session('error') }}
        </div>
    @endif

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Thông tin Phiếu PDF</h3>
        </div>
        <form action="{{ route('users.pdf-requests.update', $pdfPurchaseRequest->id) }}" method="POST"
            enctype="multipart/form-data">
            @csrf
            @method('PUT') {{-- Sử dụng phương thức PUT cho cập nhật --}}
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="pia_code">Mã Phiếu (PR NO)*</label>
                        {{-- THAY ĐỔI: Thêm thuộc tính readonly và disabled --}}
                        <input type="text" name="pia_code" id="pia_code" class="form-control"
                            value="{{ old('pia_code', $pdfPurchaseRequest->pia_code) }}" required readonly>
                        <small class="form-text text-muted">Mã PR không thể chỉnh sửa.</small>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Người yêu cầu</label>
                        <input type="text" class="form-control" value="{{ $user->name ?? 'N/A' }}" disabled>
                    </div>
                </div>

                <div class="form-group mt-3">
                    <label for="remarks">Ghi chú (Tùy chọn)</label>
                    <textarea name="remarks" id="remarks" class="form-control" rows="3">{{ old('remarks', $pdfPurchaseRequest->remarks) }}</textarea>
                </div>

                {{-- THÊM MỚI: Trường để cập nhật file PDF gốc --}}
                <div class="form-group mt-3">
                    <label for="original_pdf">File PDF gốc</label>
                    <input type="file" name="original_pdf" id="original_pdf" class="form-control" accept=".pdf">
                    <small class="form-text text-muted">Tải lên một file mới sẽ thay thế file PDF gốc hiện tại.</small>
                    @if ($pdfPurchaseRequest->original_pdf_path)
                        <p class="mt-2">
                            File PDF gốc hiện tại:
                            <a href="{{ route('users.pdf-requests.view-file', $pdfPurchaseRequest->id) }}" target="_blank">
                                {{ basename($pdfPurchaseRequest->original_pdf_path) }}
                            </a>
                        </p>
                    @endif
                </div>

                {{-- THÊM MỚI: Trường để cập nhật file đính kèm --}}
                <div class="form-group mt-3">
                    <label for="attachment">File đính kèm (PDF, Excel, Word)</label>
                    <input type="file" name="attachment" id="attachment" class="form-control"
                        accept=".pdf, .xlsx, .xls, .doc, .docx">
                    <small class="form-text text-muted">Tải lên một file mới sẽ thay thế file cũ (nếu có).</small>
                    @if ($pdfPurchaseRequest->attachment_path)
                        <p class="mt-2">
                            File đính kèm hiện tại:
                            <a href="{{ asset('storage/' . $pdfPurchaseRequest->attachment_path) }}" target="_blank">
                                {{ basename($pdfPurchaseRequest->attachment_path) }}
                            </a>
                        </p>
                    @endif
                </div>

                {{-- Các trường để cấu hình vị trí ký --}}
                <div class="card card-secondary mt-4">
                    <div class="card-header">
                        <h4 class="card-title">Cấu hình vị trí ký</h4>
                    </div>
                    <div class="card-body row">
                        <div class="col-md-3 form-group">
                            <label for="signature_page">Trang ký</label>
                            <input type="number" name="signature_page" id="signature_page" class="form-control"
                                value="{{ old('signature_page', $pdfPurchaseRequest->signature_page) }}" min="1">
                            <small class="form-text text-muted">Trang PDF để đặt chữ ký.</small>
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="signature_pos_x">Vị trí X (mm)</label>
                            <input type="number" name="signature_pos_x" id="signature_pos_x" class="form-control"
                                value="{{ old('signature_pos_x', $pdfPurchaseRequest->signature_pos_x) }}" step="0.01">
                            <small class="form-text text-muted">Tọa độ ngang từ mép trái.</small>
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="signature_pos_y">Vị trí Y (mm)</label>
                            <input type="number" name="signature_pos_y" id="signature_pos_y" class="form-control"
                                value="{{ old('signature_pos_y', $pdfPurchaseRequest->signature_pos_y) }}" step="0.01">
                            <small class="form-text text-muted">Tọa độ dọc từ mép trên.</small>
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="signature_width">Chiều rộng ảnh ký (mm)</label>
                            <input type="number" name="signature_width" id="signature_width" class="form-control"
                                value="{{ old('signature_width', $pdfPurchaseRequest->signature_width) }}" step="0.01">
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="signature_height">Chiều cao ảnh ký (mm)</label>
                            <input type="number" name="signature_height" id="signature_height" class="form-control"
                                value="{{ old('signature_height', $pdfPurchaseRequest->signature_height) }}"
                                step="0.01">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Cập nhật Phiếu
                    PDF</button>
                <a href="{{ route('users.pdf-requests.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Hủy
                </a>
            </div>
        </form>
    </div>
@endsection
