<?php
namespace Kma\Component\Eqa\Administrator\View\Programs; //must end with View Name
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
        $option->customFieldset1[] = new ListLayoutItemFieldOption('speciality', 'COM_EQA_PROGRAM_SPECIALITY', true, false, 'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('degree', 'COM_EQA_PROGRAM_DEGREE', true);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('format','COM_EQA_PROGRAM_FORMAT',true);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('approach','COM_EQA_PROGRAM_APPROACH',true);
        $field = new ListLayoutItemFieldOption('name', 'COM_EQA_PROGRAM_NAME', false, true);
        $field->altField='description';
        $option->customFieldset1[] = $field;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('firstrelease', 'COM_EQA_PROGRAM_FIRST_RELEASE', true, false,'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('lastupdate', 'COM_EQA_PROGRAM_LAST_UPDATE', true, false,'text-center');

        $option->published = ListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
}
