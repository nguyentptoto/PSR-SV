@extends('admin')

{{-- Đặt tiêu đề cho trang --}}
@section('title', 'Hỗ trợ liên hệ')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            {{-- Box nội dung chính --}}
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">
                        {{-- ✅ Đã cập nhật icon --}}
                        <i class="bi bi-headset me-2"></i>
                        Thông tin liên hệ hỗ trợ
                    </h3>
                </div>
                <div class="card-body">
                    <p class="lead">Nếu bạn gặp bất kỳ vấn đề kỹ thuật hoặc cần trợ giúp về việc sử dụng hệ thống, vui lòng liên hệ với chúng tôi qua các kênh dưới đây.</p>

                    {{-- Ảnh đại diện của nhóm --}}
                    <div class="text-center my-4">
                        <img src="{{ asset('assets/img/itteam2.jpg') }}" class="img-fluid rounded shadow-sm" alt="Ảnh nhóm hỗ trợ" style="max-height: 800px;">
                    </div>

                    <div class="row mt-4">
                        {{-- Box Email --}}
                        <div class="col-md-6">
                            <div class="info-box shadow-sm">
                                {{-- ✅ Đã cập nhật icon --}}
                                <span class="info-box-icon bg-info"><i class="bi bi-envelope-fill"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Email hỗ trợ</span>
                                    <span class="info-box-number"><a href="mailto:nguyentp@toto.com">nguyentp@toto.com</a></span>
                                </div>
                            </div>
                        </div>
                        {{-- Box Hotline --}}
                        <div class="col-md-6">
                            <div class="info-box shadow-sm">
                                {{-- ✅ Đã cập nhật icon --}}
                                <span class="info-box-icon bg-success"><i class="bi bi-telephone-fill"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Hotline</span>
                                    <a href="tel:0984118535">0984118535</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5">
                        {{-- ✅ Đã cập nhật icon --}}
                        <h4><i class="bi bi-clock-history me-2"></i>Giờ làm việc</h4>
                        <ul class="list-unstyled">
                            <li>Thứ Hai - Thứ Sáu: <strong>7:45 AM - 4:30 PM</strong></li>
                            <li>Thứ Bảy, Chủ Nhật và các ngày lễ: Nghỉ.</li>
                        </ul>
                    </div>

                     <div class="mt-5">
                        {{-- ✅ Đã cập nhật icon --}}
                        <h4><i class="bi bi-book-half me-2"></i>Hướng dẫn và tài liệu</h4>
                        <p>Bạn cũng có thể tìm thấy các câu trả lời cho những câu hỏi thường gặp trong phần <a href="{{ asset('documents/PHẦN MỀM QUẢN LÝ PHIẾU ĐỀ NGHỊ MUA HÀNG (PRS).pdf') }}" download>Tài liệu hướng dẫn</a> của chúng tôi.</p>
                    </div>
                </div>
                <div class="card-footer">
                    Chúng tôi sẽ phản hồi yêu cầu của bạn trong thời gian sớm nhất. Xin cảm ơn!
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
