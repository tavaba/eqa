<?php

/**
 * Template mặc định cho view Resits.
 *
 * Hiển thị các danh sách thi lần 2 của cơ sở đào tạo đang làm việc, kèm số liệu
 * thống kê. Nhấn vào tên danh sách để mở danh sách thí sinh bên trong.
 */

defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\CampusHelper;
use Kma\Library\Kma\Helper\ViewHelper;

/** @var \Kma\Component\Eqa\Administrator\View\Resits\HtmlView $this */

echo CampusHelper::renderSwitcher();
?>

<div class="alert alert-info">
    <span class="icon-info-circle me-1" aria-hidden="true"></span>
    Mỗi cơ sở đào tạo có <strong>tối đa một danh sách đang kích hoạt</strong>. Đó là danh sách mà
    thí sinh đăng ký thi lại vào, đồng thời là căn cứ để tạo môn thi lần 2 và rà soát việc nộp phí
    thi lại. Trước mỗi kỳ thi lần 2, hãy tạo một danh sách mới — danh sách của các kỳ thi trước
    vẫn được giữ nguyên để tra cứu.
</div>

<?php
ViewHelper::printItemsDefaultLayout($this->getLayoutData(), $this->getListLayoutItemFields());
