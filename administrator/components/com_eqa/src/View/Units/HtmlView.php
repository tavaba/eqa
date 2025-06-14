<?php
namespace Kma\Component\Eqa\Administrator\View\Units; //Must end with View Name
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;

class HtmlView extends EqaItemsHtmlView {
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new EqaListLayoutItemFields();
        $option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $option->check = EqaListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $field = new EqaListLayoutItemFieldOption('code', 'COM_EQA_GENERAL_CODE', false, true);
        $field->cssClass = 'text-center';
        $option->customFieldset1[] = $field;
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('name','COM_EQA_GENERAL_UNIT');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('type','COM_EQA_GENERAL_UNIT_TYPE');

        //Set the option
        $this->itemFields = $option;
    }
}
