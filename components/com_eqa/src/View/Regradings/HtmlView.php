<?php
namespace Kma\Component\Eqa\Site\View\Regradings;   //Must end with the View Name
defined('_JEXEC') or die();

use DateTime;
use Exception;
use Kma\Component\Eqa\Administrator\Enum\PpaaStatus;
use Kma\Component\Eqa\Administrator\Model\RegradingsModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class HtmlView extends ItemsHtmlView{
	protected $errorMessage;
	protected $examseason;
	protected function configureItemFieldsForLayoutDefault():void{
		$option = new ListLayoutItemFields();

		//Các trường thông tin
		$option->sequence = ListLayoutItemFields::defaultFieldSequence();
		$option->check = ListLayoutItemFields::defaultFieldCheck();
		$option->customFieldset1 = array();
		$option->customFieldset1[] = new ListLayoutItemFieldOption('learnerCode', 'Mã HVSV');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('learnerLastname', 'Họ đệm');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('learnerFirstname', 'Tên');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('examName', 'Môn thi');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('origMark', 'Điểm gốc');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('ppaaMark', 'Điểm PK');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('statusText', 'Trạng thái');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('description', 'Nội dung xử lý');
		$this->itemFields = $option;
	}
	protected function prepareDataForLayoutDefault(): void
	{
		try
		{
			//Check permissions
			if (!GeneralHelper::checkPermissions('eqa.supervise'))
				throw new Exception('Bạn không có quyền xem danh sách yêu cầu phúc khảo.');

			/**
			 * Create an instance of the backend model and set it as the default
			 * @var RegradingsModel $model
			 */
			$mvcFactory= ComponentHelper::getMVCFactory();
			$model = $mvcFactory->createModel('Regradings', 'Administrator');
			$this->setModel($model, true);

			//Gọi phương thức lớp cha
			parent::prepareDataForLayoutDefault();

			//Lấy thông tin về kỳ thi
			$model = $this->getModel();
			$examseasonId = $model->getFilteredExamSeasonId();
			if(!empty($examseasonId))
				$this->examseason = DatabaseHelper::getExamseasonInfo($examseasonId);

			//Tiền xử lý
			if(!empty($this->layoutData) && !empty($this->layoutData->items)){
				foreach ($this->layoutData->items as &$item)
				{
					$status = PpaaStatus::from($item->statusCode);
					$item->statusText = $status->getLabel();
					switch ($status){
						case PpaaStatus::Accepted:
							$item->optionRowCssClass='table-primary';
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
		catch (Exception $e)
		{
			$this->errorMessage=$e->getMessage();
		}
	}
	protected function addToolbarForLayoutDefault():void
	{
		//Title
		ToolbarHelper::title('Danh sách yêu cầu phúc khảo');
		if($this->errorMessage)
			return;

		// Add buttons to the toolbar
		ToolbarHelper::appendButton('eqa.supervise', 'download', 'Danh sách thu phí','regradings.downloadRegradingFee');

		// Render the toolbar
		ToolbarHelper::render();
	}

}