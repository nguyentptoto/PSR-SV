<div class="card-body">
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="name">Họ và Tên*</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" id="name" value="{{ old('name', $user->name ?? '') }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="email">Email*</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" id="email" value="{{ old('email', $user->email ?? '') }}" required>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="employee_id">Mã nhân viên (Toto ID)*</label>
                <input type="text" name="employee_id" class="form-control @error('employee_id') is-invalid @enderror" id="employee_id" value="{{ old('employee_id', $user->employee_id ?? '') }}" required>
                @error('employee_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="prs_id">Mã PRS (SAP User ID)</label>
                <input type="text" name="prs_id" class="form-control @error('prs_id') is-invalid @enderror" id="prs_id" value="{{ old('prs_id', $user->prs_id ?? '') }}">
                @error('prs_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="job-title-select2">Chức vụ*</label>
                <select name="job_title_id" id="job-title-select2" style="width: 100%;" required>
                    <option value="">-- Chọn chức vụ --</option>
                    @foreach ($jobTitles as $title)
                        <option value="{{ $title->id }}" {{ old('job_title_id', $user->job_title_id ?? '') == $title->id ? 'selected' : '' }}>
                            {{ $title->name }}
                        </option>
                    @endforeach
                </select>
            </div>



            <div class="form-group">
                <label for="main_branch_id">Chi nhánh chính*</label>
                <select name="main_branch_id" class="form-control" required>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" {{ old('main_branch_id', $user->main_branch_id ?? '') == $branch->id ? 'selected' : '' }}>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="form-group">
        <label for="signature_image">Ảnh Chữ Ký</label>
        <div class="custom-file">
            <input type="file" class="custom-file-input" id="signature_image" name="signature_image">
            <label class="custom-file-label" for="signature_image">Chọn file</label>
        </div>
        @if (isset($user) && $user->signature_image_path)
            <div class="mt-2"><img src="{{ asset('storage/' . $user->signature_image_path) }}" alt="Chữ ký" height="150">       </div>
        @endif
    </div>

    <hr>
    <h4>Phân công & Trạng thái</h4>
    <div class="row">
        <div class="col-md-4 form-group">
            <label for="sections-select2">Phòng ban chuyên môn*</label>
            <div class="input-group">
                <select name="sections[]" id="sections-select2" multiple="multiple" required style="width: 100%;">
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}" {{ collect(old('sections', $userSections ?? []))->contains($section->id) ? 'selected' : '' }}>
                            {{ $section->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="mt-1">
                <button type="button" class="btn btn-xs btn-outline-secondary" id="select-all-sections">Chọn tất cả</button>
                <button type="button" class="btn btn-xs btn-outline-secondary" id="deselect-all-sections">Bỏ chọn tất cả</button>
            </div>
        </div>
        <div class="col-md-4 form-group">
            <label>Trạng thái*</label>
            <select name="status" class="form-control" required>
                <option value="1" {{ old('status', $user->status ?? 1) == 1 ? 'selected' : '' }}>Hoạt động</option>
                <option value="0" {{ old('status', $user->status ?? 1) == 0 ? 'selected' : '' }}>Khóa</option>
            </select>
        </div>
    </div>

    <hr>
    <h4>Gán quyền hạn (Assignments)</h4>
    <p><small>Chọn một cấp bậc (rank) sẽ được áp dụng cho <strong>tất cả các chi nhánh</strong> trong nhóm chức năng tương ứng.</small></p>
    <div class="row">
        @foreach ($groups as $group)
            <div class="col-md-6">
                <div class="form-group">
                    <label><strong>{{ $group->name }}</strong></label>
                    @php
                        $selectedRankId = $userAssignments[$group->id] ?? '';
                    @endphp
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
