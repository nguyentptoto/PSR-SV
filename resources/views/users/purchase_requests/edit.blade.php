@extends('admin')
@section('title', 'Chỉnh sửa Phiếu Đề Nghị: ' . $purchaseRequest->pia_code)

@section('content')
<form action="{{ route('users.purchase-requests.update', $purchaseRequest->id) }}" method="POST" id="pr-form" enctype="multipart/form-data">
    @csrf
    @method('PUT') {{-- Bắt buộc cho việc cập nhật --}}

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Card Thông tin chung --}}
    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Chỉnh sửa Thông tin chung</h3>
        </div>
        <div class="card-body">
            {{-- ... Phần thông tin chung không thay đổi ... --}}
            <div class="row">
                <div class="col-md-3 form-group">
                    <label for="pia_code">Mã Phiếu (PR NO)*</label>
                    <input type="text" name="pia_code" class="form-control" value="{{ old('pia_code', $purchaseRequest->pia_code) }}" required>
                </div>
                <div class="col-md-3 form-group">
                    <label for="section_id">Phòng ban thực hiện*</label>
                    <select name="section_id" class="form-control" required>
                        @foreach($user->sections as $section)
                            <option value="{{ $section->id }}" {{ old('section_id', $purchaseRequest->section_id) == $section->id ? 'selected' : '' }}>{{ $section->name }}</option>
                        @endforeach
                    </select>
                </div>
                 <div class="col-md-3 form-group">
                    <label for="executing_department_id">Phòng ban yêu cầu*</label>
                    <select name="executing_department_id" id="executing-department-select" class="form-control" required style="width: 100%;">
                        <option></option>
                        @foreach($executingDepartments as $department)
                            <option value="{{ $department->id }}" {{ old('executing_department_id', $purchaseRequest->executing_department_id) == $department->id ? 'selected' : '' }}>
                                {{ $department->name }} ({{ $department->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label>Nhà máy (Plant)*</label>
                    <input type="text" class="form-control" value="{{ $purchaseRequest->branch->name }}" disabled>
                    <input type="hidden" name="branch_id" value="{{ $purchaseRequest->branch_id }}">
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 form-group">
                    <label for="sap_release_date">Ngày phát hành (SAP)</label>
                    <input type="date" name="sap_release_date" class="form-control" value="{{ old('sap_release_date', $purchaseRequest->sap_release_date ? $purchaseRequest->sap_release_date->format('Y-m-d') : '') }}">
                </div>
                <div class="col-md-4 form-group">
                    <label for="requested_delivery_date">Ngày yêu cầu giao hàng*</label>
                    <input type="date" name="requested_delivery_date" class="form-control" value="{{ old('requested_delivery_date', $purchaseRequest->requested_delivery_date ? $purchaseRequest->requested_delivery_date->format('Y-m-d') : '') }}" required>
                </div>
                <div class="col-md-4 form-group">
                    <label for="currency">Tiền tệ*</label>
                    <select name="currency" class="form-control" required>
                        <option value="VND" {{ old('currency', $purchaseRequest->currency) == 'VND' ? 'selected' : '' }}>VND</option>
                        <option value="USD" {{ old('currency', $purchaseRequest->currency) == 'USD' ? 'selected' : '' }}>USD</option>
                    </select>
                </div>

                <div class="col-md-4 form-group">
                    <label for="priority">Mức độ ưu tiên</label>
                    <select name="priority" class="form-control">
                        <option value="">-- Chọn mức độ --</option>
                        <option value="normal" {{ old('priority', $purchaseRequest->priority) == 'normal' ? 'selected' : '' }}>Normal/Bình thường</option>
                        <option value="urgent" {{ old('priority', $purchaseRequest->priority) == 'urgent' ? 'selected' : '' }}>Urgent/Khẩn cấp</option>
                        <option value="quotation_only" {{ old('priority', $purchaseRequest->priority) == 'quotation_only' ? 'selected' : '' }}>Quotation only/Báo giá</option>
                    </select>
                </div>
            </div>
              {{-- ✅ THÊM MỚI: Ô nhập Ghi chú (Remarks) --}}
            <div class="form-group mt-2">
                <label for="remarks">Ghi chú (Remarks)</label>
                <textarea name="remarks" id="remarks" class="form-control" rows="3">{{ old('remarks', $purchaseRequest->remarks) }}</textarea>
            </div>
              {{-- ✅ THÊM MỚI: Hiển thị file cũ và ô upload file mới --}}
            <div class="form-group mt-2">
                <label for="attachment_file">File đính kèm (nếu muốn thay đổi)</label>
                @if($purchaseRequest->attachment_path)
                    <p class="mt-2"><strong>File hiện tại:</strong>
                        <a href="{{ Storage::url($purchaseRequest->attachment_path) }}" target="_blank">Xem file</a>
                    </p>
                @endif
                <input type="file" name="attachment_file" id="attachment_file" class="form-control">
            </div>
            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" value="1" id="requires_director_approval" name="requires_director_approval" {{ old('requires_director_approval', $purchaseRequest->requires_director_approval) ? 'checked' : '' }}>
                <label class="form-check-label" for="requires_director_approval">
                    <strong>Yêu cầu Giám đốc (Cấp 4) duyệt</strong>
                </label>
            </div>
        </div>
    </div>

    {{-- Card Chi tiết hàng hóa --}}
    <div class="card card-info">
        <div class="card-header"><h3 class="card-title">Chi tiết hàng hóa</h3></div>
        <div class="card-body">
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-bordered" style="min-width: 1600px;">
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
                            <th style="width: 3%;"></th>
                        </tr>
                    </thead>
                    <tbody id="items-table-body">
                        @foreach(old('items', $purchaseRequest->items->toArray()) as $index => $item)
                        <tr>
                            <td class="text-center align-middle">{{ $index + 1 }}</td>
                            <td><input type="text" name="items[{{$index}}][item_code]" class="form-control" value="{{ $item['item_code'] ?? '' }}" required></td>
                            <td><input type="text" name="items[{{$index}}][item_name]" class="form-control" value="{{ $item['item_name'] ?? '' }}" required></td>
                            <td><input type="text" name="items[{{$index}}][old_item_code]" class="form-control" value="{{ $item['old_item_code'] ?? '' }}"></td>
                            <td><input type="number" name="items[{{$index}}][order_quantity]" class="form-control quantity-input" value="{{ $item['order_quantity'] ?? '' }}" required step="any" min="0"></td>
                            <td><input type="text" name="items[{{$index}}][order_unit]" class="form-control" value="{{ $item['order_unit'] ?? 'PC' }}"></td>

                            {{-- ✅ SỬA LỖI: Thêm class 'inventory-quantity-input' --}}
                            <td><input type="number" name="items[{{$index}}][inventory_quantity]" class="form-control inventory-quantity-input" value="{{ $item['inventory_quantity'] ?? '' }}" step="any" min="0"></td>

                            <td><input type="text" name="items[{{$index}}][inventory_unit]" class="form-control" value="{{ $item['inventory_unit'] ?? 'PC' }}"></td>
                            <td><input type="number" name="items[{{$index}}][r3_price]" class="form-control" value="{{ $item['r3_price'] ?? '' }}" step="any" min="0"></td>
                            <td><input type="number" name="items[{{$index}}][estimated_price]" class="form-control price-input" value="{{ $item['estimated_price'] ?? '' }}" required step="any" min="0"></td>
                            <td><input type="text" name="items[{{$index}}][subtotal_display]" class="form-control subtotal-display" readonly></td>
                            <td><input type="text" name="items[{{$index}}][using_dept_code]" class="form-control" value="{{ $item['using_dept_code'] ?? '' }}"></td>
                            <td><input type="text" name="items[{{$index}}][plant_system]" class="form-control" value="{{ $item['plant_system'] ?? '' }}"></td>
                            <td class="text-center align-middle">
                                @if($index > 0 || count(old('items', $purchaseRequest->items)) > 1)
                                <button type="button" class="btn btn-danger btn-sm remove-item-btn"><i class="fas fa-trash"></i></button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-secondary mt-2" id="add-item-btn"><i class="fas fa-plus"></i> Thêm dòng</button>
        </div>
           {{-- ✅ THÊM MỚI: Card Footer với các trường tổng --}}
        <div class="card-footer">
            <div class="row">
                <div class="col-md-6">
                    <dl class="row">
                        <dt class="col-sm-6 text-end">Tổng số lượng đặt hàng:</dt>
                        <dd class="col-sm-6 fw-bold" id="total-order-quantity-display">0</dd>
                        <input type="hidden" name="total_order_quantity" id="total-order-quantity-hidden">
                        <dt class="col-sm-6 text-end">Tổng số lượng tồn kho:</dt>
                       <dd class="col-sm-6 fw-bold" id="total-inventory-quantity-display">0</dd>
                        <input type="hidden" name="total_inventory_quantity" id="total-inventory-quantity-hidden">
                    </dl>
                </div>
                <div class="col-md-6">
                    <dl class="row">
                        <dt class="col-sm-8 text-end">Tổng cộng (Grand Total):</dt>
                        <dd class="col-sm-4 fw-bold fs-5 text-danger" id="total_amount_display">0</dd>
                        <input type="hidden" name="total_amount" id="total_amount_hidden">
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="text-left mt-3 mb-4">
        <button type="submit" class="btn btn-primary btn-lg">Cập nhật Phiếu Đề Nghị</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let itemIndex = {{ count(old('items', $purchaseRequest->items)) }};
// ✅ SỬA ĐỔI: Khởi tạo và gán giá trị cho Select2
    const selectElement = $('#executing-department-select');
    selectElement.select2({
        theme: 'bootstrap-5',
        placeholder: 'Chọn hoặc tìm kiếm phòng ban...',
        allowClear: true
    });

    // Lấy giá trị cũ hoặc giá trị từ database
    let selectedExecutingDeptId = '{{ old('executing_department_id', $purchaseRequest->executing_department_id) }}';

    // Gán giá trị và kích hoạt sự kiện 'change' của Select2
    if (selectedExecutingDeptId) {
        selectElement.val(selectedExecutingDeptId).trigger('change');
    }
    // ✅ SỬA ĐỔI: Cập nhật hàm tính toán
    function calculateTotals() {
        let grandTotal = 0;
        let totalOrderQty = 0;
        let totalInventoryQty = 0;

        $('#items-table-body tr').each(function() {
            const row = $(this);
            const orderQty = parseFloat(row.find('.quantity-input').val()) || 0;
            const inventoryQty = parseFloat(row.find('.inventory-quantity-input').val()) || 0;
            const price = parseFloat(row.find('.price-input').val()) || 0;
            const subtotal = orderQty * price;

            row.find('.subtotal-display').val(subtotal.toLocaleString('vi-VN'));
            grandTotal += subtotal;
            totalOrderQty += orderQty;
            totalInventoryQty += inventoryQty;
        });

        // Hiển thị giá trị đã định dạng cho người dùng
        $('#total_amount_display').text(grandTotal.toLocaleString('vi-VN'));
        $('#total-order-quantity-display').text(totalOrderQty.toLocaleString('vi-VN'));
        $('#total-inventory-quantity-display').text(totalInventoryQty.toLocaleString('vi-VN'));

        // Gán giá trị SỐ THÔ vào các ô input ẩn để gửi đi
        $('#total_amount_hidden').val(grandTotal);
        $('#total-order-quantity-hidden').val(totalOrderQty);
        $('#total-inventory-quantity-hidden').val(totalInventoryQty);
    }

    $('#add-item-btn').on('click', function() {
        const newRow = `
            <tr>
                <td class="text-center align-middle">${itemIndex + 1}</td>
                <td><input type="text" name="items[${itemIndex}][item_code]" class="form-control" required></td>
                <td><input type="text" name="items[${itemIndex}][item_name]" class="form-control" required></td>
                <td><input type="text" name="items[${itemIndex}][old_item_code]" class="form-control"></td>
                <td><input type="number" name="items[${itemIndex}][order_quantity]" class="form-control quantity-input" required step="any" min="0"></td>
                <td><input type="text" name="items[${itemIndex}][order_unit]" class="form-control" value="PC"></td>
                <td><input type="number" name="items[${itemIndex}][inventory_quantity]" class="form-control" step="any" min="0"></td>
                <td><input type="text" name="items[${itemIndex}][inventory_unit]" class="form-control" value="PC"></td>
                <td><input type="number" name="items[${itemIndex}][r3_price]" class="form-control" step="any" min="0"></td>
                <td><input type="number" name="items[${itemIndex}][estimated_price]" class="form-control price-input" required step="any" min="0"></td>
                <td><input type="text" name="items[${itemIndex}][subtotal_display]" class="form-control subtotal-display" readonly></td>
                <td><input type="text" name="items[${itemIndex}][using_dept_code]" class="form-control"></td>
                <td><input type="text" name="items[${itemIndex}][plant_system]" class="form-control"></td>
                <td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm remove-item-btn"><i class="fas fa-trash"></i></button></td>
            </tr>
        `;
        $('#items-table-body').append(newRow);
        itemIndex++;
    });

    $('#items-table-body').on('click', '.remove-item-btn', function() {
        if ($('#items-table-body tr').length > 1) {
            $(this).closest('tr').remove();
            $('#items-table-body tr').each(function(index) {
                $(this).find('td:first').text(index + 1);
            });
            itemIndex = $('#items-table-body tr').length;
            calculateTotals();
        } else {
            alert('Phải có ít nhất một mặt hàng.');
        }
    });

    $('#items-table-body').on('input', '.quantity-input, .price-input', function() {
        calculateTotals();
    });

    calculateTotals();
});
</script>
@endpush
