<?php
namespace Kma\Component\Eqa\Administrator\View\Gradecorrections; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

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
		$this->itemFields = $option;
	}
	protected function prepareDataForLayoutDefault(): void
	{
		if(!Factory::getApplication()->getIdentity()->authorise('eqa.manage','com_eqa'))
		{
			$this->accessDenied=true;
			return;
		}

		//Gọi phương thức lớp cha
		parent::prepareDataForLayoutDefault();

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
				}
				$item->constituentText = ExamHelper::decodeMarkConstituent($item->constituentCode);
			}
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
		ToolbarHelper::appenddButton('core.manage', 'edit', 'Đính chính','gradecorrection.correct',true);
		ToolbarHelper::appenddButton('core.manage', 'download', 'Tổng hợp','gradecorrections.download');
	}


}
