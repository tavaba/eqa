<?php
namespace Kma\Component\Eqa\Administrator\View\Gradecorrections; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Field\ExamsessionemployeeField;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView {
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
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('constituentText', 'Thành phần');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('reason', 'Mô tả');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('statusText', 'Trạng thái');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('description', 'Nội dung xử lý');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('handlers', 'Người xử lý');
		$this->itemFields = $option;
	}
	protected function prepareDataForLayoutDefault(): void
	{
		try
		{
			if(!Factory::getApplication()->getIdentity()->authorise('core.manage','com_eqa'))
				throw new Exception('Bạn không có quyền xem thông tin này');

			//Gọi phương thức lớp cha
			parent::prepareDataForLayoutDefault();

			//Lấy thông tin về kỳ thi
			$model = $this->getModel();
			$examseasonId = $model->getSelectedExamSeasonId();
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
						case ExamHelper::EXAM_PPAA_STATUS_REQUIRE_INFO:
							$item->optionRowCssClass='table-warning';
							break;
						case ExamHelper::EXAM_PPAA_STATUS_REJECTED:
							$item->optionRowCssClass='table-danger';
							break;
						case ExamHelper::EXAM_PPAA_STATUS_DONE:
							$item->optionRowCssClass='table-success';
							break;
					}
					$item->constituentText = ExamHelper::decodeMarkConstituent($item->constituentCode);

					//in $item->reason and $item->description repalce \n by <br/>
					if (!empty($item->reason)) $item->reason = str_replace("\n", "<br/>", $item->reason);
					if (!empty($item->description)) $item->description = str_replace("\n", "<br/>", $item->description);

					//Handlers
					$handlers = [];
					if(isset($item->handledBy))
						$handlers[] = Text::sprintf('1. %s (%s)', $item->handledBy, $item->handledAt);
					if(isset($item->reviewerLastname) || isset($item->reviewerFirstname))
						$handlers[] = Text::sprintf('2. %s', implode(' ', [$item->reviewerLastname,$item->reviewerFirstname]));
					if(isset($item->updatedBy))
						$handlers[] = Text::sprintf('3. %s (%s)', $item->updatedBy, $item->updatedAt);
					$item->handlers = empty($handlers) ? '': implode('<br/>',$handlers);
				}
			}
		}
		catch (Exception $e)
		{
			$this->errorMessage = $e->getMessage();
		}
	}
	protected function addToolbarForLayoutDefault():void
	{
		//Title
		ToolbarHelper::title('Danh sách yêu cầu đính chính');

		// Add buttons to the toolbar
		ToolbarHelper::appendGoHome();
		ToolbarHelper::appenddButton('core.manage', 'checkmark-circle', 'Chấp nhận','gradecorrection.accept',true,'btn btn-success');
		ToolbarHelper::appenddButton('core.manage', 'cancel-circle', 'Từ chối','gradecorrection.reject',true,'btn btn-danger');
		ToolbarHelper::appenddButton('core.manage', 'download', 'Phiếu xử lý','gradecorrection.downloadReviewForm',true);
		ToolbarHelper::appenddButton('core.manage', 'edit', 'Xử lý','gradecorrection.correct',true);
		ToolbarHelper::appenddButton('core.manage', 'download', 'Tổng hợp','gradecorrections.download');
	}
}
