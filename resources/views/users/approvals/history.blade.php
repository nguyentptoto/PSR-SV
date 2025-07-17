@extends('admin')
@section('title', 'Lịch sử Phiếu Đã Duyệt')

@section('content')
{{-- Form lọc (tương tự trang index) --}}
<div class="card card-outline card-info">
    <div class="card-header"><h3 class="card-title">Lọc lịch sử</h3></div>
    <div class="card-body">
        <form id="filter-form" method="GET" action="{{ route('users.approvals.history') }}">
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
                    <a href="{{ route('users.approvals.history') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Danh sách phiếu bạn đã xử lý</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Mã Phiếu</th>
                        <th>Người tạo</th>
                        <th>Phòng ban</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái hiện tại</th>
                        <th>Ngày tạo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($approvedRequests as $request)
                        <tr>
                            <td>
                                <a href="{{ route('users.purchase-requests.show', ['purchase_request' => $request->id, 'from' => 'approvals']) }}">{{ $request->pia_code }}</a>
                            </td>
                            <td>{{ $request->requester->name ?? 'N/A' }}</td>
                            <td>{{ $request->section->name ?? 'N/A' }}</td>
                            <td>{{ number_format($request->total_amount, 2) }} {{ $request->currency }}</td>
                            <td><span class="badge {{ $request->status_class }}">{{ $request->status_text }} (Cấp {{ $request->current_rank_level }})</span></td>
                            <td>{{ $request->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">Bạn chưa duyệt phiếu nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            {{ $approvedRequests->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
