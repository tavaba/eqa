<?php
namespace Kma\Component\Eqa\Administrator\View\Exam; //The namespace must end with the VIEW NAME.
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Base\EqaItemHtmlView;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

defined('_JEXEC') or die();

class HtmlView extends EqaItemHtmlView{
    protected object $exam;
	protected function prepareDataForLayoutAddexaminees()
	{
		//Init
		$app = Factory::getApplication();

		//Toolbar
		$this->toolbarOption->clearAllTask();
		$this->toolbarOption->title = Text::_('COM_EQA_EXAMINEES_OF_EXAM');
		$this->toolbarOption->taskCancel = true;

		//Determine the exam id and get the exam
		$examId = $app->input->getInt('exam_id');
		$this->exam = DatabaseHelper::getExamInfo($examId);

		//Load form
		$name = 'com_eqa.addexamexaminees';
		$source = 'addexamexaminees';
		$model = $this->getModel();
		$this->form = $model->getCustomForm($name,$source,[]);
	}
	protected function addToolbarForLayoutAddexaminees() : void
	{
		$option = $this->toolbarOption;
		ToolbarHelper::title($option->title);
		ToolbarHelper::save('exam.addExaminees','COM_EQA_BUTTON_ADD' );
		ToolbarHelper::cancel('exam.cancel');
	}

	protected function prepareDataForLayoutDistribute(){
		$examId = Factory::getApplication()->input->getInt('exam_id');
		$this->exam = DatabaseHelper::getExamInfo($examId);

		//Form
		$model = EqaAdminModel::cast($this->getModel());
		$this->form = $model->getCustomForm('com_eqa.examrooms','examrooms');

	}
	protected function addToolbarForLayoutDistribute():void
	{
		ToolbarHelper::title(Text::_('COM_EQA_MANAGER_EXAM_DISTRIBUTE_EXAMINEES'));
		ToolbarHelper::appenddButton('core.create','save','JTOOLBAR_SAVE','exam.distribute',false,null,true);
		ToolbarHelper::appenddButton(null,'cancel','JTOOLBAR_CANCEL','exam.cancel');
	}

	protected function prepareDataForLayoutDistribute2(){
		$examId = Factory::getApplication()->input->getInt('exam_id');
		$this->exam = DatabaseHelper::getExamInfo($examId);

		//Form
		$model = EqaAdminModel::cast($this->getModel());
		$this->form = $model->getCustomForm('com_eqa.distribution2','distribution2');

	}
	protected function addToolbarForLayoutDistribute2():void
	{
		ToolbarHelper::title(Text::_('COM_EQA_MANAGER_EXAM_DISTRIBUTE_EXAMINEES'));
		ToolbarHelper::appenddButton('core.create','save','JTOOLBAR_SAVE','exam.distribute2',false,null,true);
		ToolbarHelper::appenddButton(null,'cancel','JTOOLBAR_CANCEL','exam.cancel');
	}

	protected function prepareDataForLayoutUploaditest(){
		$this->form = FormHelper::getBackendForm('com_eqa.upload_itest','upload_itest.xml',[]);
	}
	protected function addToolbarForLayoutUploaditest():void
	{
		ToolbarHelper::title('Nhập điểm thi từ ca thi iTest');
		ToolbarHelper::appenddButton('core.create','save','JTOOLBAR_SAVE','exam.importItest',false,null,true);
		$cancelUrl = \JRoute::_('index.php?option=com_eqa', false);
		ToolbarHelper::appendCancelLink($cancelUrl);
	}

	protected function prepareDataForLayoutQuestion(){
		$this->form = FormHelper::getBackendForm('com_eqa.examquestion','examquestion.xml',[]);
	}
	protected function addToolbarForLayoutQuestion():void
	{
		ToolbarHelper::title('Nhập thông tin tiếp nhận đề thi');
		ToolbarHelper::appenddButton('core.create','save','JTOOLBAR_SAVE','exam.saveQuestion',false,null,true);
		$cancelUrl = \JRoute::_('index.php?option=com_eqa', false);
		ToolbarHelper::appendCancelLink($cancelUrl);
	}
}
