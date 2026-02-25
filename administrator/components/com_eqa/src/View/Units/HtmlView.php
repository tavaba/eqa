<?php
namespace Kma\Component\Eqa\Administrator\View\Units; //Must end with View Name
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\UnitHelper;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView {
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new ListLayoutItemFields();
        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $field = new ListLayoutItemFieldOption('code', 'COM_EQA_GENERAL_CODE', false, true);
        $field->cssClass = 'text-center';
        $option->customFieldset1[] = $field;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('name','COM_EQA_GENERAL_UNIT');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('type','COM_EQA_GENERAL_UNIT_TYPE');

        //Set the option
        $this->itemFields = $option;
    }

	protected function prepareDataForLayoutDefault(): void
	{
		parent::prepareDataForLayoutDefault();

		//Preprocessing data for layout

		if(!empty($this->layoutData->items)) {
			foreach ($this->layoutData->items as $item) {
				$item->type = UnitHelper::UnitType($item->type);
			}
		}

	}
}
