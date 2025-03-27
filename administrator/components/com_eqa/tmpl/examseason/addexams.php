<?php

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;

defined('_JEXEC') or die();
HTMLHelper::_('behavior.formvalidator');
$examseason = $this->examseason;
?>
<div>
    Thêm môn thi vào kỳ thi <b><?php echo htmlentities($examseason->name);?></b><br/>
    (Học kỳ <?php echo $examseason->term;?>, Năm học <?php echo htmlentities(DatabaseHelper::getAcademicyearCode($examseason->academicyear_id));?>)<br/>
    Bảng dưới đây hiển thị các môn thi có trong năm học, học kỳ tương ứng với kỳ thi. Điều này được xác định thông qua
    các lớp học phần đã được thiết lập cho học kỳ. Những môn thi đã có trong kỳ thi
    cũng sẽ không hiển thị ở đây. Do vậy, nếu có môn học nào đó không xuất hiện ở đây, hãy kiểm tra lại
    xem môn đó đã có trong danh sách môn thi của kỳ thi chưa, có lớp học phần nào của môn học đó trong học kỳ ứng
    với kỳ thi hay chưa.
</div>
<?php
ViewHelper::printItemsDefaultLayout($this->listLayoutData, $this->listLayoutItemFields);
