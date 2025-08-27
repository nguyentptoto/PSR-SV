@extends('admin')
@section('title', 'Tạo Phiếu Đề Nghị PDF Mới')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                 @if (session('error'))
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        <h5 class="alert-heading"><i class="icon fas fa-ban"></i> Lỗi!</h5>
                        {{ session('error') }}
                    </div>
                @endif
                <div class="card card-outline card-info">
                    <div class="card-header">
                        <h3 class="card-title">Thông tin Phiếu Đề Nghị PDF</h3>
                    </div>
                    <form action="{{ route('users.pdf-requests.store') }}" method="POST" enctype="multipart/form-data" id="pdf-upload-form">
                        @csrf
                        <div class="card-body">
                            <div class="form-group mb-3 pb-3 border-bottom">
                                <label for="pdf_files_input">Chọn File PDF (có thể chọn nhiều file)</label>
                                {{-- DÒNG NÀY ĐÃ ĐƯỢC SỬA: THÊM name="pdf_files[]" --}}
                                <input type="file" id="pdf_files_input" class="form-control" name="pdf_files[]" multiple accept=".pdf">
                                <small class="form-text text-muted">Mã phiếu sẽ được tự động điền từ tên file (ví dụ: PR_001.pdf -> mã phiếu: 001).</small>
                                @error('pdf_files')
                                    <div class="text-danger mt-1">Vui lòng chọn ít nhất một file PDF.</div>
                                @enderror
                            </div>

                            <div id="pdf-upload-container">
                                {{-- Khu vực này sẽ được JavaScript tự động điền các cặp input --}}
                            </div>

                            <div class="card-header border-bottom">
                                <h5 class="card-title">Cài đặt vị trí chữ ký</h5>
                            </div>
                            <small class="text-muted mb-3">Các cài đặt chữ ký này sẽ được áp dụng cho tất cả các phiếu đã tạo.</small>
                            <div class="row mt-3">
                                <div class="col-md-2 form-group">
                                    <label for="signature_page">Trang ký</label>
                                    <input type="number" name="signature_page" id="signature_page" class="form-control"
                                        value="{{ old('signature_page', 1) }}" min="1">
                                    @error('signature_page')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-2 form-group">
                                    <label for="signature_pos_x">Vị trí X (mm)</label>
                                    <input type="number" name="signature_pos_x" id="signature_pos_x" class="form-control"
                                        value="{{ old('signature_pos_x', $defaultSignaturePositions['pos_x']) }}"
                                        step="0.01">
                                    @error('signature_pos_x')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-2 form-group">
                                    <label for="signature_pos_y">Vị trí Y (mm)</label>
                                    <input type="number" name="signature_pos_y" id="signature_pos_y" class="form-control"
                                        value="{{ old('signature_pos_y', $defaultSignaturePositions['pos_y']) }}"
                                        step="0.01">
                                    @error('signature_pos_y')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="signature_width">Chiều rộng ảnh ký (mm)</label>
                                    <input type="number" name="signature_width" id="signature_width" class="form-control"
                                        value="{{ old('signature_width', $defaultSignaturePositions['width']) }}"
                                        step="0.01">
                                    @error('signature_width')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="signature_height">Chiều cao ảnh ký (mm)</label>
                                    <input type="number" name="signature_height" id="signature_height" class="form-control"
                                        value="{{ old('signature_height', $defaultSignaturePositions['height']) }}"
                                        step="0.01">
                                    @error('signature_height')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-primary">Tạo Phiếu</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const pdfFileInput = document.getElementById('pdf_files_input');
    const pdfUploadContainer = document.getElementById('pdf-upload-container');

    pdfFileInput.addEventListener('change', function (event) {
        pdfUploadContainer.innerHTML = ''; // Xóa nội dung cũ
        let fileCounter = 0;

        Array.from(event.target.files).forEach(file => {
            fileCounter++;
            const fileName = file.name;
            let piaCode = '';

            // Tách mã phiếu từ tên file
            const prCodeRegex = /PR_(.*?)\./;
            const match = fileName.match(prCodeRegex);
            if (match && match[1]) {
                piaCode = match[1];
            } else {
                piaCode = fileName.split('.').slice(0, -1).join('.'); // Lấy tên file không có đuôi
            }

            const newRow = `
                <div class="row pdf-upload-group mb-3 p-3 border rounded">
                    <div class="col-md-12 mb-3">
                        <span class="badge bg-secondary">Phiếu #${fileCounter}</span>
                        <button type="button" class="btn btn-sm btn-danger remove-pdf-row float-end">Xóa</button>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Mã Phiếu (PR NO)</label>
                        {{-- input này chỉ dùng để hiển thị và chỉnh sửa mã phiếu, không gửi file --}}
                        <input type="text" name="pia_codes[${fileCounter - 1}]" class="form-control pia_code" value="${piaCode}" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Tên File PDF</label>
                        <input type="text" class="form-control" value="${fileName}" readonly>
                    </div>
                    <div class="col-md-6 form-group mt-3">
                        <label for="remarks_${fileCounter}">Ghi chú</label>
                        <textarea name="remarks_per_file[${fileCounter - 1}]" id="remarks_${fileCounter}" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-md-6 form-group mt-3">
                        <label for="attachment_${fileCounter}">File đính kèm (tùy chọn)</label>
                        {{-- Input này sẽ được xử lý riêng --}}
                        <input type="file" name="attachments[${fileCounter - 1}]" id="attachment_${fileCounter}" class="form-control"
                            accept=".pdf, .xlsx, .xls, .doc, .docx">
                    </div>
                    <div class="col-md-12 form-check mt-3 mb-3">
                        <input class="form-check-input" type="checkbox" name="requires_director_approval_per_file[${fileCounter - 1}]"
                            id="requires_director_approval_${fileCounter}" value="1">
                        <label class="form-check-label" for="requires_director_approval_${fileCounter}">
                            Yêu cầu Giám đốc phê duyệt (Cấp 4)
                        </label>
                    </div>
                </div>
            `;
            pdfUploadContainer.insertAdjacentHTML('beforeend', newRow);
        });
    });

    // Xử lý sự kiện xóa cặp input
    pdfUploadContainer.addEventListener('click', function(event) {
        if (event.target.classList.contains('remove-pdf-row')) {
            const rowToRemove = event.target.closest('.pdf-upload-group');
            const fileName = rowToRemove.querySelector('input[type="text"][readonly]').value;

            // Lấy input file gốc
            const existingFileInput = document.getElementById('pdf_files_input');
            const updatedDataTransfer = new DataTransfer();

            // Tạo danh sách file mới, loại bỏ file đã bị xóa
            Array.from(existingFileInput.files).forEach(file => {
                if (file.name !== fileName) {
                    updatedDataTransfer.items.add(file);
                }
            });

            // Gán lại danh sách file đã cập nhật vào input file gốc
            existingFileInput.files = updatedDataTransfer.files;

            // Xóa hàng trên giao diện
            rowToRemove.remove();

            // Nếu không còn phiếu nào, reset input file
            if (pdfUploadContainer.querySelectorAll('.pdf-upload-group').length === 0) {
                existingFileInput.value = '';
            }
        }
    });
});
</script>
@endpush
