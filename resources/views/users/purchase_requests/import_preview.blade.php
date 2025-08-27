@extends('admin')
@section('title', 'Xem Trước Phiếu Đề Nghị Từ Excel')

@section('content')
    <h2 class="mb-4">Xem Trước Dữ Liệu Phiếu Đề Nghị Từ Excel</h2>

    <div id="alert-messages" class="mb-3">
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

        <form id="create-from-import-form" action="{{ route('users.purchase-requests.create-from-import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="session_id" value="{{ $sessionId }}">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <button type="button" class="btn btn-success btn-lg" id="submit-all-prs-btn">
                    <i class="fas fa-check-double"></i> Tạo Tất Cả Phiếu Đề Nghị Đã Xem Trước
                </button>
                <a href="{{ route('users.purchase-requests.create') }}" class="btn btn-secondary"><i
                        class="fas fa-arrow-left"></i> Quay lại Import File khác</a>
            </div>

            @foreach ($importedPurchaseRequests as $prIndex => $prWrapper)
                @php
                    $prData = $prWrapper['pr_data'];
                    $items = $prWrapper['items'];
                @endphp
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
                        <h5>Thông tin chung của phiếu</h5>
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label>Mã Phiếu (PR NO)</label>
                                <input type="text" name="prs[{{ $prIndex }}][pia_code]" class="form-control"
                                    value="{{ $prData['pia_code'] }}" required readonly>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Ngày yêu cầu (SAP Req.Date)</label>
                                <input type="date" name="prs[{{ $prIndex }}][sap_request_date]"
                                    class="form-control" value="{{ optional($prData['sap_request_date'])->format('Y-m-d') }}">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Ngày yêu cầu giao hàng*</label>
                                <input type="date" name="prs[{{ $prIndex }}][requested_delivery_date]"
                                    class="form-control" value="{{ optional($prData['requested_delivery_date'])->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Tiền tệ</label>
                                <select name="prs[{{ $prIndex }}][currency]" class="form-control" required>
                                    <option value="VND" {{ ($prData['currency'] ?? 'VND') == 'VND' ? 'selected' : '' }}>VND
                                    </option>
                                    <option value="USD" {{ ($prData['currency'] ?? '') == 'USD' ? 'selected' : '' }}>USD
                                    </option>
                                </select>
                            </div>
                             <div class="col-md-3 form-group">
                                <label>Người tạo (SAP Created By)</label>
                                <input type="text" name="prs[{{ $prIndex }}][sap_created_by]" class="form-control"
                                    value="{{ $prData['sap_created_by'] ?? '' }}">
                            </div>
                        </div>

                        <h5 class="mt-4">Chi tiết hàng hóa</h5>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-bordered table-sm" style="min-width: 1600px;">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th style="width: 3%;">#</th>
                                        <th style="width: 10%;">Mã hàng*</th>
                                        <th style="width: 15%;">Tên hàng*</th>
                                        <th style="width: 10%;">Mã hàng cũ</th>
                                        <th style="width: 7%;">SL Đặt*</th>
                                        <th style="width: 5%;">ĐV Đặt</th>
                                        <th style="width: 7%;">SL Kho</th>
                                        <th style="width: 5%;">ĐV Kho</th>
                                        <th style="width: 8%;">Giá R3</th>
                                        <th style="width: 8%;">Giá dự tính*</th>
                                        <th style="width: 10%;">Thành tiền</th>
                                        <th style="width: 8%;">Mã phòng SD</th>
                                        <th style="width: 8%;">Plant hệ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $itemIndex => $item)
                                        <tr>
                                            <td class="text-center">{{ $itemIndex + 1 }}</td>
                                            <td><input type="text" name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][item_code]" class="form-control form-control-sm" value="{{ $item['item_code'] }}" required></td>
                                            <td><input type="text" name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][item_name]" class="form-control form-control-sm" value="{{ $item['item_name'] }}" required></td>
                                            <td><input type="text" name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][old_item_code]" class="form-control form-control-sm" value="{{ $item['old_item_code'] ?? '' }}"></td>
                                            <td><input type="number" name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][order_quantity]" class="form-control form-control-sm item-quantity-input" value="{{ $item['order_quantity'] }}" required step="any" min="0.001"></td>
                                            <td><input type="text" name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][order_unit]" class="form-control form-control-sm" value="{{ $item['order_unit'] }}"></td>
                                            <td><input type="number" name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][inventory_quantity]" class="form-control form-control-sm item-inventory-quantity-input" value="{{ $item['inventory_quantity'] ?? 0 }}" step="any" min="0"></td>
                                            <td><input type="text" name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][inventory_unit]" class="form-control form-control-sm" value="{{ $item['inventory_unit'] ?? '' }}"></td>
                                            <td><input type="number" name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][r3_price]" class="form-control form-control-sm" value="{{ $item['r3_price'] ?? 0 }}" step="any" min="0"></td>
                                            <td><input type="number" name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][estimated_price]" class="form-control form-control-sm item-price-input" value="{{ $item['estimated_price'] }}" required step="any" min="0"></td>
                                            <td><input type="text" class="form-control form-control-sm item-subtotal-display" value="{{ number_format($item['subtotal'], 2, '.', ',') }}" readonly></td>
                                            <td><input type="text" name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][using_dept_code]" class="form-control form-control-sm" value="{{ $item['using_dept_code'] ?? '' }}"></td>
                                            <td><input type="text" name="prs[{{ $prIndex }}][items][{{ $itemIndex }}][plant_system]" class="form-control form-control-sm" value="{{ $item['plant_system'] ?? '' }}"></td>
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
                                    <dd class="col-sm-6 fw-bold pr-total-order-quantity-display">{{ number_format($prData['total_order_quantity'], 3, '.', ',') }}</dd>
                                    <dt class="col-sm-6 text-end">Tổng SL tồn kho:</dt>
                                    <dd class="col-sm-6 fw-bold pr-total-inventory-quantity-display">{{ number_format($prData['total_inventory_quantity'], 3, '.', ',') }}</dd>
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <dl class="row mb-0">
                                    <dt class="col-sm-8 text-end">Tổng cộng (Grand Total):</dt>
                                    <dd class="col-sm-4 fw-bold fs-5 text-danger pr-total-amount-display">{{ number_format($prData['total_amount'], 2, '.', ',') }}</dd>
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
            $('.select2-basic').select2({
                theme: 'bootstrap-5',
                placeholder: 'Chọn hoặc tìm kiếm phòng ban...',
                allowClear: true
            });

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

            $('#create-from-import-form').on('input',
                '.item-quantity-input, .item-price-input, .item-inventory-quantity-input',
                function() {
                    const prCard = $(this).closest('.pr-preview-card');
                    calculatePrTotals(prCard);
                });

            $('.pr-preview-card').each(function() {
                calculatePrTotals($(this));
            });

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
                        const formData = new FormData(form[0]);

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
                            processData: false,
                            contentType: false,
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
                @php session()->forget('imported_messages'); @endphp
            }
        });
    </script>
@endpush
