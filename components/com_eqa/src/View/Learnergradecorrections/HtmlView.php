<?php
namespace Kma\Component\Eqa\Site\View\Learnergradecorrections;   //Must end with the View Name
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Kma\Component\Eqa\Administrator\Enum\MarkConstituent;
use Kma\Component\Eqa\Administrator\Enum\PpaaStatus;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class HtmlView extends ItemsHtmlView
{
	protected $examseason;
	protected $learner;
	protected $errorMessage;
	protected function configureItemFieldsForLayoutDefault():void{
		$option = new ListLayoutItemFields();

		//Các trường thông tin
		$option->sequence = ListLayoutItemFields::defaultFieldSequence();
		$option->check = ListLayoutItemFields::defaultFieldCheck();
		$option->customFieldset1 = array();
		$option->customFieldset1[] = new ListLayoutItemFieldOption('examName', 'Môn thi');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('constituentText', 'Thành phần');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('reason', 'Yêu cầu đính chính');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('statusText', 'Trạng thái xử lý');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('description', 'Kết quả xử lý');
		$this->itemFields = $option;
	}
	protected function prepareDataForLayoutDefault(): void
	{
		try{
			$app = Factory::getApplication();

			//Create a backend model instance
			$mvcFactory = ComponentHelper::getMVCFactory();
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
					$item->constituentText = MarkConstituent::from($item->constituentCode)->getLabel();
					$status = PpaaStatus::from($item->statusCode);
					$item->statusText = $status->getLabel();
					switch ($status){
						case PpaaStatus::Accepted:
							$item->optionRowCssClass='table-primary';
							break;
						case PpaaStatus::RequireInfo:
							$item->optionRowCssClass='table-warning';
							break;
						case PpaaStatus::Rejected:
							$item->optionRowCssClass='table-danger';
							break;
						case PpaaStatus::Done:
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
