<?php
namespace Kma\Component\Eqa\Site\View\Learnerexams;   //Must end with the View Name
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\Toolbar;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;

class HtmlView extends EqaItemsHtmlView{
	protected $examseason;
	protected $learner;
	protected $errorMessage;
	protected function configureItemFieldsForLayoutDefault():void{
		$option = new EqaListLayoutItemFields();

		//Các trường thông tin
		$option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
		$option->check = EqaListLayoutItemFields::defaultFieldCheck();
		$option->customFieldset1 = array();
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('academicyear', 'Năm học', true, false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('term', 'Học kỳ', true, false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('name', 'Môn thi', true);
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('attempt', 'Lần', false,false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('pam1', 'TP1', false,false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('pam2', 'TP2', false,false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('pam', 'ĐQT', false,false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('finalMark', 'Điểm thi', false,false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('moduleMark', 'Điểm HP', false,false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('moduleGrade', 'Điểm chữ', false,false, 'text-center');

		$this->itemFields = $option;
	}
	protected function prepareDataForLayoutDefault(): void
	{
		try{
			$app = Factory::getApplication();

			//Set a model from the backend
			$mvcFactory = GeneralHelper::getMVCFactory();
			$model = $mvcFactory->createModel('Learnerexams','Administrator');
			$this->setModel($model,true);

			//Check if a learner is specified. If not, use signed in user
			$learnerId = $app->input->getInt('learnerId');
			if (empty($learnerId))
				$learnerId = GeneralHelper::getSignedInLearnerId();
			if(empty($learnerId))
				throw new Exception("Không xác định được thí sinh");
			$model->setState('filter.learner_id',$learnerId);

			//Check permission
			$canView = $model->canViewList();
			if(!$canView)
				throw new Exception("Bạn không có quyền xem thông tin này");

			//Gọi phương thức lớp cha
			parent::prepareDataForLayoutDefault();
			$this->layoutData->formHiddenFields['learnerId'] = $learnerId;

			//Lấy thông tin về thí sinh và kỳ thi
			$this->learner = DatabaseHelper::getLearnerInfo($learnerId);
			$examseasonId = $model->getSelectedExamseasonId();
			if(!empty($examseasonId))
				$this->examseason = DatabaseHelper::getExamseasonInfo($examseasonId);

			//Clear the learner id filter
			$model->setState('filter.learner_id',null);


			//Tiền xử lý
			if(!empty($this->layoutData) && !empty($this->layoutData->items)){
				foreach ($this->layoutData->items as &$item)
				{
					ExamHelper::normalizeMarks($item);
				}
			}
		}
		catch (Exception $e){
			$this->errorMessage = $e->getMessage();
			return;
		}
	}
	protected function addToolbarForLayoutDefault():void
	{
		if($this->errorMessage)
			return;

		//Title
		ToolbarHelper::title('Danh sách môn thi của thí sinh');

		// Add buttons to the toolbar
		ToolbarHelper::appendButton(null,'dashboard','Yêu cầu phúc khảo','learnerexams.RequestRegrading',true);
		ToolbarHelper::appendButton(null,'warning','Yêu cầu đính chính điểm','learnerexam.ShowCorrectionRequestForm',true, 'btn btn-warning');

		// Render the toolbar
		ToolbarHelper::render();
	}
}