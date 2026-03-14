<?php
namespace Kma\Component\Survey\Administrator\View\Units;

defined('_JEXEC') or die;

use Kma\Component\Survey\Administrator\Base\ItemsHtmlView;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;
use Kma\Component\Survey\Administrator\Model\UnitModel;
use Kma\Component\Survey\Administrator\Model\UnitsModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $option = new ListLayoutItemFields();

        $option->id = ListLayoutItemFields::defaultFieldId();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $f = new ListLayoutItemFieldOption('code', 'Mã',true,true,'text-center');
        $f->showLinkConditionField = 'canEdit';
        $option->customFieldset1[] = $f;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('type', 'Phân loại');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('name', 'Tên');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('size', 'Kích thước',true,false,'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('note', 'Ghi chú');

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        //Data preprocessing
        if(!empty($this->layoutData->items))
        {
            /**
             * Load item model for access permission checking
             * @var UnitModel $itemModel
             */
            $itemModel = ComponentHelper::createModel('Unit');

            foreach ($this->layoutData->items as $item)
            {
                $item->type = RespondentHelper::decodeUnitType($item->type);
                $item->canEdit = $itemModel->canEdit($item);
            }
        }
    }
    protected function addToolbarForLayoutDefault(): void
    {
        /**
         * Load model for access permission checking
         * @var UnitsModel $model
         */
        $model = $this->getModel();
        $items = $this->layoutData->items;

        ToolbarHelper::title('Quản lý danh sách cơ quan, đơn vị');
        ToolbarHelper::appendGoHome();

        if($model->canSync())
        {
            ToolbarHelper::appendButton('loop','Khóa đào tạo','units.syncCourses');
            ToolbarHelper::appendButton('loop','Phòng/Khoa/Ban','units.syncDepartments');
        }

        if($model->canCreate())
            ToolbarHelper::addNew('unit.add');

        if($model->canDeleteAny($items))
            ToolbarHelper::appendDelete('units.delete');
    }
}