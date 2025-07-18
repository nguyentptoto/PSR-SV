<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Các dòng ngôn ngữ kiểm tra hợp lệ
    |--------------------------------------------------------------------------
    |
    | Các dòng ngôn ngữ sau đây chứa các thông báo lỗi mặc định được sử dụng
    | bởi lớp kiểm tra hợp lệ. Một số quy tắc này có nhiều phiên bản như
    | các quy tắc về kích thước. Hãy thoải mái tinh chỉnh từng thông báo ở đây.
    |
    */

    'accepted' => 'Trường :attribute phải được chấp nhận.',
    'accepted_if' => 'Trường :attribute phải được chấp nhận khi :other là :value.',
    'active_url' => 'Trường :attribute không phải là một URL hợp lệ.',
    'after' => 'Trường :attribute phải là một ngày sau ngày :date.',
    'after_or_equal' => 'Trường :attribute phải là một ngày sau hoặc bằng ngày :date.',
    'alpha' => 'Trường :attribute chỉ được chứa các chữ cái.',
    'alpha_dash' => 'Trường :attribute chỉ được chứa các chữ cái, số, dấu gạch ngang và dấu gạch dưới.',
    'alpha_num' => 'Trường :attribute chỉ được chứa các chữ cái và số.',
    'array' => 'Trường :attribute phải là một mảng.',
    'before' => 'Trường :attribute phải là một ngày trước ngày :date.',
    'before_or_equal' => 'Trường :attribute phải là một ngày trước hoặc bằng ngày :date.',
    'between' => [
        'numeric' => 'Trường :attribute phải nằm trong khoảng :min và :max.',
        'file' => 'Dung lượng tệp :attribute phải nằm trong khoảng :min và :max kilobytes.',
        'string' => 'Trường :attribute phải có từ :min đến :max ký tự.',
        'array' => 'Trường :attribute phải có từ :min đến :max phần tử.',
    ],
    'boolean' => 'Trường :attribute phải là true hoặc false.',
    'confirmed' => 'Giá trị xác nhận của trường :attribute không khớp.',
    'current_password' => 'Mật khẩu không chính xác.',
    'date' => 'Trường :attribute không phải là một ngày hợp lệ.',
    'date_equals' => 'Trường :attribute phải là một ngày bằng với :date.',
    'date_format' => 'Trường :attribute không khớp với định dạng :format.',
    'declined' => 'Trường :attribute phải bị từ chối.',
    'declined_if' => 'Trường :attribute phải bị từ chối khi :other là :value.',
    'different' => 'Trường :attribute và :other phải khác nhau.',
    'digits' => 'Trường :attribute phải có :digits chữ số.',
    'digits_between' => 'Trường :attribute phải có từ :min đến :max chữ số.',
    'dimensions' => 'Trường :attribute có kích thước hình ảnh không hợp lệ.',
    'distinct' => 'Trường :attribute có giá trị bị trùng lặp.',
    'email' => 'Trường :attribute phải là một địa chỉ email hợp lệ.',
    'ends_with' => 'Trường :attribute phải kết thúc bằng một trong những giá trị sau: :values.',
    'enum' => 'Giá trị đã chọn cho :attribute không hợp lệ.',
    'exists' => 'Giá trị đã chọn cho :attribute không hợp lệ.',
    'file' => 'Trường :attribute phải là một tệp.',
    'filled' => 'Trường :attribute phải có một giá trị.',
    'gt' => [
        'numeric' => 'Trường :attribute phải lớn hơn :value.',
        'file' => 'Dung lượng tệp :attribute phải lớn hơn :value kilobytes.',
        'string' => 'Trường :attribute phải có nhiều hơn :value ký tự.',
        'array' => 'Trường :attribute phải có nhiều hơn :value phần tử.',
    ],
    'gte' => [
        'numeric' => 'Trường :attribute phải lớn hơn hoặc bằng :value.',
        'file' => 'Dung lượng tệp :attribute phải lớn hơn hoặc bằng :value kilobytes.',
        'string' => 'Trường :attribute phải có :value ký tự hoặc nhiều hơn.',
        'array' => 'Trường :attribute phải có :value phần tử hoặc nhiều hơn.',
    ],
    'image' => 'Trường :attribute phải là một hình ảnh.',
    'in' => 'Giá trị đã chọn cho :attribute không hợp lệ.',
    'in_array' => 'Trường :attribute không tồn tại trong :other.',
    'integer' => 'Trường :attribute phải là một số nguyên.',
    'ip' => 'Trường :attribute phải là một địa chỉ IP hợp lệ.',
    'ipv4' => 'Trường :attribute phải là một địa chỉ IPv4 hợp lệ.',
    'ipv6' => 'Trường :attribute phải là một địa chỉ IPv6 hợp lệ.',
    'json' => 'Trường :attribute phải là một chuỗi JSON hợp lệ.',
    'lt' => [
        'numeric' => 'Trường :attribute phải nhỏ hơn :value.',
        'file' => 'Dung lượng tệp :attribute phải nhỏ hơn :value kilobytes.',
        'string' => 'Trường :attribute phải có ít hơn :value ký tự.',
        'array' => 'Trường :attribute phải có ít hơn :value phần tử.',
    ],
    'lte' => [
        'numeric' => 'Trường :attribute phải nhỏ hơn hoặc bằng :value.',
        'file' => 'Dung lượng tệp :attribute phải nhỏ hơn hoặc bằng :value kilobytes.',
        'string' => 'Trường :attribute không được có nhiều hơn :value ký tự.',
        'array' => 'Trường :attribute không được có nhiều hơn :value phần tử.',
    ],
    'mac_address' => 'Trường :attribute phải là một địa chỉ MAC hợp lệ.',
    'max' => [
        'numeric' => 'Trường :attribute không được lớn hơn :max.',
        'file' => 'Dung lượng tệp :attribute không được lớn hơn :max kilobytes.',
        'string' => 'Trường :attribute không được có nhiều hơn :max ký tự.',
        'array' => 'Trường :attribute không được có nhiều hơn :max phần tử.',
    ],
    'mimes' => 'Trường :attribute phải là một tệp có định dạng: :values.',
    'mimetypes' => 'Trường :attribute phải là một tệp có định dạng: :values.',
    'min' => [
        'numeric' => 'Trường :attribute phải có giá trị ít nhất là :min.',
        'file' => 'Dung lượng tệp :attribute phải có ít nhất :min kilobytes.',
        'string' => 'Trường :attribute phải có ít nhất :min ký tự.',
        'array' => 'Trường :attribute phải có ít nhất :min phần tử.',
    ],
    'multiple_of' => 'Trường :attribute phải là bội số của :value.',
    'not_in' => 'Giá trị đã chọn cho :attribute không hợp lệ.',
    'not_regex' => 'Định dạng trường :attribute không hợp lệ.',
    'numeric' => 'Trường :attribute phải là một số.',
    'password' => 'Mật khẩu không chính xác.',
    'present' => 'Trường :attribute phải được cung cấp.',
    'prohibited' => 'Trường :attribute bị cấm.',
    'prohibited_if' => 'Trường :attribute bị cấm khi :other là :value.',
    'prohibited_unless' => 'Trường :attribute bị cấm trừ khi :other là một trong :values.',
    'prohibits' => 'Trường :attribute cấm :other tồn tại.',
    'regex' => 'Định dạng trường :attribute không hợp lệ.',
    'required' => 'Trường :attribute là bắt buộc.',
    'required_array_keys' => 'Trường :attribute phải chứa các mục cho: :values.',
    'required_if' => 'Trường :attribute là bắt buộc khi :other là :value.',
    'required_unless' => 'Trường :attribute là bắt buộc trừ khi :other là một trong :values.',
    'required_with' => 'Trường :attribute là bắt buộc khi :values có mặt.',
    'required_with_all' => 'Trường :attribute là bắt buộc khi tất cả :values có mặt.',
    'required_without' => 'Trường :attribute là bắt buộc khi :values không có mặt.',
    'required_without_all' => 'Trường :attribute là bắt buộc khi không có :values nào có mặt.',
    'same' => 'Trường :attribute và :other phải khớp nhau.',
    'size' => [
        'numeric' => 'Trường :attribute phải bằng :size.',
        'file' => 'Dung lượng tệp :attribute phải bằng :size kilobytes.',
        'string' => 'Trường :attribute phải có :size ký tự.',
        'array' => 'Trường :attribute phải chứa :size phần tử.',
    ],
    'starts_with' => 'Trường :attribute phải bắt đầu bằng một trong những giá trị sau: :values.',
    'string' => 'Trường :attribute phải là một chuỗi.',
    'timezone' => 'Trường :attribute phải là một múi giờ hợp lệ.',
    'unique' => 'Trường :attribute đã tồn tại.',
    'uploaded' => 'Tải lên trường :attribute không thành công.',
    'url' => 'Trường :attribute phải là một URL hợp lệ.',
    'uuid' => 'Trường :attribute phải là một UUID hợp lệ.',

    /*
    |--------------------------------------------------------------------------
    | Các dòng ngôn ngữ kiểm tra hợp lệ tùy chỉnh
    |--------------------------------------------------------------------------
    |
    | Tại đây bạn có thể chỉ định các thông báo kiểm tra hợp lệ tùy chỉnh cho
    | các thuộc tính bằng cách sử dụng quy ước "attribute.rule" để đặt tên
    | cho các dòng. Điều này giúp chúng ta nhanh chóng chỉ định một dòng ngôn
    | ngữ tùy chỉnh cụ thể cho một quy tắc thuộc tính nhất định.
    |
    */

    'custom' => [
        'ten-thuoc-tinh' => [
            'ten-quy-tac' => 'thong-bao-tuy-chinh',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Các thuộc tính kiểm tra hợp lệ tùy chỉnh
    |--------------------------------------------------------------------------
    |
    | Các dòng ngôn ngữ sau đây được sử dụng để hoán đổi trình giữ chỗ thuộc
    | tính của chúng ta với một cái gì đó dễ đọc hơn như "Địa chỉ E-Mail" thay
    | vì "email". Điều này chỉ đơn giản giúp chúng ta làm cho thông báo của
    | mình biểu cảm hơn.
    |
    */

    'attributes' => [],

];
