<?php
namespace Kma\Component\Eqa\Administrator\View\Courses;    //The namespace must end with the VIEW NAME.
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
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('program', 'COM_EQA_COURSE_PROGRAM');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('degree','COM_EQA_GENERAL_COURSE_DEGREE', true, false);
        $field = new EqaListLayoutItemFieldOption('code', 'COM_EQA_COURSE_CODE',true,true,'text-center');
        $field->altField='description';
        $option->customFieldset1[] = $field;
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('admissionyear','COM_EQA_COURSE_ADMISSION_YEAR',true,false,'text-center');

        $option->published = EqaListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
}
