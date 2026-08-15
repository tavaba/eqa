<?php
namespace Kma\Component\Eqa\Administrator\View\Employees;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $field = new ListLayoutItemFieldOption('unit_code','COM_EQA_GENERAL_UNIT',true,false);
        $field->cellCssClasses = 'text-center';
        $field->altField='unit_name';
        $option->customFieldset1[] = $field;
        $field = new ListLayoutItemFieldOption('code','COM_EQA_GENERAL_CODE_EMPLOYEE',true,true);
        $field->cellCssClasses = 'text-center';
        $option->customFieldset1[] = $field;
        $option->customFieldset1[] = ListLayoutItemFields::defaultFieldLastname();
        $option->customFieldset1[] = ListLayoutItemFields::defaultFieldFirstname();
        $option->customFieldset1[] = ListLayoutItemFields::defaultFieldEmail();
        $option->customFieldset1[] = ListLayoutItemFields::defaultFieldMobile();
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('campus_name', 'Cơ sở đào tạo');
        $option->state = ListLayoutItemFields::defaultFieldState();

        //Set the option
        $this->itemFields = $option;
    }

	protected function prepareDataForLayoutDefault(): void
	{
		parent::prepareDataForLayoutDefault();

		//Preprocessing
		if(!empty($this->layoutData->items)) {
			foreach ($this->layoutData->items as $item) {
				if (empty($item->code))
					$item->code = 'N/A';
			}
		}
	}
}
