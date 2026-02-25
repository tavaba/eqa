<?php
namespace Kma\Component\Survey\Administrator\View\Respondents;

defined('_JEXEC') or die;

use Kma\Component\Survey\Administrator\Base\ItemsHtmlView;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;
use Kma\Component\Survey\Administrator\Model\RespondentsModel;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $model = $this->getModel();
        $isPersonList = $model->getState('filter.is_person');

        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new ListLayoutItemFieldOption('type', 'Phân loại');
        $f = new ListLayoutItemFieldOption('code', 'Mã',true,false,'text-center');
        $f->altField = 'note';
        $option->customFieldset1[] = $f;
        if($isPersonList)
        {
            $option->customFieldset1[] = new ListLayoutItemFieldOption('lastname', 'Họ đệm');
            $option->customFieldset1[] = new ListLayoutItemFieldOption('firstname', 'Tên');
            $option->customFieldset1[] = new ListLayoutItemFieldOption('gender', 'Giới tính',false, false,'text-center');
        }
        else
        {
            $option->customFieldset1[] = new ListLayoutItemFieldOption('name', 'Tên gọi');
        }
        $option->customFieldset1[] = new ListLayoutItemFieldOption('phone', 'Điện thoại',false, false,'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('email', 'E-mail');

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        //Data preprocessing
        $model = $this->getModel();
        $isPersonList = $model->getState('filter.is_person');
        if(!empty($this->layoutData->items))
        {
            foreach ($this->layoutData->items as $item)
            {
                $item->type = RespondentHelper::decodeType($item->type);
                if($item->gender)
                    $item->gender = RespondentHelper::decodeGender($item->gender);
                if(!$isPersonList && $item->is_person)
                    $item->name = implode(' ',[$item->lastname,$item->firstname]);
            }
        }
    }
    protected function addToolbarForLayoutDefault(): void
    {
        /**
         * @var RespondentsModel $model
         */
        $model = $this->getModel();

        ToolbarHelper::title('Quản lý danh sách người được khảo sát');
        ToolbarHelper::appendGoHome();
        if($model->canSync())
        {
            $msg = 'Tác vụ này sẽ đồng bộ dữ liệu từ nguồn bên ngoài nên có thể tốn thời gian. Bạn có chắc chắn muốn thực hiện không?';
            ToolbarHelper::appendConfirmButton($msg,'loop','HVSV','respondents.syncLearners',false);
            ToolbarHelper::appendConfirmButton($msg,'loop','CB-GV-NV','respondents.syncEmployees',false);
            ToolbarHelper::appendConfirmButton($msg,'loop','GV thỉnh giảng','respondents.syncVisitingTeachers',false);
        }
        if($model->canCreate())
           ToolbarHelper::addNew('respondent.add');
        if($model->canDeleteAny($this->layoutData->items))
           ToolbarHelper::appendDelete('respondents.delete');
    }
}