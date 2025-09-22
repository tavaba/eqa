<?php
namespace Kma\Component\Eqa\Administrator\View\Examroom; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutData;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Field\ExamineeanomalyField;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

class HtmlView extends EqaItemHtmlView {
    protected $examroom;
	protected $examineeAnomalies;
	protected $anomalyField;
    protected function prepareDataForLayoutExaminees() : void
    {
        $examroomId = Factory::getApplication()->input->getInt('examroom_id');

        //Toolbar
        $this->toolbarOption->clearAllTask();
        $this->toolbarOption->title = Text::_('COM_EQA_EXAMINEES_OF_EXAMROOM');
        $this->toolbarOption->taskCancel = true;

        //Prepare model
        $factory = GeneralHelper::getMVCFactory();
        $model = $factory->createModel('examroomexaminees');
        $this->setModel($model,true);
        $model->setState('filter.examroom_id',$examroomId);   //Để dùng trong $model->getsListQuery()

        //Exam Item
        $this->examroom = DatabaseHelper::getExamroomInfo($examroomId);

        //List layout data
        $this->listLayoutData = new EqaListLayoutData();
        $this->loadCommonListLayoutData($this->listLayoutData, $model);
        $this->listLayoutData->formActionParams = [
            'view' => 'examroom',
            'layout' => 'examinees',
            'examroom_id' => $examroomId
        ];

        //Clear model state after successful retrieving data
        $model->setState('filter.examroom_id',null);   //Để dùng trong $model->getsListQuery()


        //Layout data preprocessing
        if(!empty($this->listLayoutData->items))
        {
            foreach ($this->listLayoutData->items as $item)
            {
                if($item->allowed)
                    $item->allowed = Text::_('JYES');
                else
                    $item->allowed = Text::_('JNO');
            }
        }


        //List Item Fields
        $this->listLayoutItemFields = new EqaListLayoutItemFields();
        $fields = $this->listLayoutItemFields;      //Just shorten the name
        $fields->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $fields->check = EqaListLayoutItemFields::defaultFieldCheck();

        $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('examinee_code','COM_EQA_EXAMINEE_CODE_ABBR', true, false, 'text-center');
        $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('learner_code','COM_EQA_LEARNER_CODE', true, false, 'text-center');
        $fields->customFieldset1[] = EqaListLayoutItemFields::defaultFieldLastname();
        $fields->customFieldset1[] = EqaListLayoutItemFields::defaultFieldFirstname();
        $f = new EqaListLayoutItemFieldOption('attempt', 'COM_EQA_EXAM_ATTEMPT_ABBR', true, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_EXAM_ATTEMPT');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('pam1', 'COM_EQA_PAM1_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_PAM1');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('pam2', 'COM_EQA_PAM2_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_PAM2');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('pam', 'COM_EQA_PAM_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_PAM');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('allowed', 'COM_EQA_ALLOWED_TO_TAKE_EXAM_ABBR', true, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_ALLOWED_TO_TAKE_EXAM');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('mark_final', 'COM_EQA_MARK_FINALEXAM_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_MARK_FINALEXAM');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('module_mark', 'COM_EQA_MODULE_MARK_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_MODULE_MARK');
        $fields->customFieldset1[] = $f;
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('module_base4_mark', 'Hệ 4', false, false, 'text-center');
	    $f = new EqaListLayoutItemFieldOption('module_grade', 'COM_EQA_MODULE_GRADE_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_MODULE_GRADE');
        $fields->customFieldset1[] = $f;

    }
	protected function addToolbarForLayoutExaminees(): void
	{
		ToolbarHelper::title($this->toolbarOption->title);
		ToolbarHelper::appendGoHome();
		ToolbarHelper::appendButton(null,'arrow-up-2','COM_EQA_EXAMSESSION','examsession.cancel');
		ToolbarHelper::appendButton(null,'arrow-up-2','COM_EQA_EXAM','exam.cancel');
		ToolbarHelper::appendButton(null,'arrow-up-2','COM_EQA_EXAMROOM','examroom.cancel');
		ToolbarHelper::appendButton('core.edit', 'plus-circle','COM_EQA_ADD_EXAMINEES','examroom.addExaminees',false,'btn btn-success');
		ToolbarHelper::appendButton('core.edit','shuffle','COM_EQA_CHANGE_EXAMROOM', 'examroom.change',true);
		ToolbarHelper::appendDelete('examroom.removeExaminees','COM_EQA_REMOVE_FROM_EXAMROOM','COM_EQA_MSG_CONFIRM_REMOVE_FROM_EXAMROOM');
	}
    protected function prepareDataForLayoutAddexaminees(): void
    {
        //Init
        $app = Factory::getApplication();

        //Toolbar
        $this->toolbarOption->clearAllTask();
        $this->toolbarOption->title = Text::_('COM_EQA_ADD_EXAMINEES_TO_EXAMROOM');

        //Determine the exam id and get the exam
        $examroomId = $app->input->getInt('examroom_id');
        $this->examroom = DatabaseHelper::getExamroomInfo($examroomId);

        //Load form
        $name = 'com_eqa.addexamroomexaminees';
        $source = 'addexamroomexaminees';
        $model = $this->getModel();
        $this->form = $model->getCustomForm($name,$source,[]);

    }
    protected function addToolbarForLayoutAddexaminees() : void
    {
        ToolbarHelper::title($this->toolbarOption->title);
        ToolbarHelper::appendButton('core.edit','save','JTOOLBAR_SAVE','examroom.addExaminees');
        $url = JRoute::_('index.php?option=com_eqa&view=examroom&layout=examinees&examroom_id='.$this->examroom->id,false);
        ToolbarHelper::appendLink(null,$url, 'JTOOLBAR_CANCEL', 'delete','btn btn-danger');
    }

	protected function prepareDataForLayoutAnomaly(): void
	{
		//Init
		$app = Factory::getApplication();

		//Get data
		$examroomId = $app->input->getInt('examroom_id',0);
		$model = $this->getModel();
		$this->examroom = DatabaseHelper::getExamroomInfo($examroomId);
		$this->examineeAnomalies = $model->getExamineeAnomalies($examroomId);
		$this->anomalyField = new ExamineeanomalyField();
	}
	protected function addToolbarForLayoutAnomaly() : void
	{
		ToolbarHelper::title('Thông tin bất thường phòng thi');
		ToolbarHelper::appendButton('core.edit','save','JTOOLBAR_APPLY','examroom.applyAnomaly');
		ToolbarHelper::appendButton('core.edit','save','JTOOLBAR_SAVE','examroom.saveAnomaly');
		ToolbarHelper::appendCancelLink(JRoute::_('index.php?option=com_eqa&view=examrooms',false));
	}
}
