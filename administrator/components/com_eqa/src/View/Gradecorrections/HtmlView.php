<?php
namespace Kma\Component\Eqa\Administrator\View\Gradecorrections; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Enum\MarkConstituent;
use Kma\Component\Eqa\Administrator\Enum\PpaaStatus;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends ItemsHtmlView {
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
		$option->customFieldset1[] = new ListLayoutItemFieldOption('constituentText', 'Thành phần');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('reason', 'Mô tả');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('statusText', 'Trạng thái');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('description', 'Nội dung xử lý');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('handlers', 'Người xử lý');
		$this->itemFields = $option;
	}
	protected function prepareDataForLayoutDefault(): void
	{
		$user = Factory::getApplication()->getIdentity();
		if(!$user->authorise('core.manage', 'com_eqa'))
			die('Bạn không có quyền xem thông tin này');

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
				$status = PpaaStatus::tryFrom($item->statusCode);
				$item->statusText = $status->getLabel();
				switch ($item->statusCode){
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
				$item->constituentText = MarkConstituent::from($item->constituentCode)->getLabel();

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
	protected function addToolbarForLayoutDefault():void
	{
		//Title
		ToolbarHelper::title('Danh sách yêu cầu đính chính');

		// Add buttons to the toolbar
		ToolbarHelper::appendGoHome();
		ToolbarHelper::appendButton('core.manage', 'checkmark-circle', 'Chấp nhận','gradecorrection.accept',true,'btn btn-success');
		ToolbarHelper::appendButton('core.manage', 'cancel-circle', 'Từ chối','gradecorrection.reject',true,'btn btn-danger');
		ToolbarHelper::appendButton('core.manage', 'download', 'Phiếu xử lý','gradecorrection.downloadReviewForm',true);
		ToolbarHelper::appendButton('core.manage', 'edit', 'Xử lý','gradecorrection.correct',true);
		ToolbarHelper::appendButton('core.manage', 'download', 'Tổng hợp','gradecorrections.download');
	}

}
