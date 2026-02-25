<?php
namespace Kma\Component\Eqa\Administrator\View\Courses;    //The namespace must end with the VIEW NAME.
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
        $option->customFieldset1[] = new ListLayoutItemFieldOption('program', 'COM_EQA_COURSE_PROGRAM');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('degree','COM_EQA_GENERAL_COURSE_DEGREE', true, false);
        $field = new ListLayoutItemFieldOption('code', 'COM_EQA_COURSE_CODE',true,true,'text-center');
        $field->altField='description';
        $option->customFieldset1[] = $field;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('admissionyear','COM_EQA_COURSE_ADMISSION_YEAR',true,false,'text-center');

        $option->published = ListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
}
