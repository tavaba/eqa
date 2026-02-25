<?php
namespace Kma\Component\Eqa\Administrator\View\Mmproductions; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use JRoute;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Library\Kma\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends ItemsHtmlView {
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new ListLayoutItemFields();
        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
		$option->customFieldset1[] = new ListLayoutItemFieldOption('exam','Môn thi',true);
		$option->customFieldset1[] = new ListLayoutItemFieldOption('lastname','Họ đệm');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('firstname','Tên', true);
		$option->customFieldset1[] = new ListLayoutItemFieldOption('role','Vai trò');
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('quantity','Số bài',true,false,'text-center');

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
