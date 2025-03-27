<?php
namespace Kma\Component\Eqa\Administrator\View\Subjects;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

class HtmlView extends EqaItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new EqaListLayoutItemFields();

        $option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $option->check = EqaListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $field = new EqaListLayoutItemFieldOption('department_code', 'COM_EQA_GENERAL_SUBJECT_DEPARTMENT',true,false);
        $field->cssClass = 'text-center';
        $option->customFieldset1[] = $field;
        $field = new EqaListLayoutItemFieldOption('code','COM_EQA_GENERAL_SUBJECT_CODE', true, true);
        $field->cssClass = 'text-center';
        $option->customFieldset1[] = $field;
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('name', 'COM_EQA_GENERAL_SUBJECT_NAME');
	    $option->customFieldset1[] = new EqaListLayoutItemFieldOption('degree','COM_EQA_GENERAL_COURSE_DEGREE',true,false,'text-center');
	    $option->customFieldset1[] = new EqaListLayoutItemFieldOption('credits','Số TC',true,false,'text-center');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('finaltesttype','COM_EQA_GENERAL_SUBJECT_TESTTYPE', true, false);
        $field = new EqaListLayoutItemFieldOption('testbankyear', 'COM_EQA_GENERAL_SUBJECT_TESTBANK', true, false);
        $field->cssClass = 'text-center';
        $option->customFieldset1[] = $field;

        $option->published = EqaListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        if(!empty($this->layoutData->items)) {
            foreach ($this->layoutData->items as $item) {
                $item->finaltesttype = ExamHelper::getTestType($item->finaltesttype);
                $item->degree = CourseHelper::Degree($item->degree);
            }
        }
    }
}
