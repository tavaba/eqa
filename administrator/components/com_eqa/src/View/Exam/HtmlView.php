<?php
namespace Kma\Component\Eqa\Administrator\View\Exam; //The namespace must end with the VIEW NAME.
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
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
		//Determine the exam id
		$examId = Factory::getApplication()->input->getInt('exam_id');
		if(empty($examId))
			die('Không xác định được môn thi');

		//Load the exam information
		$this->exam = DatabaseHelper::getExamInfo($examId);
		if(!$this->exam)
			die('Không tìm thấy thông tin môn thi');

		//Toolbar
		$this->toolbarOption->clearAllTask();
		$this->toolbarOption->title = Text::_('COM_EQA_EXAMINEES_OF_EXAM');
		$this->toolbarOption->taskCancel = true;

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
		$cancelUrl = Route::_('index.php?option=com_eqa&view=examexaminees&exam_id='.$this->exam->id,false);
		ToolbarHelper::appendCancelLink($cancelUrl);
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
		ToolbarHelper::appendButton('core.create','save','JTOOLBAR_SAVE','exam.distribute',false,null,true);
		ToolbarHelper::appendButton(null,'cancel','JTOOLBAR_CANCEL','exam.cancel');
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
		ToolbarHelper::appendButton('core.create','save','JTOOLBAR_SAVE','exam.distribute2',false,null,true);
		ToolbarHelper::appendButton(null,'cancel','JTOOLBAR_CANCEL','exam.cancel');
	}

	protected function prepareDataForLayoutUploaditest(){
		$this->form = FormHelper::getBackendForm('com_eqa.upload_itest','upload_itest.xml',[]);
	}
	protected function addToolbarForLayoutUploaditest():void
	{
		ToolbarHelper::title('Nhập điểm thi từ ca thi iTest');
		ToolbarHelper::appendButton('core.create','save','JTOOLBAR_SAVE','exam.importItest',false,null,true);
		$cancelUrl = \JRoute::_('index.php?option=com_eqa', false);
		ToolbarHelper::appendCancelLink($cancelUrl);
	}

	protected function prepareDataForLayoutQuestion(){
		$this->form = FormHelper::getBackendForm('com_eqa.examquestion','examquestion.xml',[]);
	}
	protected function addToolbarForLayoutQuestion():void
	{
		ToolbarHelper::title('Nhập thông tin tiếp nhận đề thi');
		ToolbarHelper::appendButton('core.create','save','JTOOLBAR_SAVE','exam.saveQuestion',false,null,true);
		$cancelUrl = \JRoute::_('index.php?option=com_eqa', false);
		ToolbarHelper::appendCancelLink($cancelUrl);
	}
}
