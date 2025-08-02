<?php
namespace Kma\Component\Eqa\Administrator\View\Mmproductions; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView {
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new EqaListLayoutItemFields();
        $option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $option->check = EqaListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('exam','Môn thi',true);
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('lastname','Họ đệm');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('firstname','Tên', true);
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('role','Vai trò');
	    $option->customFieldset1[] = new EqaListLayoutItemFieldOption('quantity','Số bài',true,false,'text-center');

        //Set the option
        $this->itemFields = $option;
    }
	protected function addToolbarForLayoutDefault(): void
	{
		ToolbarHelper::title($this->toolbarOption->title);
		ToolbarHelper::appendGoHome();
		ToolbarHelper::deleteList('Bạn có chắc muốn xóa không?','mmproductions.delete');
		$urlUpload = JRoute::_('index.php?option=com_eqa&view=mmproductions&layout=upload',false);
		ToolbarHelper::appendLink('core.create', $urlUpload,'Upload', 'upload');
	}

	protected function prepareDataForLayoutUpload(string $xmlFileName = '', string $formName = ''): void
	{
		$this->form = FormHelper::getBackendForm('com_eqa.mmproductions.upload', 'upload_mmproductions.xml',[]);
	}
	protected function addToolbarForLayoutUpload(): void
	{
		ToolbarHelper::title('Upload sản lượng chấm iTest');
		ToolbarHelper::save('mmproductions.import');
		ToolbarHelper::appendCancelLink(JRoute::_('index.php?option=com_eqa&view=mmproductions',false));
	}
}
