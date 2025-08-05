@extends('admin')
@section('title', 'Xem Trước Phiếu Đề Nghị Từ Excel')

@section('content')
    <h2 class="mb-4">Xem Trước Dữ Liệu Phiếu Đề Nghị Từ Excel</h2>

    {{-- Phần hiển thị thông báo lỗi/cảnh báo từ quá trình importPreview --}}
    <div id="alert-messages" class="mb-3">
        {{-- Messages will be injected here by JavaScript --}}
    </div>

    @if (empty($importedPurchaseRequests))
        <div class="alert alert-warning">
            Không có phiếu đề nghị nào hợp lệ được tìm thấy để xem trước. Vui lòng quay lại trang <a
                href="{{ route('users.purchase-requests.create') }}">Tạo phiếu mới</a> và import lại.
        </div>
    @else
        <p>Tìm thấy <strong>{{ count($importedPurchaseRequests) }}</strong> phiếu đề nghị từ file Excel.</p>
        <p class="text-danger">Vui lòng kiểm tra kỹ thông tin bên dưới trước khi tạo phiếu. Bạn có thể chỉnh sửa các trường
            trước khi tạo.</p>

        <form id="create-from-import-form" action="{{ route('users.purchase-requests.create-from-import') }}" method="POST">
            @csrf
            <input type="hidden" name="session_id" value="{{ $sessionId }}">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <button type="button" class="btn btn-success btn-lg" id="submit-all-prs-btn">
                    <i class="fas fa-check-double"></i> Tạo Tất Cả Phiếu Đề Nghị Đã Xem Trước
                </button>
                <a href="{{ route('users.purchase-requests.create') }}" class="btn btn-secondary"><i
                        class="fas fa-arrow-left"></i> Quay lại Import File khác</a>
            </div>

            @foreach ($importedPurchaseRequests as $prIndex => $prData)
                <div class="card card-outline card-primary mb-4 pr-preview-card" data-pr-index="{{ $prIndex }}">
                    <div class="card-header">
                        <h3 class="card-title"><strong>Phiếu #{{ $prIndex + 1 }} - Mã PR:
                                {{ $prData['pia_code'] }}</strong></h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        {{-- Thông tin chung của phiếu --}}
                        <h5>Thông tin chung của phiếu</h5>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Mã Phiếu (PR NO)</label>
                                <input type="text" name="prs[{{ $prIndex }}][pia_code]" class="form-control"
                                    value="{{ $prData['pia_code'] }}" required readonly>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Phòng ban của bạn</label>
                                <input type="text" class="form-control"
                                    value="{{ $user->sections->first()->name ?? 'N/A' }}" disabled>
                                <input type="hidden" name="prs[{{ $prIndex }}][section_id]"
                                    value="{{ $user->sections->first()->id ?? '' }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Phòng ban yêu cầu</label>
                                <select name="prs[{{ $prIndex }}][executing_department_id]"
                                    class="form-control select2-basic" style="width: 100%;" required>
                                    @foreach ($executingDepartments as $department)
                                        <option value="{{ $department->id }}"
                                            {{ $prData['executing_department_id'] == $department->id ? 'selected' : '' }}>
                                            {{ $department->name }} ({{ $department->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Nhà máy (Plant)</label>
                                <input type="text" class="form-control" value="{{ $user->mainBranch->name ?? 'N/A' }}"
                                    disabled>
                                <input type="hidden" name="prs[{{ $prIndex }}][branch_id]"
                                    value="{{ $user->mainBranch->id ?? '' }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Ngày phát hành (SAP)</label>
                                <input type="date" name="prs[{{ $prIndex }}][sap_release_date]"
                                    class="form-control" value="{{ $prData['sap_release_date'] }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Ngày yêu cầu giao hàng*</label>
                                <input type="date" name="prs[{{ $prIndex }}][requested_delivery_date]"
                                    class="form-control" value="{{ $prData['requested_delivery_date'] }}" required>
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="priority_{{ $prIndex }}">Mức độ ưu tiên</label>
                                <select name="prs[{{ $prIndex }}][priority]" id="priority_{{ $prIndex }}"
                                    class="form-control">
                                    <option value="">-- Chọn mức độ --</option>
                                    <option value="normal"
                                        {{ ($prData['priority'] ?? old('priority')) == 'normal' ? 'selected' : '' }}>
                                        Normal/Bình thường</option>
                                    <option value="urgent"
                                        {{ ($prData['priority'] ?? old('priority')) == 'urgent' ? 'selected' : '' }}>
                                        Urgent/Khẩn cấp</option>
                                    <option value="quotation_only"
                                        {{ ($prData['priority'] ?? old('priority')) == 'quotation_only' ? 'selected' : '' }}>
                                        Quotation only/Báo giá</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Tiền tệ</label>
                                <select name="prs[{{ $prIndex }}][currency]" class="form-control" required>
                                    <option value="VND" {{ $prData['currency'] == 'VND' ? 'selected' : '' }}>VND
                                    </option>
                                    <option value="USD" {{ $prData['currency'] == 'USD' ? 'selected' : '' }}>USD
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Ngày yêu cầu (SAP Req.Date)</label>
                                <input type="date" name="prs[{{ $prIndex }}][sap_request_date]"
                                    class="form-control" value="{{ $prData['sap_request_date'] }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Số PO (PO Number)</label>
                                <input type="text" name="prs[{{ $prIndex }}][po_number]" class="form-control"
                                    value="{{ $prData['po_number'] }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Ngày PO (PO Date)</label>
                                <input type="date" name="prs[{{ $prIndex }}][po_date]" class="form-control"
                                    value="{{ $prData['po_date'] }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Người tạo (SAP Created By)</label>
                                <input type="text" name="prs[{{ $prIndex }}][sap_created_by]" class="form-control"
                                    value="{{ $prData['sap_created_by'] }}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Ghi chú (Remarks)</label>
                            <textarea name="prs[{{ $prIndex }}][remarks]" class="form-control" rows="2">{{ $prData['remarks'] }}</textarea>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1"
                                name="prs[{{ $prIndex }}][requires_director_approval]"
                                {{ $prData['requires_director_approval'] ? 'checked' : '' }}>
                            <label class="form-check-label"><strong>Yêu cầu Giám đốc (Cấp 4) duyệt</strong></label>
                        </div>

                        {{-- THÊM PHẦN HIỂN THỊ FILE ĐÍNH KÈM VÀ INPUT UPLOAD RIÊNG LẺ Ở ĐÂY --}}
                        <div class="form-group mt-3">
                            <label for="attachment_file_{{ $prIndex }}">File đính kèm</label>
                            {{-- Kiểm tra nếu có đường dẫn tạm (tức là có file từ ZIP ban đầu) --}}
                            @if ($prData['temporary_attachment_path'] ?? false)
                                {{-- Sử dụng ?? false để tránh lỗi Undefined index --}}
                                @php
                                    $fileName = pathinfo($prData['temporary_attachment_path'], PATHINFO_BASENAME);
                                    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                                    $iconClass = 'fas fa-file';
                                    if (in_array($fileExtension, ['pdf'])) {
                                        $iconClass = 'fas fa-file-pdf';
                                    } elseif (in_array($fileExtension, ['doc', 'docx'])) {
                                        $iconClass = 'fas fa-file-word';
                                    } elseif (in_array($fileExtension, ['xls', 'xlsx'])) {
                                        $iconClass = 'fas fa-file-excel';
                                    }
                                    // Đã loại bỏ image và zip
                                @endphp
                                <p class="form-control-static">
                                    <i class="{{ $iconClass }}"></i> <strong>{{ $fileName }}</strong> (Đã có từ
                                    ZIP)
                                </p>
                                {{-- Input hidden này truyền đường dẫn file tạm trở lại controller --}}
                                <input type="hidden" name="prs[{{ $prIndex }}][temp_existing_attachment_path]"
                                    value="{{ $prData['temporary_attachment_path'] }}">
                                {{-- Input file mới này cho phép upload thay thế hoặc thêm --}}
                                <input type="file" name="prs[{{ $prIndex }}][attachment_file]"
                                    id="attachment_file_{{ $prIndex }}" class="form-control mt-1"
                                    accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                                <small class="form-text text-muted">Bỏ trống nếu không muốn thay đổi. Nếu chọn file mới,
                                    file từ ZIP sẽ bị ghi đè.</small>
                            @else
                                <p class="form-control-static text-muted">Chưa có file đính kèm.</p>
                                <input type="file" name="prs[{{ $prIndex }}][attachment_file]"
                                    id="attachment_file_{{ $prIndex }}" class="form-control mt-1"
                                    accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                                <small class="form-text text-muted">Chọn file để đính kèm cho phiếu này (tùy chọn).</small>
                                <input type="hidden" name="prs[{{ $prIndex }}][temp_existing_attachment_path]"
                                    value="">
                            @endif
                        </div>

                        <h5 class="mt-4">Chi tiết hàng hóa</h5>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-bordered table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Mã hàng*</th>
                                        <th>Tên hàng*</th>
                                        <th>Mã hàng cũ</th>

                                        <th>SL Đặt*</th>
                                        <th>ĐV Đặt</th>
                                        <th>SL Kho</th>
                                        <th>ĐV Kho</th>
                                        <th>Giá R3</th>
                                        <th>Giá dự tính*</th>
                                        <th>Thành tiền</th>
                                        <th>Mã phòng SD</th>
                                        <th>Plant hệ</th>

                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($prData['items'] as $itemIndex => $item)
                                        <tr>
                                            <td>{{ $itemIndex + 1 }}</td>
                                            <td><input type="text"
                                                    name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][item_code]"
                                                    class="form-control form-control-sm" value="{{ $item['item_code'] }}"
                                                    required></td>
                                            <td><input type="text"
                                                    name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][item_name]"
                                                    class="form-control form-control-sm" value="{{ $item['item_name'] }}"
                                                    required></td>
                                            <td><input type="text"
                                                    name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][old_item_code]"
                                                    class="form-control form-control-sm"
                                                    value="{{ $item['old_item_code'] }}"></td>
                                            <td><input type="number"
                                                    name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][order_quantity]"
                                                    class="form-control form-control-sm item-quantity-input"
                                                    value="{{ $item['order_quantity'] }}" required step="any"
                                                    min="0.001"></td>
                                            <td><input type="text"
                                                    name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][order_unit]"
                                                    class="form-control form-control-sm"
                                                    value="{{ $item['order_unit'] }}"></td>
                                            <td><input type="number"
                                                    name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][inventory_quantity]"
                                                    class="form-control form-control-sm item-inventory-quantity-input"
                                                    value="{{ $item['inventory_quantity'] }}" step="any"
                                                    min="0"></td>
                                            <td><input type="text"
                                                    name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][inventory_unit]"
                                                    class="form-control form-control-sm"
                                                    value="{{ $item['inventory_unit'] }}"></td>
                                            <td><input type="number"
                                                    name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][r3_price]"
                                                    class="form-control form-control-sm" value="{{ $item['r3_price'] }}"
                                                    step="any" min="0"></td>
                                            <td><input type="number"
                                                    name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][estimated_price]"
                                                    class="form-control form-control-sm item-price-input"
                                                    value="{{ $item['estimated_price'] }}" required step="any"
                                                    min="0"></td>
                                            <td><input type="text"
                                                    class="form-control form-control-sm item-subtotal-display"
                                                    value="{{ number_format($item['subtotal'], 2, '.', ',') }}" readonly>
                                                {{-- THÊM input hidden cho subtotal --}}
                                                <input type="hidden"
                                                    name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][subtotal]"
                                                    class="item-subtotal-hidden" value="{{ $item['subtotal'] }}">
                                            </td>
                                            <td><input type="text"
                                                    name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][using_dept_code]"
                                                    class="form-control form-control-sm"
                                                    value="{{ $item['using_dept_code'] }}"></td>
                                            <td><input type="text"
                                                    name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][plant_system]"
                                                    class="form-control form-control-sm"
                                                    value="{{ $item['plant_system'] }}"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="row mb-0">
                                    <dt class="col-sm-6 text-end">Tổng SL đặt hàng:</dt>
                                    <dd class="col-sm-6 fw-bold pr-total-order-quantity-display">
                                        {{ number_format($prData['total_order_quantity'], 3, '.', ',') }}</dd>
                                    <dt class="col-sm-6 text-end">Tổng SL tồn kho:</dt>
                                    <dd class="col-sm-6 fw-bold pr-total-inventory-quantity-display">
                                        {{ number_format($prData['total_inventory_quantity'], 3, '.', ',') }}</dd>
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <dl class="row mb-0">
                                    <dt class="col-sm-8 text-end">Tổng cộng (Grand Total):</dt>
                                    <dd class="col-sm-4 fw-bold fs-5 text-danger pr-total-amount-display">
                                        {{ number_format($prData['total_amount'], 2, '.', ',') }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </form>

        <div class="d-flex justify-content-between align-items-center mt-4">
            <button type="button" class="btn btn-success btn-lg" id="submit-all-prs-btn-bottom">
                <i class="fas fa-check-double"></i> Tạo Tất Cả Phiếu Đề Nghị Đã Xem Trước
            </button>
            <a href="{{ route('users.purchase-requests.create') }}" class="btn btn-secondary"><i
                    class="fas fa-arrow-left"></i> Quay lại Import File khác</a>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // Kích hoạt Select2 cho các select phòng ban yêu cầu
            $('.select2-basic').select2({
                theme: 'bootstrap-5',
                placeholder: 'Chọn hoặc tìm kiếm phòng ban...',
                allowClear: true
            });

            // Hàm tính toán tổng cho TỪNG PHIẾU
            function calculatePrTotals(prCard) {
                let currentPrGrandTotal = 0;
                let currentPrTotalOrderQty = 0;
                let currentPrTotalInventoryQty = 0;

                prCard.find('tbody tr').each(function() {
                    const row = $(this);
                    const orderQty = parseFloat(row.find('.item-quantity-input').val()) || 0;
                    const inventoryQty = parseFloat(row.find('.item-inventory-quantity-input').val()) || 0;
                    const price = parseFloat(row.find('.item-price-input').val()) || 0;
                    const subtotal = orderQty * price;

                    row.find('.item-subtotal-display').val(subtotal.toLocaleString('vi-VN', {
                        minimumFractionDigits: 2
                    }));
                    currentPrGrandTotal += subtotal;
                    currentPrTotalOrderQty += orderQty;
                    currentPrTotalInventoryQty += inventoryQty;
                });

                prCard.find('.pr-total-amount-display').text(currentPrGrandTotal.toLocaleString('vi-VN', {
                    minimumFractionDigits: 2
                }));
                prCard.find('.pr-total-order-quantity-display').text(currentPrTotalOrderQty.toLocaleString(
                'vi-VN', {
                    minimumFractionDigits: 3
                }));
                prCard.find('.pr-total-inventory-quantity-display').text(currentPrTotalInventoryQty.toLocaleString(
                    'vi-VN', {
                        minimumFractionDigits: 3
                    }));
            }

            // Gắn sự kiện input cho các trường số lượng/giá trong mỗi phiếu
            $('#create-from-import-form').on('input',
                '.item-quantity-input, .item-price-input, .item-inventory-quantity-input',
                function() {
                    const prCard = $(this).closest('.pr-preview-card');
                    calculatePrTotals(prCard);
                });

            // Tính toán tổng ban đầu cho tất cả các phiếu khi trang tải
            $('.pr-preview-card').each(function() {
                calculatePrTotals($(this));
            });

            // Xử lý khi người dùng nhấn nút "Tạo Tất Cả Phiếu"
            $('#submit-all-prs-btn, #submit-all-prs-btn-bottom').on('click', function() {
                Swal.fire({
                    title: 'Bạn có chắc chắn muốn tạo các phiếu này?',
                    text: "Các phiếu sẽ được lưu vào hệ thống và bắt đầu quy trình duyệt.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Có, tạo tất cả!',
                    cancelButtonText: 'Hủy bỏ'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = $('#create-from-import-form');
                        const formData = form.serialize(); // Lấy tất cả dữ liệu từ form

                        Swal.fire({
                            title: 'Đang tạo phiếu...',
                            html: 'Vui lòng chờ. Quá trình này có thể mất một chút thời gian tùy thuộc vào số lượng phiếu.',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        $.ajax({
                            url: form.attr('action'),
                            method: form.attr('method'),
                            data: formData,
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        title: 'Thành công!',
                                        html: response.message + (response
                                            .errors.length > 0 ?
                                            '<br><br><strong>Chi tiết cảnh báo:</strong><br>' +
                                            response.errors.join('<br>') :
                                            ''),
                                        icon: 'success',
                                        showConfirmButton: false,
                                        timer: 3000
                                    }).then(() => {
                                        window.location.href = response
                                            .redirect_url;
                                    });
                                } else {
                                    let errorHtml = '<ul>';
                                    if (response.message) {
                                        errorHtml += `<li>${response.message}</li>`;
                                    }
                                    if (response.errors && response.errors.length > 0) {
                                        response.errors.forEach(err => {
                                            errorHtml += `<li>${err}</li>`;
                                        });
                                    }
                                    errorHtml += '</ul>';

                                    Swal.fire({
                                        title: 'Lỗi!',
                                        html: 'Đã xảy ra lỗi khi tạo phiếu:' +
                                            errorHtml,
                                        icon: 'error'
                                    });
                                }
                            },
                            error: function(xhr) {
                                let errorMessages = 'Đã xảy ra lỗi không xác định.';
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    errorMessages = xhr.responseJSON.message;
                                    if (xhr.responseJSON.errors) {
                                        errorMessages += '<ul>';
                                        for (const key in xhr.responseJSON.errors) {
                                            errorMessages +=
                                                `<li>${xhr.responseJSON.errors[key]}</li>`;
                                        }
                                        errorMessages += '</ul>';
                                    }
                                }
                                Swal.fire({
                                    title: 'Lỗi!',
                                    html: errorMessages,
                                    icon: 'error'
                                });
                            }
                        });
                    }
                });
            });

            // Check if there are any messages passed from importPreview via session and display them
            const importedMessages = @json(session('imported_messages') ?? []);
            if (importedMessages.length > 0) {
                let html = '';
                importedMessages.forEach(msg => {
                    if (msg.type === 'error') {
                        html +=
                            `<div class="alert alert-danger alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>${msg.text}</div>`;
                    } else if (msg.type === 'warning') {
                        html +=
                            `<div class="alert alert-warning alert-dismissible"><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>${msg.text}</div>`;
                    }
                });
                $('#alert-messages').html(html);
                {{ session()->forget('imported_messages') }}; // Clear messages from session after displaying
            }

        });
    </script>
@endpush
