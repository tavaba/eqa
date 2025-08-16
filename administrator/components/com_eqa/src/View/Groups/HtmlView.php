<?php
namespace Kma\Component\Eqa\Administrator\View\Groups; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;

class HtmlView extends EqaItemsHtmlView {
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new EqaListLayoutItemFields();

        $option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
		$option->id = EqaListLayoutItemFields::defaultFieldId();
        $option->check = EqaListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('course','COM_EQA_GENERAL_COURSE',true,false,'text-center');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('admissionyear','COM_EQA_COURSE_ADMISSION_YEAR',true,false,'text-center');
        $field = new EqaListLayoutItemFieldOption('code','COM_EQA_GROUP_CODE',true,true,'text-center');
        $field->altField='description';
        $option->customFieldset1[] = $field;
        $field = new EqaListLayoutItemFieldOption('size','COM_EQA_GROUP_SIZE',true,false,'text-center');
        $field->urlFormatString = 'index.php?option=com_eqa&view=learners&filter[group_id]=%d';
        $option->customFieldset1[] = $field;
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('homeroom','COM_EQA_GROUP_HOMEROOM_TEACHER');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('adviser','COM_EQA_GROUP_ADVISER');

        $option->published = EqaListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
}
