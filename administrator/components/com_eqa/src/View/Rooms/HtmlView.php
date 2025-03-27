<?php
namespace Kma\Component\Eqa\Administrator\View\Rooms; //must end with View Name
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
        $option->customFieldset1[] = EqaListLayoutItemFields::defaultFieldCode();
        $field = new EqaListLayoutItemFieldOption('building', 'COM_EQA_GENERAL_BUILDING', true, false);
        $field->cssClass = 'text-center';
        $option->customFieldset1[] = $field;
        $field = new EqaListLayoutItemFieldOption('capacity', 'COM_EQA_GENERAL_ROOM_CAPACITY', true, false);
        $field->cssClass = 'text-center';
        $option->customFieldset1[] = $field;
        $field = new EqaListLayoutItemFieldOption('type', 'COM_EQA_GENERAL_ROOM_TYPE', true, false);
        $field->cssClass = 'text-center';
        $option->customFieldset1[] = $field;

        $option->published = EqaListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
}
