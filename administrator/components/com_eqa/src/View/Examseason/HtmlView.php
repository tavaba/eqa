<?php
namespace Kma\Component\Eqa\Administrator\View\Examseason; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Enum\TestType;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Component\Eqa\Administrator\Base\ItemHtmlView;
use Kma\Library\Kma\View\ListLayoutData;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Model\ClassesModel;
use Kma\Component\Eqa\Administrator\Model\ExamseasonModel;

class HtmlView extends ItemHtmlView {
    protected $examseason;
    protected ListLayoutData $listLayoutData;
    protected ListLayoutItemFields $listLayoutItemFields;
	protected function prepareDataForLayoutAddexams(): void
	{
		//Determine the examseason id
		$examseasonId = Factory::getApplication()->input->getInt('examseason_id');
		if(empty($examseasonId))
			die('Không xác định được kỳ thi');

		/**
		 * Get the examseason item
		 * @var ExamseasonModel $itemModel
		 */
		$itemModel = $this->getModel('examseason');
		$this->examseason = $itemModel->getItem($examseasonId);

		//Set up the DEFAULT model, namely the list model 'subjects'
		//This model allow the layout to utilize ViewHelper to display a list of subjects
		//that the user can select to 'import' exams
		$existingSubjectIds = $itemModel->getSubjectIdsByExamseasonId($examseasonId);
		$termSubjectIds = $itemModel->getSubjectIdsByTerm($this->examseason->academicyear_id, $this->examseason->term);
		$limitSubjectIds = array_diff($termSubjectIds, $existingSubjectIds);
		$factory = ComponentHelper::getMVCFactory();
		$listModel = $factory->createModel('subjects');
		$listModel->setState('filter.limit_subject_ids',$limitSubjectIds);
		$this->setModel($listModel, true);

		//Prepare list layout data
		$this->listLayoutData = new ListLayoutData();
		$this->loadCommonListLayoutData($this->listLayoutData, $listModel);
		$this->listLayoutData->formActionParams = [
			'view' => 'examseason',
			'layout' => 'addexams',
			'examseason_id' => $examseasonId
		];

		//Cleanup list model's state after successful retrieving data
		$listModel->setState('filter.limit_subject_ids',null);


		//Preprocess the layout data
		if(!empty($this->listLayoutData->items)) {
			foreach ($this->listLayoutData->items as $item) {
				$item->finaltesttype = TestType::from($item->finaltesttype)->getLabel();
				$item->degree = CourseHelper::Degree($item->degree);
			}
		}


		//Prepare list layout item fields
		$this->listLayoutItemFields = new ListLayoutItemFields();
		$itemFields = $this->listLayoutItemFields; //Just shorten the name

		$itemFields->sequence = ListLayoutItemFields::defaultFieldSequence();
		$itemFields->check = ListLayoutItemFields::defaultFieldCheck();

		$itemFields->customFieldset1 = array();
		$field = new ListLayoutItemFieldOption('department_code', 'COM_EQA_GENERAL_SUBJECT_DEPARTMENT',true,false);
		$field->cellCssClasses = 'text-center';
		$itemFields->customFieldset1[] = $field;
		$field = new ListLayoutItemFieldOption('code','COM_EQA_GENERAL_SUBJECT_CODE', true, false);
		$field->cellCssClasses = 'text-center';
		$itemFields->customFieldset1[] = $field;
		$itemFields->customFieldset1[] = new ListLayoutItemFieldOption('name', 'COM_EQA_GENERAL_SUBJECT_NAME');
		$itemFields->customFieldset1[] = new ListLayoutItemFieldOption('degree','COM_EQA_GENERAL_COURSE_DEGREE',true,false,'text-center');
		$itemFields->customFieldset1[] = new ListLayoutItemFieldOption('finaltesttype','COM_EQA_GENERAL_SUBJECT_TESTTYPE', true, false);
		$field = new ListLayoutItemFieldOption('testbankyear', 'COM_EQA_GENERAL_SUBJECT_TESTBANK', true, false);
		$field->cellCssClasses = 'text-center';
		$itemFields->customFieldset1[] = $field;
		$itemFields->published = ListLayoutItemFields::defaultFieldPublished();
	}
	protected function addToolbarForLayoutAddexams(): void
	{
		ToolbarHelper::title('Thêm môn thi theo môn học');
		ToolbarHelper::save('examseason.addExams');
		$cancelLink = Route::_('index.php?option=com_eqa&view=examseasonexams&examseason_id='.$this->examseason->id);
		ToolbarHelper::appendCancelLink($cancelLink);
	}
	protected function prepareDataForLayoutAddExamsForClasses(): void
	{
		//Determine the examseason id
		$examseasonId = Factory::getApplication()->input->getInt('examseason_id');
		if(empty($examseasonId))
			die('Không xác định được kỳ thi');

		/**
		 * Get the examseason item
		 * @var ExamseasonModel $itemModel
		 */
		$itemModel = $this->getModel('examseason');
		$this->examseason = $itemModel->getItem($examseasonId);

		//Setup the list model
		/** @var ClassesModel $listModel */
		$factory = ComponentHelper::getMVCFactory();
		$listModel = $factory->createModel('classes');
		$listModel->setState('filter.academicyear_id', $this->examseason->academicyear_id);
		$listModel->setState('filter.term', $this->examseason->term);
		$this->setModel($listModel, true);

		//Prepare list layout data
		$this->listLayoutData = new ListLayoutData();
		$this->loadCommonListLayoutData($this->listLayoutData, $listModel);
		$this->listLayoutData->formActionParams = [
			'view' => 'examseason',
			'layout' => 'addExamsForClasses',
			'examseason_id' => $examseasonId
		];
		$this->listLayoutData->formHiddenFields = ['examseason_id'=>$examseasonId];

		//Preprocess the layout data
		if(!empty($this->listLayoutData->items)) {
			foreach ($this->listLayoutData->items as $item) {
				$item->lecturer = EmployeeHelper::getFullName($item->lecturer_id);
			}
		}

		//Prepare list layout item fields
		$option = new ListLayoutItemFields();

		$option->sequence = ListLayoutItemFields::defaultFieldSequence();
		$option->check = ListLayoutItemFields::defaultFieldCheck();

		$option->customFieldset1 = array();
		$option->customFieldset1[] = new ListLayoutItemFieldOption('academicyear','COM_EQA_ACADEMICYEAR', true,false,'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('term','COM_EQA_TERM', true,false,'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('code', 'COM_EQA_CLASS_CODE', true,false);
		$field = new ListLayoutItemFieldOption('name','COM_EQA_CLASS_NAME',true,false);
		$field->altField = 'description';
		$option->customFieldset1[] = $field;
		//Trường 'lecturer' thực tế không tồn tại
		// ==> Ở phần layout cần căn cứ vào lecturer_id để tính toán Họ và tên của lecturer.
		$option->customFieldset1[] = new ListLayoutItemFieldOption('lecturer','COM_EQA_LECTURER');
		$field = new ListLayoutItemFieldOption('size','COM_EQA_CLASS_SIZE', true,false,'text-center');
		$field->urlFormatString = 'index.php?option=com_eqa&view=classlearners&class_id=%d';
		$option->customFieldset1[] = $field;

		//Set the option
		$this->listLayoutItemFields = $option;
	}
	protected function addToolbarForLayoutAddExamsForClasses(): void
	{
		ToolbarHelper::title('Thêm môn thi theo lớp học phần');
		ToolbarHelper::save('examseason.addExamsForClasses');
		$cancelLink = Route::_('index.php?option=com_eqa&view=examseasonexams&examseason_id='.$this->examseason->id);
		ToolbarHelper::appendCancelLink($cancelLink);
	}
}
