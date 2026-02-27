<?php
namespace Kma\Component\Survey\Administrator\View\Classes;

defined('_JEXEC') or die;

use Kma\Component\Survey\Administrator\Base\ItemsHtmlView;
use Kma\Component\Survey\Administrator\Model\ClassesModel;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new ListLayoutItemFieldOption('subject', 'Môn học');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('code', 'Mã lớp', true);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('lecturer', 'Giảng viên');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('size', 'Sĩ số',true);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('academicyear', 'Năm học',true);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('term', 'Học kỳ',true);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('startDate', 'Bắt đầu',true);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('endDate', 'Kết thúc',true);

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        //Data preprocessing
        if(!empty($this->layoutData->items))
        {
            foreach ($this->layoutData->items as &$item)
            {
                $item->academicyear = DatetimeHelper::decodeAcademicYear($item->academicyear);
            }
        }
    }
    protected function addToolbarForLayoutDefault(): void
    {
        /**
         * @var ClassesModel $model
         */
        $model = $this->getModel();
        $items = $this->layoutData->items;

        ToolbarHelper::title('Danh sách lớp học phần');
        ToolbarHelper::appendGoHome();
        if($model->canSync())
        {
            $confirmMsg1 = 'Thao tác này chỉ cập nhật danh sách lớp học, không tác động đến danh sách HVSV của lớp học. Nó đòi hỏi kết nối dữ liệu bên ngoài nên có thể tốn vài phút!';
            ToolbarHelper::appendConfirmButton($confirmMsg1,'loop','Danh sách lớp','classes.sync',false, 'btn btn-danger');
            $confirmMsg2 = 'Thao tác này sẽ cập nhật danh sách HVSV cho các lớp học đã chọn. Nó đòi hỏi kết nối dữ liệu bên ngoài nên có thể tốn vài phút!';
            ToolbarHelper::appendConfirmButton($confirmMsg2,'loop','HVSV từng lớp','classes.syncLearners',true, 'btn btn-danger');
        }
        if($model->canDeleteAny($items))
            ToolbarHelper::appendDelete('classes.delete');
    }
}