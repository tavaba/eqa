<?php
namespace Kma\Component\Eqa\Administrator\View\SecondAttempts;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends ItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new ListLayoutItemFields();

	    $option->sequence = ListLayoutItemFields::defaultFieldSequence();
	    $option->id = ListLayoutItemFields::defaultFieldId();
	    $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('class_id','class_id');
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('learner_id','learner_id');
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('payment_required','Phí');
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('payment_code','Mã thanh toán');
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('payment_completed','Đã thanh toán');

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
