<?php
namespace Kma\Component\Eqa\Administrator\View\Fixer; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView{
	protected function configureItemFieldsForLayoutDefault(): void
	{
	}

	protected function prepareDataForLayoutFixpam():void
	{
		$this->layoutData->form = FormHelper::getBackendForm('com_eqa.fixer.fixpam','fixpam.xml', ['control'=>'jform']);
	}
	protected function addToolbarForLayoutFixpam(): void
	{
		ToolbarHelper::title('Chỉnh sửa điểm quá trình');
		ToolbarHelper::save('fixer.fixpam');
		ToolbarHelper::appendCancelLink(JRoute::_('index.php?option=com_eqa', false));
	}
}
