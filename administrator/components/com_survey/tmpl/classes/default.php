<?php

use Kma\Library\Kma\Helper\ViewHelper;

defined('_JEXEC') or die();
?>
<div>
    Danh sách các lớp học phần ở đây chỉ mang tính tạm thời,
    hỗ trợ cho việc tạo các cuộc khảo sát ý kiến người học về lớp học phần
    (về hoạt động giảng dạy của giảng viên). Cán bộ khảo sát có thể tùy ý
    xóa, cập nhật danh sách này mà không ảnh hưởng đến các cuộc khảo sát đã tạo trước đây.
</div>
<?php
ViewHelper::printItemsDefaultLayout($this->getLayoutData(), $this->getListLayoutItemFields());
