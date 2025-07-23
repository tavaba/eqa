<?php
namespace Kma\Component\Eqa\Site\View\Learnergradecorrections;   //Must end with the View Name
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class HtmlView extends EqaItemsHtmlView
{
	protected $examseason;
	protected $learner;
	protected $errorMessage;
	protected function configureItemFieldsForLayoutDefault():void{
		$option = new EqaListLayoutItemFields();

		//Các trường thông tin
		$option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
		$option->check = EqaListLayoutItemFields::defaultFieldCheck();
		$option->customFieldset1 = array();
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('examName', 'Môn thi');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('constituentText', 'Thành phần');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('reason', 'Yêu cầu đính chính');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('statusText', 'Trạng thái xử lý');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('description', 'Kết quả xử lý');
		$this->itemFields = $option;
	}
	protected function prepareDataForLayoutDefault(): void
	{
		try{
			$app = Factory::getApplication();

			//Create a backend model instance
			$mvcFactory = GeneralHelper::getMVCFactory();
			$model = $mvcFactory->createModel('Gradecorrections', 'Administrator');
			$this->setModel($model, true);

			//Check if a learner is specified. If not, use signed in user
			//And then set a filter to the model
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

			//Clear the filter
			$model->setState('filter.learner_id', null);

			//Tiền xử lý
			if(!empty($this->layoutData) && !empty($this->layoutData->items)){
				foreach ($this->layoutData->items as &$item)
				{
					$item->constituentText = ExamHelper::decodeMarkConstituent($item->constituentCode);
					$item->statusText = ExamHelper::decodePpaaStatus($item->statusCode);
					switch ($item->statusCode){
						case ExamHelper::EXAM_PPAA_STATUS_ACCEPTED:
							$item->optionRowCssClass='table-primary';
							break;
						case ExamHelper::EXAM_PPAA_STATUS_REJECTED:
							$item->optionRowCssClass='table-danger';
							break;
						case ExamHelper::EXAM_PPAA_STATUS_DONE:
							$item->optionRowCssClass='table-success';
							break;
					}
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
		//Title
		ToolbarHelper::title('Yêu cầu đính chính điểm');
		if(!empty($this->errorMessage))
			return;

		// Add buttons to the toolbar
		// Add buttons to the toolbar
		ToolbarHelper::deleteList('Bạn có chắc muốn xóa không?','learnerregradings.delete', 'Xóa yêu cầu');

		// Render the toolbar
		ToolbarHelper::render();
	}
}
