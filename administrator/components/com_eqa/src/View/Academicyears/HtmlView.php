<?php
namespace Kma\Component\Eqa\Administrator\View\Academicyears; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;

class HtmlView extends ItemsHtmlView {
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = ListLayoutItemFields::defaultFieldCode();
        $option->customFieldset1[] = ListLayoutItemFields::defaultFieldDescription();

        $option->default = ListLayoutItemFields::defaultFieldDefault();
        $option->published = ListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
}
