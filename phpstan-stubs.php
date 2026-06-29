<?php
// phpcs:ignoreFile
// File stub CHỈ dùng cho PHPStan, không nạp lúc chạy; cú pháp namespace{} làm phpcs treo nên bỏ qua.
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
namespace core_privacy\local\metadata {
    interface provider {}
    class collection {}
}
namespace core_privacy\local\request {
    interface core_userlist_provider {}
    class contextlist {}
    class approved_contextlist {}
    class userlist {}
    class approved_userlist {}
    class writer {}
    class transform {}
}
namespace core_privacy\local\request\plugin {
    interface provider {}
}
