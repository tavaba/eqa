<?php
namespace Kma\Component\Eqa\Administrator\View\Paperexam; //The namespace must end with the VIEW NAME.
use Joomla\CMS\Factory;
use Kma\Component\Eqa\Administrator\Base\EqaItemHtmlView;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

defined('_JEXEC') or die();

class HtmlView extends EqaItemHtmlView {
	protected $exam;
	protected $packages;
	protected function prepareDataForLayoutMasking()
	{
		$this->form = FormHelper::getBackendForm('com_eqa.masking','masking.xml',[]);
	}
	protected function addToolbarForLayoutMasking()
	{
		ToolbarHelper::title('Đánh số phách bài thi viết');
		ToolbarHelper::appenddButton('core.edit','save','JTOOLBAR_SAVE','paperexam.mask',false,null,true);
		$url = \JRoute::_('index.php?option=com_eqa&view=paperexams',false);
		ToolbarHelper::appendLink('core.manage',$url,'JTOOLBAR_CANCEL','delete','btn btn-danger');
	}
	protected function prepareDataForLayoutExaminers()
	{
		$app = Factory::getApplication();
		$model = $this->getModel();
		$examId = $app->input->getInt('exam_id');
		$this->exam = DatabaseHelper::getExamInfo($examId);
		$this->packages = $model->getPackages($examId);
	}
	protected function addToolbarForLayoutExaminers()
	{
		ToolbarHelper::title('Phân công chấm thi viết');
		ToolbarHelper::appendGoHome();
		ToolbarHelper::save('paperexam.saveExaminers');
		ToolbarHelper::appendCancelLink(\JRoute::_('index.php?option=com_eqa&view=paperexams'));
	}
}
