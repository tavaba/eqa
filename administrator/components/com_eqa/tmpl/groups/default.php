<?php
defined('_JEXEC') or die();
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\ViewHelper;

?>
<div>
    Lưu ý: chỉ hiển thị các lớp học thuộc các khóa học đang còn hiệu lực. Nếu không tìm thấy lớp học mong muốn, hãy kiểm tra trạng thái <a href="<?php echo JRoute::_('index.php?option=com_eqa&view=courses'); ?>">khóa học</a> tương ứng.
</div>
<?php
if(!empty($this->layoutData->items)) {
    foreach ($this->layoutData->items as $key => $item) {
        $item->homeroom = EmployeeHelper::getFullName($item->homeroom);
        $item->adviser = EmployeeHelper::getFullName($item->adviser);
        if ($item->admissionyear == 0)
            $item->admissionyear = null;
    }
}

ViewHelper::printItemsDefaultLayout($this->layoutData, $this->itemFields);
