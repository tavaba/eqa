<?php
namespace Kma\Component\Eqa\Site\View\Regradings;   //Must end with the View Name
defined('_JEXEC') or die();

use DateTime;
use Exception;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\Toolbar;
use Kma\Component\Eqa\Administrator\Base\EqaItemAction;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class HtmlView extends EqaItemsHtmlView{
	protected $errorMessage;
	protected $examseason;
	protected function configureItemFieldsForLayoutDefault():void{
		$option = new EqaListLayoutItemFields();

		//Các trường thông tin
		$option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
		$option->check = EqaListLayoutItemFields::defaultFieldCheck();
		$option->customFieldset1 = array();
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('learnerCode', 'Mã HVSV');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('learnerLastname', 'Họ đệm');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('learnerFirstname', 'Tên');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('examName', 'Môn thi');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('origMark', 'Điểm gốc');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('ppaaMark', 'Điểm PK');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('statusText', 'Trạng thái');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('description', 'Nội dung xử lý');
		$this->itemFields = $option;
	}
	protected function prepareDataForLayoutDefault(): void
	{
		try
		{
			//Check permissions
			if (!GeneralHelper::checkPermissions('eqa.supervise'))
				throw new Exception('Bạn không có quyền xem danh sách yêu cầu phúc khảo.');

			//Create an instance of the backend model and set it as the default
			$mvcFactory= GeneralHelper::getMVCFactory();
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
		ToolbarHelper::appenddButton('eqa.supervise', 'download', 'Danh sách thu phí','regradings.downloadRegradingFee');

		// Render the toolbar
		ToolbarHelper::render();
	}

}