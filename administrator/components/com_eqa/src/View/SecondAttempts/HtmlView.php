<?php
namespace Kma\Component\Eqa\Administrator\View\SecondAttempts;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new EqaListLayoutItemFields();

	    $option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
	    $option->id = EqaListLayoutItemFields::defaultFieldId();
	    $option->check = EqaListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
	    $option->customFieldset1[] = new EqaListLayoutItemFieldOption('class_id','class_id');
	    $option->customFieldset1[] = new EqaListLayoutItemFieldOption('learner_id','learner_id');
	    $option->customFieldset1[] = new EqaListLayoutItemFieldOption('paid','paid');

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        //Call parent method
        parent::prepareDataForLayoutDefault();

        //Prepare data
        if(!empty($this->layoutData->items)){
            foreach ($this->layoutData->items as $item){
            }
        }

    }
    protected function addToolbarForLayoutDefault(): void
    {
	    ToolbarHelper::title('Danh sách thi lần 2');
		ToolbarHelper::appendGoHome();
		$msg = 'Loại bỏ các trường hợp đã thi và Thêm vào các trường hợp thi lần 2';
		ToolbarHelper::appendConfirmButton('core.create',$msg,'loop','Làm mới','secondattempts.refresh',false,null);
    }
}
