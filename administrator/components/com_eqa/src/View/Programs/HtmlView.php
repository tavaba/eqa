<?php
namespace Kma\Component\Eqa\Administrator\View\Programs; //must end with View Name
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;

class HtmlView extends EqaItemsHtmlView {
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new EqaListLayoutItemFields();

        $option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $option->check = EqaListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('speciality', 'COM_EQA_PROGRAM_SPECIALITY', true, false, 'text-center');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('degree', 'COM_EQA_PROGRAM_DEGREE', true);
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('format','COM_EQA_PROGRAM_FORMAT',true);
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('approach','COM_EQA_PROGRAM_APPROACH',true);
        $field = new EqaListLayoutItemFieldOption('name', 'COM_EQA_PROGRAM_NAME', false, true);
        $field->altField='description';
        $option->customFieldset1[] = $field;
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('firstrelease', 'COM_EQA_PROGRAM_FIRST_RELEASE', true, false,'text-center');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('lastupdate', 'COM_EQA_PROGRAM_LAST_UPDATE', true, false,'text-center');

        $option->published = EqaListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
}
