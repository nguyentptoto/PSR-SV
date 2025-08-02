@extends('admin')
@section('title', 'Tạo Phiếu Đề Nghị PDF Mới')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-info">
                    <div class="card-header">
                        <h3 class="card-title">Thông tin Phiếu Đề Nghị PDF</h3>
                    </div>
                    <form action="{{ route('users.pdf-requests.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="pia_code">Mã Phiếu (PR NO)</label>
                                    <input type="text" name="pia_code" id="pia_code" class="form-control"
                                        value="{{ old('pia_code') }}" required>
                                    @error('pia_code')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="pdf_file">File PDF</label>
                                    <input type="file" name="pdf_file" id="pdf_file" class="form-control" required>
                                    @error('pdf_file')
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="remarks">Ghi chú</label>
                                <textarea name="remarks" id="remarks" class="form-control" rows="3">{{ old('remarks') }}</textarea>
                                @error('remarks')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="attachment">File đính kèm (tùy chọn)</label>
                                <input type="file" name="attachment" id="attachment" class="form-control"
                                    accept=".pdf, .xlsx, .xls, .doc, .docx">
                            </div>
                            <input type="hidden" name="requires_director_approval" value="0">

                            <div class="form-check mt-3 mb-3">
                                <input class="form-check-input" type="checkbox" name="requires_director_approval"
                                    id="requires_director_approval" value="1"
                                    {{ old('requires_director_approval') == 1 ? 'checked' : '' }}>
                                <label class="form-check-label" for="requires_director_approval">
                                    Yêu cầu Giám đốc phê duyệt (Cấp 4)
                                </label>
                            </div>

                            <div class="card-header border-bottom">
                                <h5 class="card-title">Cài đặt vị trí chữ ký</h5>
                            </div>
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
