<?php
namespace Kma\Component\Eqa\Administrator\View\Gradecorrection; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use JFactory;
use Joomla\CMS\Factory;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemHtmlView;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemHtmlView {
	protected $itemId;
	protected function prepareDataForLayoutReject()
	{
		$this->itemId = JFactory::getApplication()->input->getInt('id');
		$model = $this->getModel();
		$this->form = $model->getRejectForm($this->itemId);
	}
	protected function addToolbarForLayoutReject()
	{
		ToolbarHelper::title('Từ chối yêu cầu đính chính điểm');
		ToolbarHelper::appenddButton('core.edit','expired', 'Từ chối', 'gradecorrection.reject', false, 'btn btn-danger');
		ToolbarHelper::appendCancelLink(JRoute::_('index.php?option=com_eqa&view=gradecorrections', false));
	}
	protected function prepareDataForLayoutCorrect()
	{
		$this->itemId = JFactory::getApplication()->input->getInt('id');
		$model = $this->getModel();
		$this->form = $model->getCorrectionForm($this->itemId);
	}
	protected function addToolbarForLayoutCorrect()
	{
		ToolbarHelper::title('Đính chính điểm');
		ToolbarHelper::appenddButton('core.edit','save', 'JTOOLBAR_SAVE', 'gradecorrection.correct', false, 'btn btn-success', true);
		ToolbarHelper::appendCancelLink(JRoute::_('index.php?option=com_eqa&view=gradecorrections', false));
	}
}
