<?php
namespace Kma\Component\Eqa\Administrator\View\Buildings; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView {
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new ListLayoutItemFields();
        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = ListLayoutItemFields::defaultFieldCode();
        $option->customFieldset1[] = ListLayoutItemFields::defaultFieldDescription();
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('campus_name', 'Cơ sở đào tạo');
        $option->state = ListLayoutItemFields::defaultFieldState();

        //Set the option
        $this->itemFields = $option;
    }
}
