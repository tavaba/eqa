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

        $option->published = ListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
}
