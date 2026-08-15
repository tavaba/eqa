<?php
namespace Kma\Component\Eqa\Administrator\View\Rooms; //must end with View Name
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Helper\RoomHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView {
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = ListLayoutItemFields::defaultFieldCode();
        $field = new ListLayoutItemFieldOption('building', 'COM_EQA_GENERAL_BUILDING', true, false);
        $field->cellCssClasses = 'text-center';
        $option->customFieldset1[] = $field;
        $field = new ListLayoutItemFieldOption('capacity', 'COM_EQA_GENERAL_ROOM_CAPACITY', true, false);
        $field->cellCssClasses = 'text-center';
        $option->customFieldset1[] = $field;
        $field = new ListLayoutItemFieldOption('type', 'COM_EQA_GENERAL_ROOM_TYPE', true, false);
        $field->cellCssClasses = 'text-center';
        $option->customFieldset1[] = $field;

        $option->state = ListLayoutItemFields::defaultFieldState();

        //Set the option
        $this->itemFields = $option;
    }

	protected function prepareDataForLayoutDefault():void
	{
		parent::prepareDataForLayoutDefault();

		//Preprocessing
		if(!empty($this->layoutData->items)) {
			foreach ($this->layoutData->items as $item) {
				$item->type = RoomHelper::roomType($item->type);
			}
		}

	}
}
