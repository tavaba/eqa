<?php
namespace Kma\Component\Survey\Administrator\View\Respondentgroups;

defined('_JEXEC') or die;

use Kma\Component\Survey\Administrator\Base\ItemsHtmlView;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;
use Kma\Component\Survey\Administrator\Model\RespondentgroupModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
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
        $f = new ListLayoutItemFieldOption('name', 'Tên nhóm', false, true);
        $f->showLinkConditionField = 'canEdit';
        $option->customFieldset1[] = $f;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('typeText', 'Phân loại');
        $f = new ListLayoutItemFieldOption('size', 'Số lượng',true,false,'text-center');
        $f->urlFormatString = 'index.php?option=com_survey&view=respondentgroupmembers&group_id=%d';
        $option->customFieldset1[] = $f;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('description', 'Mô tả nhóm');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('author', 'Tác giả');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('date', 'Ngày tạo');

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        //Preprocessing
        if(!empty($this->layoutData->items))
        {
            /**
             * Load admin model for access checking
             * @var RespondentgroupModel $model
             */
            $model = ComponentHelper::getMVCFactory()->createModel('Respondentgroup');
            foreach ($this->layoutData->items as &$item) {
                $item->typeText = RespondentHelper::decodeType($item->type);
                $item->date = DatetimeHelper::getFullDate($item->creationTime);
                $item->canEdit = $model->canEdit($item);
            }
        }
    }
    protected function addToolbarForLayoutDefault(): void
    {
        $this->toolbarOption->title = 'Quản lý nhóm người được khảo sát';
        parent::addToolbarForLayoutDefault();
    }
}