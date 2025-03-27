<?php
namespace Kma\Component\Eqa\Administrator\View\Employees;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;

class HtmlView extends EqaItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new EqaListLayoutItemFields();

        $option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $option->check = EqaListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $field = new EqaListLayoutItemFieldOption('unit_code','COM_EQA_GENERAL_UNIT',true,false);
        $field->cssClass = 'text-center';
        $field->altField='unit_name';
        $option->customFieldset1[] = $field;
        $field = new EqaListLayoutItemFieldOption('code','COM_EQA_GENERAL_CODE_EMPLOYEE',true,true);
        $field->cssClass = 'text-center';
        $option->customFieldset1[] = $field;
        $option->customFieldset1[] = EqaListLayoutItemFields::defaultFieldLastname();
        $option->customFieldset1[] = EqaListLayoutItemFields::defaultFieldFirstname();
        $option->customFieldset1[] = EqaListLayoutItemFields::defaultFieldEmail();
        $option->customFieldset1[] = EqaListLayoutItemFields::defaultFieldMobile();

        $option->published = EqaListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
}
