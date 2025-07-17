<div class="card-body">
    <h4>Thông tin cơ bản</h4>
    <div class="row">
        <div class="col-md-6 form-group">
            <label for="employee_id">Mã nhân viên (Toto ID)*</label>
            <input type="text" name="employee_id" class="form-control @error('employee_id') is-invalid @enderror"
                id="employee_id" value="{{ old('employee_id', $user->employee_id ?? '') }}" required>
            @error('employee_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-6 form-group">
            <label for="email">Email*</label>
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                id="email" value="{{ old('email', $user->email ?? '') }}" required>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>


        <div class="col-md-6 form-group">
            <label for="name">Họ và Tên*</label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" id="name"
                value="{{ old('name', $user->name ?? '') }}" required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6 form-group">
            <label for="prs_id">Mã PRS (SAP User ID)</label>
            <input type="text" name="prs_id" class="form-control @error('prs_id') is-invalid @enderror"
                id="prs_id" value="{{ old('prs_id', $user->prs_id ?? '') }}">
            @error('prs_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        {{-- THAY THẾ INPUT TEXT BẰNG SELECT --}}
        <div class="col-md-6 form-group">
            {{-- ✅ Cập nhật for để khớp với id mới --}}
            <label for="job-title-select2">Chức vụ</label>

            {{-- ✅ Sửa đổi id, bỏ class, thêm style --}}
            <select name="job_title_id" id="job-title-select2" style="width: 100%;">
                <option value="">-- Chọn chức vụ --</option>
                @foreach ($jobTitles as $title)
                    <option value="{{ $title->id }}"
                        {{ old('job_title_id', $user->job_title_id ?? '') == $title->id ? 'selected' : '' }}>
                        {{ $title->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="form-group">
        <label for="signature_image">Ảnh Chữ Ký</label>
        <div class="custom-file">
            <input type="file" class="custom-file-input" id="signature_image" name="signature_image">
            <label class="custom-file-label" for="signature_image">Chọn file</label>
        </div>
        @if (isset($user) && $user->signature_image_path)
            <div class="mt-2"><img src="{{ Storage::url($user->signature_image_path) }}" alt="Chữ ký"
                    height="50"></div>
        @endif
    </div>

    <hr>
    <h4>Phân công & Trạng thái</h4>
    <div class="row">
        <div class="col-md-4 form-group">
            <label>Chi nhánh chính*</label>
            <select name="main_branch_id" class="form-control" required>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}"
                        {{ old('main_branch_id', $user->main_branch_id ?? '') == $branch->id ? 'selected' : '' }}>
                        {{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        {{-- ✅ BƯỚC 2: Sửa lại thẻ select --}}
       <div class="col-md-4 form-group">
    <label for="sections-select2">Phòng ban chuyên môn*</label>
    {{-- Sử dụng input-group để nhóm ô select và nút bấm --}}
    <div class="input-group">
        <select name="sections[]" id="sections-select2" multiple="multiple" required style="width: 100%;">
            @foreach ($sections as $section)
                <option value="{{ $section->id }}"
                    {{ collect(old('sections', $userSections ?? []))->contains($section->id) ? 'selected' : '' }}>
                    {{ $section->name }}
                </option>
            @endforeach
        </select>
    </div>
    {{-- THÊM MỚI: Các nút bấm tiện ích --}}
    <div class="mt-1">
        <button type="button" class="btn btn-xs btn-outline-secondary" id="select-all-sections">Chọn tất cả</button>
        <button type="button" class="btn btn-xs btn-outline-secondary" id="deselect-all-sections">Bỏ chọn tất cả</button>
    </div>
</div>
        <div class="col-md-4 form-group">
            <label>Trạng thái*</label>
            <select name="status" class="form-control" required>
                <option value="1" {{ old('status', $user->status ?? 1) == 1 ? 'selected' : '' }}>Hoạt động
                </option>
                <option value="0" {{ old('status', $user->status ?? 1) == 0 ? 'selected' : '' }}>Khóa</option>
            </select>
        </div>
    </div>

    <hr>
    <h4>Gán quyền hạn (Assignments)</h4>
    <p><small>Chọn một cấp bậc (rank) sẽ được áp dụng cho <strong>tất cả các chi nhánh</strong> trong nhóm chức năng
            tương ứng.</small></p>
    <div class="row">
        @foreach ($groups as $group)
            <div class="col-md-6">
                <div class="form-group">
                    <label><strong>{{ $group->name }}</strong></label>
                    @php
                        // Lấy rank đã được gán cho group này (nếu có)
                        $selectedRankId = $userAssignments[$group->id] ?? '';
                    @endphp
                    {{-- Tên của select box sẽ là 'assignments[group_id]' --}}
                    <select name="assignments[{{ $group->id }}]" class="form-control">
                        <option value="">-- Không có quyền duyệt --</option>
                        @foreach ($ranks as $rank)
                            <option value="{{ $rank->id }}" {{ $selectedRankId == $rank->id ? 'selected' : '' }}>
                                {{ $rank->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endforeach
    </div>
</div>
<div class="card-footer">
    <button type="submit" class="btn btn-primary">Lưu</button>
</div>
@push('scripts')
    {{-- Nạp thư viện Select2 JS --}}
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    {{-- Mã khởi tạo Select2 --}}
    <script>
        // Sử dụng jQuery's document ready để đảm bảo DOM đã được tải
        $(function() {
            // Kiểm tra xem element có tồn tại không
            if ($('#sections-select2').length) {
                $('#sections-select2').select2({
                    theme: "bootstrap-5", // Áp dụng theme cho Bootstrap 5
                    placeholder: 'Chọn hoặc tìm kiếm phòng ban...',
                    allowClear: true // Cho phép xóa lựa chọn
                });
            }
        });
    </script>
@endpush
