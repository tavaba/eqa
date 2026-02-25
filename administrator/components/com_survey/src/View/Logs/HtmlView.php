<?php
namespace Kma\Component\Survey\Administrator\View\Logs;

defined('_JEXEC') or die;

use Kma\Component\Survey\Administrator\Base\ItemsHtmlView;
use Kma\Component\Survey\Administrator\Enum\AssetType;
use Kma\Component\Survey\Administrator\Enum\EntityType;
use Kma\Component\Survey\Administrator\Helper\AssetHelper;
use Kma\Component\Survey\Administrator\Helper\LogHelper;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;
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
        $option->customFieldset1[] = new ListLayoutItemFieldOption('user', 'Người dùng');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('action', 'Hành động', true);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('entityType', 'Đối tượng',true);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('entityId', 'ID');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('result', 'Kết quả');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('data', 'Dữ liệu');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('log_date', 'Thời gian');

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        //Data preprocessing
        if(!empty($this->layoutData->items))
        {
            foreach ($this->layoutData->items as $item)
            {
                $item->action = LogHelper::decodeActionType($item->action);
                $item->entityType = EntityType::decode($item->entityType);
                $item->result = LogHelper::decodeResultCode($item->result);
            }
        }
    }
    protected function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title('Quản lý sự kiện');
        ToolbarHelper::appendGoHome();
    }
}