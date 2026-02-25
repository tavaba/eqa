<?php
namespace Kma\Component\Eqa\Administrator\View\Groups; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView {
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
		$option->id = ListLayoutItemFields::defaultFieldId();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new ListLayoutItemFieldOption('course','COM_EQA_GENERAL_COURSE',true,false,'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('admissionyear','COM_EQA_COURSE_ADMISSION_YEAR',true,false,'text-center');
        $field = new ListLayoutItemFieldOption('code','COM_EQA_GROUP_CODE',true,true,'text-center');
        $field->altField='description';
        $option->customFieldset1[] = $field;
        $field = new ListLayoutItemFieldOption('size','COM_EQA_GROUP_SIZE',true,false,'text-center');
        $field->urlFormatString = 'index.php?option=com_eqa&view=learners&filter[group_id]=%d';
        $option->customFieldset1[] = $field;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('homeroom','COM_EQA_GROUP_HOMEROOM_TEACHER');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('adviser','COM_EQA_GROUP_ADVISER');

        $option->published = ListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
}
