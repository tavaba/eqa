<?php
namespace Kma\Component\Eqa\Administrator\View\Academicyears; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;

class HtmlView extends EqaItemsHtmlView {
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new EqaListLayoutItemFields();

        $option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $option->check = EqaListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = EqaListLayoutItemFields::defaultFieldCode();
        $option->customFieldset1[] = EqaListLayoutItemFields::defaultFieldDescription();

        $option->default = EqaListLayoutItemFields::defaultFieldDefault();
        $option->published = EqaListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
}
