@extends('admin')
@section('title', 'Duyệt Phiếu Đề Nghị')

@section('content')
<div class="card card-outline card-info">
    <div class="card-header"><h3 class="card-title">Lọc danh sách</h3></div>
    <div class="card-body">
        <form id="filter-form" method="GET" action="{{ route('users.approvals.index') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="pia_code" class="form-label">Mã Phiếu</label>
                    <input type="text" class="form-control" id="pia_code" name="pia_code" value="{{ request('pia_code') }}">
                </div>
                <div class="col-md-4">
                    <label for="requester_id" class="form-label">Người tạo</label>
                    <select class="form-select" id="requester_id" name="requester_id">
                        <option value="">-- Tất cả --</option>
                        @foreach($requesters as $requester)
                            <option value="{{ $requester->id }}" {{ request('requester_id') == $requester->id ? 'selected' : '' }}>
                                {{ $requester->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="section_id" class="form-label">Phòng ban</label>
                    <select class="form-select" id="section_id" name="section_id">
                        <option value="">-- Tất cả --</option>
                        @foreach($sections as $section)
                            <option value="{{ $section->id }}" {{ request('section_id') == $section->id ? 'selected' : '' }}>
                                {{ $section->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-12 text-end mt-3">
                    <button type="submit" class="btn btn-info">Lọc</button>
                    <a href="{{ route('users.approvals.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Danh sách phiếu cần xử lý</h3>
    </div>
    <form action="{{ route('users.approvals.bulk-approve') }}" method="POST" id="bulk-approve-form">
        @csrf
        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{!! session('error') !!}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="mb-3">
                <button type="button" class="btn btn-success" id="bulk-approve-trigger-btn">
                    <i class="bi bi-check2-square"></i> Duyệt các mục đã chọn
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 10px;"><input type="checkbox" id="check-all"></th>
                            <th>Mã Phiếu</th>
                            <th>Người tạo</th>
                            <th>Phòng ban</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái hiện tại</th>
                            <th>Ngày tạo</th>
                            <th style="width: 150px;">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pendingRequests as $request)
                            <tr>
                                <td><input type="checkbox" name="request_ids[]" class="request-checkbox" value="{{ $request->id }}"></td>
                                <td>
                                    <a href="{{ route('users.purchase-requests.show', ['purchase_request' => $request->id, 'from' => 'approvals']) }}">{{ $request->pia_code }}</a>
                                </td>
                                <td>{{ $request->requester->name ?? 'N/A' }}</td>
                                <td>{{ $request->section->name ?? 'N/A' }}</td>
                                <td>{{ number_format($request->total_amount, 2) }} {{ $request->currency }}</td>
                                <td><span class="badge {{ $request->status_class }}">{{ $request->status_text }} (Cấp {{ $request->current_rank_level }})</span></td>
                                <td>{{ $request->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    @can('approve', $request)
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal" data-action-url="{{ route('users.approvals.approve', $request->id) }}">Duyệt</button>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" data-action-url="{{ route('users.approvals.reject', $request->id) }}">Từ chối</button>
                                    @else
                                        <span class="text-muted">Đã xử lý hoặc chờ cấp khác</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center">Không có phiếu nào đang chờ bạn duyệt.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $pendingRequests->withQueryString()->links() }}
            </div>
        </div>
    </form>
</div>


{{-- ✅ SỬA ĐỔI: Modal Xác nhận Duyệt Hàng Loạt (Phiên bản tùy chỉnh) --}}
<div class="custom-modal-overlay" id="bulkApproveModalOverlay">
    <div class="custom-modal-content">
      <div class="custom-modal-header">
        <h5 class="custom-modal-title">Xác nhận Duyệt Hàng Loạt</h5>
        <button type="button" class="custom-modal-close" id="close-bulk-modal-btn">&times;</button>
      </div>
      <div class="custom-modal-body">
          <p>Bạn có chắc chắn muốn phê duyệt tất cả <strong id="selected-count">0</strong> phiếu đã chọn?</p>
      </div>
      <div class="custom-modal-footer">
        <button type="button" class="btn btn-secondary" id="cancel-bulk-approve-btn">Hủy</button>
        <button type="button" class="btn btn-success" id="confirm-bulk-approve-btn">Xác nhận Duyệt</button>
      </div>
    </div>
</div>

{{-- ✅ BỔ SUNG: Modal Xác nhận Duyệt --}}
<div class="modal fade" id="approveModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Xác nhận Phê duyệt</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="approve-form" method="POST" action="">
          @csrf
          <div class="modal-body">
              <p>Bạn có chắc chắn muốn phê duyệt phiếu này không?</p>
              <div class="mb-3">
                  <label for="approve_comment" class="form-label">Ghi chú (tùy chọn):</label>
                  <textarea class="form-control" id="approve_comment" name="comment" rows="3"></textarea>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button type="submit" class="btn btn-success">Xác nhận Duyệt</button>
          </div>
      </form>
    </div>
  </div>
</div>
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Lý do từ chối</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="reject-form" method="POST" action="">
          @csrf
          <div class="modal-body">
              <div class="mb-3">
                  <label for="reject_comment" class="form-label">Vui lòng nhập lý do từ chối (bắt buộc):</label>
                  <textarea class="form-control" id="reject_comment" name="comment" rows="3" required></textarea>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button type="submit" class="btn btn-danger">Xác nhận Từ chối</button>
          </div>
      </form>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#approveModal').on('show.bs.modal', function (event) {
            let button = $(event.relatedTarget);
            let actionUrl = button.data('action-url');
            let form = $('#approve-form');
            form.attr('action', actionUrl);
        });

        $('#rejectModal').on('show.bs.modal', function (event) {
            let button = $(event.relatedTarget);
            let actionUrl = button.data('action-url');
            let form = $('#reject-form');
            form.attr('action', actionUrl);
        });

        $('#check-all').on('click', function() {
            $('.request-checkbox').prop('checked', $(this).prop('checked'));
        });

        $('.request-checkbox').on('click', function() {
            if (!$(this).prop('checked')) {
                $('#check-all').prop('checked', false);
            }
        });

        $('#bulk-approve-trigger-btn').on('click', function() {
            const selectedCount = $('input.request-checkbox:checked').length;
            if (selectedCount === 0) {
                alert('Vui lòng chọn ít nhất một phiếu để duyệt.');
                return;
            }
            $('#selected-count').text(selectedCount);
            var bulkApproveModal = new bootstrap.Modal(document.getElementById('bulkApproveModal'));
            bulkApproveModal.show();
        });

        $('#confirm-bulk-approve-btn').on('click', function() {
            $('#bulk-approve-form').submit();
        });
    });
</script>
@endpush
