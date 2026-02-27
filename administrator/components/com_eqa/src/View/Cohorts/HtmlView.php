<?php
namespace Kma\Component\Eqa\Administrator\View\Cohorts;    //The namespace must end with the VIEW NAME.
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
        $option->customFieldset1[] = new ListLayoutItemFieldOption('code', 'Ký hiệu', true,true);
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('name','Tên gọi',true,false);
        $field = new ListLayoutItemFieldOption('size','Số HVSV', true,false,'text-center');
        $field->urlFormatString = 'index.php?option=com_eqa&view=cohortlearners&cohort_id=%d';
        $option->customFieldset1[] = $field;

		$option->published = ListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
}
