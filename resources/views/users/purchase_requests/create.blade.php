@extends('admin') {{-- Giả định bạn đang sử dụng layout 'admin' --}}
@section('title', 'Tạo Phiếu Đề Nghị Mua Hàng')

@section('content')
    {{-- Khối hiển thị thông báo (errors, warnings, success) --}}
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <h5 class="alert-heading"><i class="icon fas fa-ban"></i> Lỗi!</h5>
            {{ session('error') }}
        </div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warning alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <h5 class="alert-heading"><i class="icon fas fa-exclamation-triangle"></i> Cảnh báo!</h5>
            {!! session('warning') !!}
        </div>
    @endif
    @if (session('success'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <h5 class="alert-heading"><i class="icon fas fa-check-circle"></i> Thành công!</h5>
            {{ session('success') }}
        </div>
    @endif
    {{-- Chỉ hiển thị lỗi validation nếu không phải từ import Excel --}}
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

    {{-- Form để tạo phiếu thủ công (SINGLE PURCHASE REQUEST) --}}
    <form action="{{ route('users.purchase-requests.store') }}" method="POST" id="pr-form" enctype="multipart/form-data">
        @csrf
        {{-- Card Thông tin chung --}}
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Thông tin chung (Phiếu đơn lẻ)</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label for="pia_code">Mã Phiếu (PR NO)*</label>
                        <input type="text" name="pia_code" class="form-control" value="{{ old('pia_code') }}" required>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="section_id">Phòng ban của bạn*</label>
                        <select name="section_id" class="form-control" required>
                            @foreach ($user->sections as $section)
                                <option value="{{ $section->id }}"
                                    {{ old('section_id', $user->sections->first()->id) == $section->id ? 'selected' : '' }}>
                                    {{ $section->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="executing_department_id">Phòng ban yêu cầu*</label>
                        <select name="executing_department_id" id="executing-department-select" class="form-control"
                            required style="width: 100%;">
                            <option value="">-- Chọn phòng ban --</option>
                            @foreach ($executingDepartments as $department)
                                <option value="{{ $department->id }}" data-code="{{ $department->code }}"
                                    {{ old('executing_department_id') == $department->id ? 'selected' : '' }}>
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
                        <input type="date" name="requested_delivery_date" class="form-control"
                            value="{{ old('requested_delivery_date') }}" required>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="priority">Mức độ ưu tiên</label>
                        <select name="priority" class="form-control">
                            <option value="">-- Chọn mức độ --</option>
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
                            <option value="VND" {{ old('currency', 'VND') == 'VND' ? 'selected' : '' }}>VND</option>
                            <option value="USD" {{ old('currency') == 'USD' ? 'selected' : '' }}>USD</option>
                        </select>
                    </div>
                </div>
                <div class="form-group mt-2">
                    <label for="remarks">Ghi chú (Remarks)</label>
                    <textarea name="remarks" id="remarks" class="form-control" rows="3">{{ old('remarks') }}</textarea>
                </div>

                <div class="form-group mt-2">
                    <label for="attachment_file">File đính kèm (Phiếu đơn lẻ)</label>
                    <input type="file" name="attachment_file" id="attachment_file" class="form-control"
                        accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/jpeg,image/png,application/zip">
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" value="1" id="requires_director_approval"
                        name="requires_director_approval" {{ old('requires_director_approval') ? 'checked' : '' }}>
                    <label class="form-check-label" for="requires_director_approval">
                        <strong>Yêu cầu Giám đốc (Cấp 4) duyệt</strong>
                    </label>
                </div>

                {{-- Các trường mới thêm vào PurchaseRequest từ Excel (nếu bạn muốn nhập thủ công) --}}
                <div class="row mt-3">
                    <div class="col-md-4 form-group">
                        <label for="sap_request_date">Ngày yêu cầu (SAP Req.Date)</label>
                        <input type="date" name="sap_request_date" class="form-control"
                            value="{{ old('sap_request_date') }}">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="po_number">Số PO (PO Number)</label>
                        <input type="text" name="po_number" class="form-control" value="{{ old('po_number') }}">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="po_date">Ngày PO (PO Date)</label>
                        <input type="date" name="po_date" class="form-control" value="{{ old('po_date') }}">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="sap_created_by">Người tạo (SAP Created By)</label>
                        <input type="text" name="sap_created_by" class="form-control"
                            value="{{ old('sap_created_by') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- Card Chi tiết hàng hóa --}}
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Chi tiết hàng hóa</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-bordered table-head-fixed" style="min-width: 1600px;">
                        <thead class="table-light text-center">
                            <tr>
                                <th style="width: 3%;">#</th>
                                <th style="width: 10%;">Mã hàng*</th>
                                <th style="width: 15%;">Tên hàng*</th>
                                <th style="width: 10%;">Mã hàng cũ</th>
                                <th style="width: 10%;">Mã hàng phụ</th>
                                <th style="width: 7%;">SL Đặt*</th>
                                <th style="width: 5%;">ĐV Đặt</th>
                                <th style="width: 7%;">SL Kho</th>
                                <th style="width: 5%;">ĐV Kho</th>
                                <th style="width: 8%;">Giá R3</th>
                                <th style="width: 8%;">Giá dự tính*</th>
                                <th style="width: 10%;">Thành tiền</th>
                                <th style="width: 8%;">Mã phòng SD</th>
                                <th style="width: 8%;">Plant hệ</th>
                                <th style="width: 8%;">Nhóm mua hàng</th>
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

    <hr> {{-- Đường phân cách giữa 2 chức năng --}}

    {{-- Form RIÊNG BIỆT để Import từ Excel (MULTIPLE PURCHASE REQUESTS) --}}
    <div class="card card-success mt-4">
        <div class="card-header">
            <h3 class="card-title">Import nhiều Phiếu Đề Nghị từ Excel</h3>
        </div>
        <div class="card-body">
            <div class="callout callout-info mb-3">
                <h5><i class="fas fa-file-excel"></i> Hướng dẫn Import</h5>
                <p>Bạn có thể chuẩn bị file Excel chứa **nhiều mã PR khác nhau**. Hệ thống sẽ tự động **nhóm các mặt hàng có
                    cùng Mã Phiếu (Purch.Req.)** và chuyển hướng đến trang xem trước để bạn kiểm tra trước khi tạo phiếu
                    chính thức.</p>
                <button type="button" class="btn btn-info" data-bs-toggle="modal"
                    data-bs-target="#importInstructionsModal"><i class="fas fa-info-circle"></i> Xem hướng dẫn định dạng
                    file Excel</button>
            </div>
            <form action="{{ route('users.purchase-requests.import-excel-process') }}" method="POST"
                enctype="multipart/form-data" id="import-excel-form">
                @csrf
                <div class="form-group mb-3">
                    <label for="excel_file" class="form-label">Chọn file Excel để Import</label>
                    <input type="file" name="excel_file" id="excel_file" class="form-control" accept=".xlsx, .xls"
                        required>
                </div>
                <div class="form-group mb-3">
                    <label for="attachment_zip_file" class="form-label">File đính kèm (ZIP - Tùy chọn)</label>
                    <p class="text-muted small">Nếu có, file ZIP này phải chứa các file con được đặt tên theo Mã PR (ví dụ:
                        <code>101532462.pdf</code> hoặc <code>1101532462.jpg</code>). Hệ thống sẽ cố gắng khớp
                        và đính kèm.</p>
                    <input type="file" name="attachment_zip_file" id="attachment_zip_file" class="form-control"
                        accept="application/zip">
                </div>
                <button type="submit" class="btn btn-success mt-3" id="submit-import-excel-btn"><i
                        class="fas fa-upload"></i> Đọc File Excel để Xem Trước</button>
            </form>
        </div>
    </div>


    {{-- Modal Hướng dẫn Import (đã cập nhật) --}}
    <div class="modal fade" id="importInstructionsModal" tabindex="-1" aria-labelledby="importInstructionsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importInstructionsModalLabel">Hướng dẫn Định dạng File Excel cho Import
                        Nhiều Phiếu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Chuẩn bị file Excel với **dòng tiêu đề** (Header Row) ở dòng đầu tiên. Dòng tiêu đề sẽ bị bỏ qua khi
                        import.</p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Tên Cột trong Excel (phải khớp chính xác)</th>
                                    <th>Ánh xạ vào hệ thống</th>
                                    <th>Ghi chú</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Purch.Req.</strong></td>
                                    <td>Mã Phiếu (PR NO)</td>
                                    <td>**Bắt buộc**. Các dòng có cùng mã này sẽ được nhóm thành một phiếu đề nghị riêng.
                                    </td>
                                </tr>
                                <tr>
                                    <td>**A**</td>
                                    <td>Mã hàng phụ (Legacy Item Code)</td>
                                    <td>**Mới thêm**. Sẽ được lưu vào chi tiết mặt hàng.</td>
                                </tr>
                                <tr>
                                    <td><strong>Plnt</strong> (hoặc <strong>Plant</strong>)</td>
                                    <td>Mã Plant</td>
                                    <td>Ví dụ: 4910. Hệ thống sẽ ưu tiên "Plnt" nếu có.</td>
                                </tr>
                                <tr>
                                    <td><strong>PGr</strong></td>
                                    <td>Nhóm mua hàng (Purchase Group)</td>
                                    <td>**Mới thêm**. Sẽ được lưu vào chi tiết mặt hàng.</td>
                                </tr>
                                <tr>
                                    <td><strong>SLoc</strong></td>
                                    <td>Mã SLoc (Storage Location)</td>
                                    <td>Ví dụ: 4941. Sẽ được ghép với Plant để tạo "Plant hệ".</td>
                                </tr>
                                <tr>
                                    <td><strong>Material</strong></td>
                                    <td>Mã hàng</td>
                                    <td>**Bắt buộc**. Mã vật tư.</td>
                                </tr>
                                <tr>
                                    <td><strong>Short Text</strong> (hoặc <strong>Description</strong>)</td>
                                    <td>Tên hàng</td>
                                    <td>**Bắt buộc**. Mô tả vật tư. Hệ thống sẽ ưu tiên "Short Text" nếu có.</td>
                                </tr>
                                <tr>
                                    <td><strong>Created</strong></td>
                                    <td>Người tạo (SAP Created By)</td>
                                    <td>**Mới thêm**. Thông tin người tạo phiếu trong hệ thống gốc (ví dụ: SAP). Nếu trống,
                                        sẽ lấy PR ID của người dùng đang import.</td>
                                </tr>
                                <tr>
                                    <td><strong>Requisnr.</strong> (hoặc <strong>Requesting</strong>)</td>
                                    <td>Mã phòng đề nghị</td>
                                    <td>**Bắt buộc**. Mã phòng ban yêu cầu (Executing Department). Ví dụ: 4900-23120. Hệ
                                        thống sẽ ưu tiên "Requisnr.".</td>
                                </tr>
                                <tr>
                                    <td><strong>TrackingNo</strong></td>
                                    <td>Mã phòng sử dụng (Using Department Code)</td>
                                    <td>Tùy chọn.</td>
                                </tr>
                                <tr>
                                    <td><strong>PO</strong></td>
                                    <td>Số đơn đặt hàng (PO Number)</td>
                                    <td>**Mới thêm**. Số PO liên quan (nếu có). Sẽ lưu vào phiếu.</td>
                                </tr>
                                <tr>
                                    <td><strong>Req.Date</strong></td>
                                    <td>Ngày yêu cầu (SAP Request Date)</td>
                                    <td>**Mới thêm**. Ngày PR được tạo/yêu cầu trong hệ thống gốc. Sẽ lưu vào phiếu. Định
                                        dạng **YYYY-MM-DD** hoặc Excel Date Number.</td>
                                </tr>
                                <tr>
                                    <td><strong>Deliv.dt</strong> (hoặc <strong>Deliv. Date</strong>)</td>
                                    <td>Ngày yêu cầu giao hàng</td>
                                    <td>**Bắt buộc**. Định dạng **YYYY-MM-DD** hoặc Excel Date Number. Đây là ngày yêu cầu
                                        giao hàng chung cho phiếu. Hệ thống sẽ ưu tiên "Deliv.dt".</td>
                                </tr>
                                <tr>
                                    <td><strong>Quantity</strong></td>
                                    <td>Số lượng đặt hàng</td>
                                    <td>**Bắt buộc**, phải là số lớn hơn 0.</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Val.</strong></td>
                                    <td>Tổng giá trị mặt hàng (Subtotal)</td>
                                    <td>Tùy chọn, nhưng **được dùng để tính toán "Giá dự tính" (Estimated Price)** nếu cột
                                        "Estimated" không có.</td>
                                </tr>
                                <tr>
                                    <td><strong>Un</strong> (hoặc <strong>Unit</strong>)</td>
                                    <td>Đơn vị tính (Order Unit)</td>
                                    <td>Ví dụ: PC, KG. Mặc định là PC nếu trống. Hệ thống sẽ ưu tiên "Un".</td>
                                </tr>
                                <tr>
                                    <td><strong>Crcy</strong> (hoặc <strong>Currency</strong>)</td>
                                    <td>Tiền tệ của phiếu</td>
                                    <td>Tùy chọn. Mặc định là VND. (Sẽ lấy từ dòng đầu tiên của mỗi PR_NO). Hệ thống sẽ ưu
                                        tiên "Crcy".</td>
                                </tr>
                                <tr>
                                    <td><strong>PO Date</strong></td>
                                    <td>Ngày đặt hàng (PO Date)</td>
                                    <td>**Mới thêm**. Ngày PO được tạo (nếu có). Sẽ lưu vào phiếu. Định dạng **YYYY-MM-DD**
                                        hoặc Excel Date Number.</td>
                                </tr>
                                <tr>
                                    <td>**Estimated**</td>
                                    <td>Giá dự tính</td>
                                    <td>Tùy chọn. Nếu cột này có và giá trị hợp lệ, nó sẽ được ưu tiên sử dụng. Nếu không có
                                        hoặc bằng 0, hệ thống sẽ cố gắng tính từ "Total Val." chia cho "Quantity". **Giá trị
                                        cuối cùng phải là số không âm.**</td>
                                </tr>
                                <tr>
                                    <td>**Item**</td>
                                    <td>Mã hàng cũ (Old Item Code)</td>
                                    <td>Tùy chọn. (Trong Excel đa mã, đây là cột F).</td>
                                </tr>
                                <tr>
                                    <td>**[Không có trong Excel của bạn]**</td>
                                    <td>Số lượng tồn kho (Inventory Quantity)</td>
                                    <td>Tùy chọn. Nếu không có, mặc định bằng "Quantity".</td>
                                </tr>
                                <tr>
                                    <td>**[Không có trong Excel của bạn]**</td>
                                    <td>Đơn vị tồn kho (Inventory Unit)</td>
                                    <td>Tùy chọn. Nếu không có, mặc định bằng "Un" hoặc "Unit".</td>
                                </tr>
                                <tr>
                                    <td>**[Không có trong Excel của bạn]**</td>
                                    <td>Giá R3 (R3 Price)</td>
                                    <td>Tùy chọn. Nếu không có, mặc định là 0.</td>
                                </tr>
                                <tr>
                                    <td>**[Không có trong Excel của bạn]**</td>
                                    <td>Mức độ ưu tiên (Priority)</td>
                                    <td>Tùy chọn. Giá trị: 'urgent', 'normal', 'quotation_only'. Mặc định 'normal'. (Sẽ lấy
                                        từ dòng đầu tiên của mỗi PR_NO).</td>

                                </tr>
                                <tr>
                                    <td>**[Không có trong Excel của bạn]**</td>
                                    <td>Ghi chú (Remarks)</td>
                                    <td>Tùy chọn. (Sẽ lấy từ dòng đầu tiên của mỗi PR_NO).</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-danger">**Lưu ý quan trọng:**</p>
                    <ul>
                        <li>Các cột **Purch.Req. (Mã phiếu)**, **Material (Mã hàng)**, **Short Text/Description (Tên
                            hàng)**, **Requisnr./Requesting (Phòng đề nghị)**, **Quantity (Số lượng đặt)**,
                            **Deliv.dt/Deliv. Date (Ngày yêu cầu giao hàng)** là **bắt buộc**.</li>
                        <li>Nếu cột "Estimated" không có hoặc giá trị bằng 0, hệ thống sẽ cố gắng tính toán "Giá dự tính" từ
                            "Total Val." chia cho "Quantity". Đảm bảo cung cấp đủ dữ liệu nếu bạn không có cột "Estimated".
                        </li>
                        <li>Nếu một trong các cột bắt buộc bị thiếu hoặc không hợp lệ, quá trình import phiếu đó sẽ bị bỏ
                            qua và bạn sẽ nhận được cảnh báo.</li>
                        <li>Đảm bảo rằng dữ liệu trong các cột ngày (Req.Date, Deliv.dt, PO Date) có định dạng
                            **YYYY-MM-DD** hoặc là **Excel Date Number** để được chuyển đổi chính xác.</li>
                        <li>Đảm bảo rằng dữ liệu trong các cột số (Quantity, Total Val., Estimated, Inventory Quantity, R3
                            Price) là định dạng số.</li>
                        <li>Dòng đầu tiên của file Excel sẽ được xem là dòng tiêu đề và bị bỏ qua.</li>
                        <li>Các giá trị cho các trường thông tin chung của phiếu (như Ngày yêu cầu giao hàng, Phòng ban yêu
                            cầu, Tiền tệ, Mức độ ưu tiên, Ghi chú, Ngày yêu cầu SAP, Số PO, Ngày PO, Người tạo SAP) sẽ được
                            lấy từ **dòng đầu tiên** của mỗi nhóm PR_NO trong file Excel.</li>
                        <li>Nếu bạn cung cấp **File đính kèm (ZIP)**, các file bên trong ZIP phải được đặt tên theo **Mã
                            PR** (ví dụ: <code>1101532462.pdf</code>, <code>PR_1101532463.jpg</code>) để hệ thống tự động
                            khớp và đính kèm.</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đã hiểu</button>
                </div>
            </div>
        </div>
    </div>
    </div>

@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            let itemIndex = 0; // Dùng cho form tạo thủ công

            // Kích hoạt Select2
            $('#executing-department-select').select2({
                theme: 'bootstrap-5',
                placeholder: 'Chọn hoặc tìm kiếm phòng ban...',
                allowClear: true
            });

            // Hàm tính tổng cho form tạo thủ công (single PR)
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

                    row.find('.subtotal-display').val(subtotal.toLocaleString('vi-VN', {
                        minimumFractionDigits: 2
                    }));
                    grandTotal += subtotal;
                    totalOrderQty += orderQty;
                    totalInventoryQty += inventoryQty;
                });

                $('#total_amount_display').text(grandTotal.toLocaleString('vi-VN', {
                    minimumFractionDigits: 2
                }));
                $('#total-order-quantity-display').text(totalOrderQty.toLocaleString('vi-VN', {
                    minimumFractionDigits: 3
                }));
                $('#total-inventory-quantity-display').text(totalInventoryQty.toLocaleString('vi-VN', {
                    minimumFractionDigits: 3
                }));

                $('#total_amount_hidden').val(grandTotal);
                $('#total-order-quantity-hidden').val(totalOrderQty);
                $('#total-inventory-quantity-hidden').val(totalInventoryQty);
            }

            // Hàm tạo dòng mặt hàng mới cho form tạo thủ công
            function createItemRow(item = {}) {
                const newRow = `
                    <tr>
                        <td class="text-center align-middle">${itemIndex + 1}</td>
                        <td><input type="text" name="items[${itemIndex}][item_code]" class="form-control" value="${item.item_code || ''}" required></td>
                        <td><input type="text" name="items[${itemIndex}][item_name]" class="form-control" value="${item.item_name || ''}" required></td>
                        <td><input type="text" name="items[${itemIndex}][old_item_code]" class="form-control" value="${item.old_item_code || ''}"></td>
                        <td><input type="text" name="items[${itemIndex}][legacy_item_code]" class="form-control" value="${item.legacy_item_code || ''}"></td>
                        <td><input type="number" name="items[${itemIndex}][order_quantity]" class="form-control quantity-input" value="${item.order_quantity || 0}" required step="any" min="0.001"></td>
                        <td><input type="text" name="items[${itemIndex}][order_unit]" class="form-control" value="${item.order_unit || 'PC'}"></td>
                        <td><input type="number" name="items[${itemIndex}][inventory_quantity]" class="form-control inventory-quantity-input" value="${item.inventory_quantity || 0}" step="any" min="0"></td>
                        <td><input type="text" name="items[${itemIndex}][inventory_unit]" class="form-control" value="${item.inventory_unit || 'PC'}"></td>
                        <td><input type="number" name="items[${itemIndex}][r3_price]" class="form-control" value="${item.r3_price || 0}" step="any" min="0"></td>
                        <td><input type="number" name="items[${itemIndex}][estimated_price]" class="form-control price-input" value="${item.estimated_price || 0}" required step="any" min="0"></td>
                        <td><input type="text" name="items[${itemIndex}][subtotal_display]" class="form-control subtotal-display" readonly></td>
                        <td><input type="text" name="items[${itemIndex}][using_dept_code]" class="form-control" value="${item.using_dept_code || ''}"></td>
                        <td><input type="text" name="items[${itemIndex}][plant_system]" class="form-control" value="${item.plant_system || ''}"></td>
                        <td><input type="text" name="items[${itemIndex}][purchase_group]" class="form-control" value="${item.purchase_group || ''}"></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-danger btn-sm remove-item-btn"><i class="fas fa-trash"></i></button></td>
                    </tr>
                `;
                $('#items-table-body').append(newRow);
                itemIndex++;
                calculateTotals();
            }

            $('#add-item-btn').on('click', function() {
                createItemRow();
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

            $('#items-table-body').on('input', '.quantity-input, .price-input, .item-inventory-quantity-input',
                function() {
                    calculateTotals();
                });

            // Kiểm tra dữ liệu trước khi gửi form (Client-side validation cho form tạo thủ công)
            $('#pr-form').on('submit', function(e) {
                const itemRows = $('#items-table-body tr');
                if (itemRows.length === 0) {
                    e.preventDefault();
                    alert('Vui lòng thêm ít nhất một mặt hàng.');
                    return false;
                }

                let hasError = false;
                itemRows.each(function(index) {
                    const itemCode = $(this).find(`input[name="items[${index}][item_code]"]`).val();
                    const itemName = $(this).find(`input[name="items[${index}][item_name]"]`).val();
                    const orderQuantity = parseFloat($(this).find(
                        `input[name="items[${index}][order_quantity]"]`).val());
                    const estimatedPrice = parseFloat($(this).find(
                        `input[name="items[${index}][estimated_price]"]`).val());

                    if (!itemCode) {
                        alert(`Mặt hàng ${index + 1}: Mã hàng là bắt buộc.`);
                        hasError = true;
                        return false;
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

                calculateTotals(); // Cập nhật lại tổng trước khi gửi form cuối cùng
            });

            // --- Logic cho phần Import Excel (SỬA ĐỔI LỚN) ---
            // Nút "Đọc File Excel để Xem Trước" sẽ trigger việc gửi form qua AJAX
            $('#submit-import-excel-btn').on('click', function(e) {
                e.preventDefault(); // Ngăn chặn submit form HTML mặc định

                const form = $('#import-excel-form');
                const fileInput = $('#excel_file')[0];
                if (!fileInput.files || fileInput.files.length === 0) {
                    Swal.fire('Lỗi!', 'Vui lòng chọn file Excel để import.', 'error');
                    return;
                }

                const formData = new FormData(form[0]); // Lấy form data bao gồm cả file Excel và file ZIP

                Swal.fire({
                    title: 'Đang đọc file Excel...',
                    html: 'Vui lòng chờ. Quá trình này có thể mất một chút thời gian cho file lớn.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: form.attr('action'),
                    method: form.attr('method'),
                    data: formData,
                    processData: false, // Không xử lý dữ liệu (để FormData hoạt động)
                    contentType: false, // Không đặt Content-Type (để trình duyệt tự đặt boundary cho FormData)
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        Swal.close(); // Đóng loading alert

                        if (response.success) {
                            // Chuyển hướng đến trang xem trước
                            window.location.href = response.redirect_url;
                        } else {
                            let errorHtml = '';
                            if (response.message) {
                                errorHtml += `<p>${response.message}</p>`;
                            }
                            if (response.errors && response.errors.length > 0) {
                                errorHtml += '<ul>';
                                response.errors.forEach(err => {
                                    errorHtml += `<li>${err}</li>`;
                                });
                                errorHtml += '</ul>';
                            } else {
                                errorHtml = 'Đã xảy ra lỗi không xác định khi đọc file Excel.';
                            }

                            Swal.fire({
                                title: 'Lỗi!',
                                html: errorHtml,
                                icon: 'error'
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.close(); // Đóng loading alert
                        let errorMessages = 'Đã xảy ra lỗi không xác định.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessages = xhr.responseJSON.message;
                            if (xhr.responseJSON.errors) {
                                errorMessages += '<ul>';
                                for (const key in xhr.responseJSON.errors) {
                                    errorMessages += `<li>${xhr.responseJSON.errors[key]}</li>`;
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
            });


            // Thêm dòng mặc định khi tải trang cho form tạo thủ công
            if ($('#items-table-body tr').length === 0) {
                $('#add-item-btn').click();
            }
            // Gọi calculateTotals khi tải trang để hiển thị tổng ban đầu (nếu có dữ liệu cũ)
            calculateTotals();
        });
    </script>
@endpush
