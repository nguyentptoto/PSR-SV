@extends('admin')
@section('title', 'Tạo Phiếu Đề Nghị Mua Hàng')


@section('content')
    <form action="{{ route('users.purchase-requests.store') }}" method="POST" id="pr-form" enctype="multipart/form-data">
        @csrf
        {{-- Khối hiển thị lỗi validation --}}
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
        {{-- Card Thông tin chung --}}

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Thông tin chung</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label for="pia_code">Mã Phiếu (PR NO)*</label>
                        <input type="text" name="pia_code" class="form-control" required>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="section_id">Phòng ban của bạn*</label>
                        <select name="section_id" class="form-control" required>
                            @foreach ($user->sections as $section)
                                <option value="{{ $section->id }}">{{ $section->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="executing_department_id">Phòng ban yêu cầu*</label>
                        <select name="executing_department_id" id="executing-department-select" class="form-control"
                            required style="width: 100%;">
                            <option></option>
                            @foreach ($executingDepartments as $department)
                                <option value="{{ $department->id }}" data-code="{{ $department->code }}">
                                    {{ $department->name }} ({{ $department->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Nhà máy (Plant)*</label>
                        <input type="text" class="form-control" value="{{ $user->mainBranch->name }}" disabled>
                        <input type="hidden" name="branch_id" value="{{ $user->mainBranch->id }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="sap_release_date">Ngày phát hành (SAP)</label>
                        <input type="date" id="sap_release_date" name="sap_release_date" class="form-control"
                            value="{{ old('sap_release_date') }}">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="requested_delivery_date">Ngày yêu cầu giao hàng*</label>
                        <input type="date" name="requested_delivery_date" class="form-control" required>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="priority">Mức độ ưu tiên</label> {{-- Bỏ dấu * --}}
                        <select name="priority" class="form-control"> {{-- Bỏ required --}}
                            <option value="">-- Chọn mức độ --</option> {{-- Thêm lựa chọn rỗng --}}
                            <option value="normal" {{ old('priority') == 'normal' ? 'selected' : '' }}>Normal/Bình thường
                            </option>
                            <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Urgent/Khẩn cấp
                            </option>
                            <option value="quotation_only" {{ old('priority') == 'quotation_only' ? 'selected' : '' }}>
                                Quotation only/Báo giá</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="currency">Tiền tệ*</label>
                        <select name="currency" class="form-control" required>
                            <option value="VND" selected>VND</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                </div>
                <div class="form-group mt-2">
                    <label for="remarks">Ghi chú (Remarks)</label>
                    <textarea name="remarks" id="remarks" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group mt-2">
                    <label for="attachment_file">File đính kèm</label>
                    <input type="file" name="attachment_file" id="attachment_file" class="form-control">
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" value="1" id="requires_director_approval"
                        name="requires_director_approval">
                    <label class="form-check-label" for="requires_director_approval">
                        <strong>Yêu cầu Giám đốc (Cấp 4) duyệt</strong>
                    </label>
                </div>
            </div>
        </div>

        {{-- Card Chi tiết hàng hóa --}}
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Chi tiết hàng hóa</h3>
            </div>
            <div class="card-body">
                {{-- ✅ SỬA ĐỔI: Giao diện Import được cập nhật --}}
                <div class="callout callout-success mb-3">
                    <h5><i class="fas fa-file-excel"></i> Import từ Excel</h5>
                    <p>Bạn có thể chuẩn bị file Excel với **dòng tiêu đề** và các cột theo thứ tự hướng dẫn.</p>
                    <button type="button" class="btn btn-info" data-bs-toggle="modal"
                        data-bs-target="#importInstructionsModal"><i class="fas fa-info-circle"></i> Xem hướng dẫn</button>
                    <button type="button" class="btn btn-success" id="import-items-btn"><i class="fas fa-upload"></i>
                        Chọn
                        file Excel để Import</button>
                    <input type="file" id="items-excel-input" style="display: none;" accept=".xlsx, .xls">
                </div>


                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-bordered table-head-fixed" style="min-width: 1600px;">
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
                            {{-- Dữ liệu sẽ được điền bằng JavaScript --}}
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-secondary mt-2" id="add-item-btn"><i class="fas fa-plus"></i> Thêm
                    dòng</button>
            </div>
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
            <button type="submit" class="btn btn-primary btn-lg">Tạo Phiếu Đề Nghị</button>
        </div>
    </form>

    {{-- ✅ THÊM MỚI: Modal Hướng dẫn Import --}}
    <div class="modal fade" id="importInstructionsModal" tabindex="-1" aria-labelledby="importInstructionsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importInstructionsModalLabel">Hướng dẫn Định dạng File Excel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Chuẩn bị file Excel với **dòng tiêu đề** (Header Row) và các cột được sắp xếp theo đúng thứ tự sau.
                        Dòng tiêu đề sẽ bị bỏ qua khi import.</p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Thứ tự cột Excel</th>
                                    <th>Tên Cột (Gợi ý)</th>
                                    <th>Nội dung</th>
                                    <th>Ghi chú</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>A</td>
                                    <td><strong>PR_NO</strong></td>
                                    <td>Mã Phiếu Đề Nghị</td>
                                    <td>Sẽ tự động điền vào trường "Mã Phiếu (PR NO)" chung của phiếu.</td>
                                </tr>
                                <tr>
                                    <td>B</td>
                                    <td><strong>Material</strong></td>
                                    <td>Mã hàng</td>
                                    <td>**Bắt buộc**</td>
                                </tr>
                                <tr>
                                    <td>C</td>
                                    <td><strong>Description</strong></td>
                                    <td>Tên hàng</td>
                                    <td>**Bắt buộc**</td>
                                </tr>
                                <tr>
                                    <td>D</td>
                                    <td><strong>Plant</strong></td>
                                    <td>Mã Plant</td>
                                    <td>Ví dụ: 1000. Cần khớp với dữ liệu hệ thống.</td>
                                </tr>
                                <tr>
                                    <td>E</td>
                                    <td><strong>SLoc</strong></td>
                                    <td>Mã SLoc (Storage Location)</td>
                                    <td>Ví dụ: 0001. Sẽ được ghép với Plant để tạo "Plant hệ".</td>
                                </tr>
                                <tr>
                                    <td>F</td>
                                    <td><strong>Requesting</strong></td>
                                    <td>Mã phòng đề nghị</td>
                                    <td>Sẽ tự động điền vào trường "Phòng ban yêu cầu" chung của phiếu. Ví dụ: PC.</td>
                                </tr>
                                <tr>
                                    <td>G</td>
                                    <td><strong>TrackingNo</strong></td>
                                    <td>Mã phòng sử dụng</td>
                                    <td>Tùy chọn.</td>
                                </tr>
                                <tr>
                                    <td>H</td>
                                    <td><strong>Quantity</strong></td>
                                    <td>Số lượng đặt hàng</td>
                                    <td>**Bắt buộc**, phải là số lớn hơn 0.</td>
                                </tr>
                                <tr>
                                    <td>I</td>
                                    <td><strong>Unit</strong></td>
                                    <td>Đơn vị tính</td>
                                    <td>Ví dụ: PC, KG. Mặc định là PC nếu trống.</td>
                                </tr>
                                <tr>
                                    <td>J</td>
                                    <td><strong>Deliv. Date</strong></td>
                                    <td>Ngày yêu cầu giao hàng</td>
                                    <td>Định dạng **YYYY-MM-DD** hoặc định dạng ngày Excel (Excel Date Number). Sẽ tự động điền vào trường "Ngày yêu cầu giao hàng" chung của phiếu.</td>
                                </tr>
                                <tr>
                                    <td>K</td>
                                    <td><strong>Estimated</strong></td>
                                    <td>Giá dự tính</td>
                                    <td>**Bắt buộc**, phải là số không âm.</td>
                                </tr>
                                <tr>
                                    <td>L</td>
                                    <td><strong>Total Val.</strong></td>
                                    <td>Tổng giá trị</td>
                                    <td>Tùy chọn, hệ thống sẽ tự tính lại.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-danger">**Lưu ý quan trọng:**</p>
                    <ul>
                        <li>Các cột **Mã hàng (Material)**, **Tên hàng (Description)**, **Số lượng đặt (Quantity)**, và **Giá dự tính (Estimated)** là **bắt buộc**.</li>
                        <li>Nếu một trong các cột bắt buộc bị thiếu hoặc không hợp lệ, quá trình import sẽ dừng lại và bạn sẽ nhận được cảnh báo.</li>
                        <li>Đảm bảo rằng dữ liệu trong các cột số (Quantity, Estimated, Total Val.) là định dạng số.</li>
                        <li>Dòng đầu tiên của file Excel sẽ được xem là dòng tiêu đề và bị bỏ qua.</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đã hiểu</button>
                </div>
            </div>
        </div>
    </div>
@endsection


@push('scripts')
    {{-- Thêm thư viện XLSX (SheetJS) --}}

    <script>
        $(document).ready(function () {
            let itemIndex = 0;

            // Kích hoạt Select2
            $('#executing-department-select').select2({
                theme: 'bootstrap-5',
                placeholder: 'Chọn hoặc tìm kiếm phòng ban...',
                allowClear: true
            });

            function calculateTotals() {
                let grandTotal = 0;
                let totalOrderQty = 0;
                let totalInventoryQty = 0;

                $('#items-table-body tr').each(function () {
                    const row = $(this);
                    // Sử dụng parseFloat và kiểm tra NaN để đảm bảo giá trị là số
                    const orderQty = parseFloat(row.find('.quantity-input').val()) || 0;
                    const inventoryQty = parseFloat(row.find('.inventory-quantity-input').val()) || 0;
                    const price = parseFloat(row.find('.price-input').val()) || 0;
                    const subtotal = orderQty * price;

                    row.find('.subtotal-display').val(subtotal.toLocaleString('vi-VN'));
                    grandTotal += subtotal;
                    totalOrderQty += orderQty;
                    totalInventoryQty += inventoryQty;
                });

                // Hiển thị giá trị đã định dạng
                $('#total_amount_display').text(grandTotal.toLocaleString('vi-VN'));
                $('#total-order-quantity-display').text(totalOrderQty.toLocaleString('vi-VN'));
                $('#total-inventory-quantity-display').text(totalInventoryQty.toLocaleString('vi-VN'));

                // Gán giá trị số thô vào input ẩn
                $('#total_amount_hidden').val(grandTotal);
                $('#total-order-quantity-hidden').val(totalOrderQty);
                $('#total-inventory-quantity-hidden').val(totalInventoryQty);
            }

            // Hàm tạo dòng mặt hàng mới
            function createItemRow(item = {}) {
                const newRow = `
                    <tr>
                        <td class="text-center align-middle">${itemIndex + 1}</td>
                        <td><input type="text" name="items[${itemIndex}][item_code]" class="form-control" value="${item.item_code || ''}" required></td>
                        <td><input type="text" name="items[${itemIndex}][item_name]" class="form-control" value="${item.item_name || ''}" required></td>
                        <td><input type="text" name="items[${itemIndex}][old_item_code]" class="form-control" value="${item.old_item_code || ''}"></td>
                        <td><input type="number" name="items[${itemIndex}][order_quantity]" class="form-control quantity-input" value="${item.order_quantity || 0}" required step="any" min="0.001"></td>
                        <td><input type="text" name="items[${itemIndex}][order_unit]" class="form-control" value="${item.order_unit || 'PC'}"></td>
                        <td><input type="number" name="items[${itemIndex}][inventory_quantity]" class="form-control inventory-quantity-input" value="${item.inventory_quantity || 0}" step="any" min="0"></td>
                        <td><input type="text" name="items[${itemIndex}][inventory_unit]" class="form-control" value="${item.inventory_unit || 'PC'}"></td>
                        <td><input type="number" name="items[${itemIndex}][r3_price]" class="form-control" value="${item.r3_price || 0}" step="any" min="0"></td>
                        <td><input type="number" name="items[${itemIndex}][estimated_price]" class="form-control price-input" value="${item.estimated_price || 0}" required step="any" min="0"></td>
                        <td><input type="text" name="items[${itemIndex}][subtotal_display]" class="form-control subtotal-display" readonly></td>
                        <td><input type="text" name="items[${itemIndex}][using_dept_code]" class="form-control" value="${item.using_dept_code || ''}"></td>
                        <td><input type="text" name="items[${itemIndex}][plant_system]" class="form-control" value="${item.plant_system || ''}"></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm remove-item-btn"><i class="fas fa-trash"></i></button></td>
                    </tr>
                `;
                $('#items-table-body').append(newRow);
                itemIndex++;
                calculateTotals();
            }

            $('#add-item-btn').on('click', function () {
                createItemRow();
            });

            $('#items-table-body').on('click', '.remove-item-btn', function () {
                if ($('#items-table-body tr').length > 1) {
                    $(this).closest('tr').remove();
                    $('#items-table-body tr').each(function (index) {
                        $(this).find('td:first').text(index + 1);
                    });
                    itemIndex = $('#items-table-body tr').length;
                    calculateTotals();
                } else {
                    alert('Phải có ít nhất một mặt hàng.');
                }
            });

            $('#items-table-body').on('input', '.quantity-input, .price-input, .inventory-quantity-input', function () {
                calculateTotals();
            });

            // Loại bỏ code xử lý sự kiện 'change' cho #requested_delivery_date nếu không cần cộng ngày thủ công
            // $('#requested_delivery_date').on('change', function() {
            //     const selectedDate = $(this).val();
            //     if (selectedDate) {
            //         const date = new Date(selectedDate);
            //         date.setDate(date.getDate() + 1);
            //         const nextDay = date.toISOString().split('T')[0];
            //         $(this).val(nextDay);
            //     }
            // });


            // Kiểm tra dữ liệu trước khi gửi form (Client-side validation)
            $('#pr-form').on('submit', function (e) {
                const itemRows = $('#items-table-body tr');
                if (itemRows.length === 0) {
                    e.preventDefault();
                    alert('Vui lòng thêm ít nhất một mặt hàng.');
                    return false;
                }

                let hasError = false;
                itemRows.each(function (index) {
                    const itemCode = $(this).find(`input[name="items[${index}][item_code]"]`).val();
                    const itemName = $(this).find(`input[name="items[${index}][item_name]"]`).val();
                    // Đảm bảo giá trị là số và lớn hơn 0
                    const orderQuantity = parseFloat($(this).find(`input[name="items[${index}][order_quantity]"]`).val());
                    // Đảm bảo giá trị là số và không âm
                    const estimatedPrice = parseFloat($(this).find(`input[name="items[${index}][estimated_price]"]`).val());

                    if (!itemCode) {
                        alert(`Mặt hàng ${index + 1}: Mã hàng là bắt buộc.`);
                        hasError = true;
                        return false; // Break out of each loop
                    }
                    if (!itemName) {
                        alert(`Mặt hàng ${index + 1}: Tên hàng là bắt buộc.`);
                        hasError = true;
                        return false;
                    }
                    if (isNaN(orderQuantity) || orderQuantity <= 0) {
                        alert(`Mặt hàng ${index + 1}: Số lượng đặt hàng phải là số lớn hơn 0.`);
                        hasError = true;
                        return false;
                    }
                    if (isNaN(estimatedPrice) || estimatedPrice < 0) {
                        alert(`Mặt hàng ${index + 1}: Giá dự tính phải là số không âm.`);
                        hasError = true;
                        return false;
                    }
                });

                if (hasError) {
                    e.preventDefault(); // Ngăn chặn gửi form nếu có lỗi
                    return false;
                }

                // Cập nhật lại tổng trước khi gửi form cuối cùng
                calculateTotals();
            });

            $('#import-items-btn').on('click', function () {
                $('#items-excel-input').click();
            });

            $('#items-excel-input').on('change', function (e) {
                const file = e.target.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = function (event) {
                    try {
                        const data = new Uint8Array(event.target.result);
                        // Cấu hình cellDates: true để đọc ngày tháng chính xác
                        const workbook = XLSX.read(data, { type: 'array', cellDates: true });
                        const firstSheetName = workbook.SheetNames[0];
                        const worksheet = workbook.Sheets[firstSheetName];
                        // header: 1 để đọc dữ liệu thô, raw: false để XLSX tự định dạng ngày tháng
                        const dataRows = XLSX.utils.sheet_to_json(worksheet, { header: 1, raw: false });

                        if (dataRows.length < 2) { // Cần ít nhất 1 dòng header và 1 dòng dữ liệu
                            alert('File Excel cần có ít nhất một dòng tiêu đề và một dòng dữ liệu.');
                            e.target.value = ''; // Reset input file
                            return;
                        }

                        const headerRow = dataRows[0]; // Có thể dùng để xác định các cột nếu cần thiết
                        const items = dataRows.slice(1); // Bỏ qua dòng tiêu đề

                        let hasImportError = false;
                        const importedItems = [];

                        items.forEach((row, rowIndex) => {
                            // Ánh xạ các cột từ Excel theo thứ tự bạn đã định nghĩa:
                            // A: PR_NO (row[0])
                            // B: Material (row[1])
                            // C: Description (row[2])
                            // D: Plant (row[3])
                            // E: SLoc (row[4])
                            // F: Requesting (row[5])
                            // G: TrackingNo (row[6])
                            // H: Quantity (row[7])
                            // I: Unit (row[8])
                            // J: Deliv. Date (row[9])
                            // K: Estimated (row[10])
                            // L: Total Val. (row[11])

                            const itemCode = String(row[1] || '').trim(); // Cột B: Material
                            const itemName = String(row[2] || '').trim(); // Cột C: Description
                            const orderQuantity = parseFloat(row[7]) || 0; // Cột H: Quantity
                            const estimatedPrice = parseFloat(row[10]) || 0; // Cột K: Estimated

                            // --- Client-side validation cho dữ liệu từ Excel ---
                            if (!itemCode) {
                                alert(`Lỗi import: Dòng ${rowIndex + 2}: Mã hàng (Cột B) không được để trống.`);
                                hasImportError = true;
                                return;
                            }
                            if (!itemName) {
                                alert(`Lỗi import: Dòng ${rowIndex + 2}: Tên hàng (Cột C) không được để trống.`);
                                hasImportError = true;
                                return;
                            }
                            if (isNaN(orderQuantity) || orderQuantity <= 0) {
                                alert(`Lỗi import: Dòng ${rowIndex + 2}: Số lượng đặt hàng (Cột H) phải là số lớn hơn 0.`);
                                hasImportError = true;
                                return;
                            }
                            if (isNaN(estimatedPrice) || estimatedPrice < 0) {
                                alert(`Lỗi import: Dòng ${rowIndex + 2}: Giá dự tính (Cột K) phải là số không âm.`);
                                hasImportError = true;
                                return;
                            }

                            // --- Xử lý ngày giao hàng từ Excel (Cột J: Deliv. Date) và cộng thêm 1 ngày ---
                            let deliveryDate = row[9];
                            let formattedDeliveryDate = '';

                            if (deliveryDate) {
                                let dateObj;
                                if (deliveryDate instanceof Date) {
                                    dateObj = deliveryDate;
                                } else if (typeof deliveryDate === 'number') {
                                    // Chuyển đổi Excel Date Number sang Date Object
                                    dateObj = new Date(Math.round((deliveryDate - 25569) * 86400 * 1000));
                                } else {
                                    dateObj = new Date(deliveryDate);
                                }

                                if (dateObj && !isNaN(dateObj.getTime())) {
                                    // Cộng thêm 1 ngày chỉ khi import từ Excel
                                    dateObj.setDate(dateObj.getDate() + 1);

                                    // Định dạng lại về YYYY-MM-DD
                                    formattedDeliveryDate = dateObj.toISOString().split('T')[0];
                                } else {
                                    console.warn(`Dòng ${rowIndex + 2}: Ngày giao hàng không hợp lệ từ Excel: ${deliveryDate}`);
                                    formattedDeliveryDate = '';
                                }
                            }
                            // --- Kết thúc xử lý ngày giao hàng ---

                            importedItems.push({
                                pia_code: String(row[0] || '').trim(), // Cột A: PR_NO
                                item_code: itemCode,
                                item_name: itemName,
                                old_item_code: String(row[1] || '').trim(), // Cột B: Material (Bạn có thể muốn ánh xạ cột này sang một cột khác trong Excel nếu "Mã hàng cũ" không phải là "Material")
                                order_quantity: orderQuantity,
                                order_unit: String(row[8] || 'PC').trim(), // Cột I: Unit
                                inventory_quantity: parseFloat(row[7]) || 0, // Cột H: Quantity (Bạn có thể muốn ánh xạ cột này sang một cột khác nếu "SL Kho" không phải là "Quantity")
                                inventory_unit: String(row[8] || 'PC').trim(), // Cột I: Unit (Bạn có thể muốn ánh xạ cột này sang một cột khác nếu "ĐV Kho" không phải là "Unit")
                                r3_price: parseFloat(row[10]) || 0, // Cột K: Estimated (Bạn có thể muốn ánh xạ cột này sang một cột khác nếu "Giá R3" không phải là "Estimated")
                                estimated_price: estimatedPrice,
                                using_dept_code: String(row[6] || '').trim(), // Cột G: TrackingNo
                                plant_system: `${String(row[3] || '').trim()} ${String(row[4] || '').trim()}`.trim(), // Cột D (Plant) + Cột E (SLoc)
                                requested_delivery_date: formattedDeliveryDate, // Ngày đã được cộng 1 ngày
                                requesting_code: String(row[5] || '').trim(), // Cột F: Requesting
                            });
                        });

                        if (hasImportError) {
                            e.target.value = ''; // Reset input file nếu có lỗi
                            return;
                        }

                        // Xóa các dòng hiện có và điền dữ liệu mới
                        $('#items-table-body').empty();
                        itemIndex = 0; // Reset itemIndex để các dòng mới có chỉ số đúng

                        // Cập nhật thông tin chung từ dòng đầu tiên của Excel (nếu có)
                        if (importedItems.length > 0) {
                            const firstImportedItem = importedItems[0];
                            if (firstImportedItem.pia_code) {
                                $('input[name="pia_code"]').val(firstImportedItem.pia_code);
                            }
                            if (firstImportedItem.requesting_code) {
                                const selectElement = $('#executing-department-select');
                                const targetOption = selectElement.find(`option[data-code="${firstImportedItem.requesting_code}"]`);
                                if (targetOption.length) {
                                    selectElement.val(targetOption.val()).trigger('change');
                                }
                            }
                            if (firstImportedItem.requested_delivery_date) {
                                // Gán ngày đã được cộng 1 ngày từ importedItems
                                $('input[name="requested_delivery_date"]').val(firstImportedItem.requested_delivery_date);
                            }
                        }

                        // Thêm các mặt hàng đã import vào bảng
                        importedItems.forEach(function (itemData) {
                            createItemRow(itemData);
                        });

                        calculateTotals();
                        alert('Import dữ liệu từ Excel thành công!');

                    } catch (error) {
                        console.error("Lỗi đọc file Excel: ", error);
                        alert("Đã có lỗi xảy ra khi đọc file Excel. Vui lòng kiểm tra lại định dạng file hoặc dữ liệu. Lỗi: " + error.message);
                    } finally {
                        e.target.value = ''; // Luôn reset input file sau khi xử lý
                    }
                };
                reader.readAsArrayBuffer(file);
            });

            // Thêm dòng mặc định khi tải trang
            if ($('#items-table-body tr').length === 0) {
                $('#add-item-btn').click();
            }
            // Gọi calculateTotals khi tải trang để hiển thị tổng ban đầu (nếu có dữ liệu cũ)
            calculateTotals();
        });
    </script>
@endpush
