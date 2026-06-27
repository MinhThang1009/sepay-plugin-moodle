<?php
// Stub các lớp Moodle core mà plugin kế thừa nhưng PHPStan không tự resolve được
// trong môi trường phân tích (vd core_external\external_api ở lib/external/classes/,
// table_sql ở lib/tablelib.php). Khai báo rỗng để hết lỗi "extends unknown class"
// (lỗi này không ignore được qua identifier). CHỈ dùng cho PHPStan, KHÔNG nạp lúc chạy.
namespace core_external {
    class external_api {}
    class external_function_parameters {}
    class external_value {}
    class external_single_structure {}
}
namespace {
    class table_sql {}
}
