<?php
namespace Kma\Component\Eqa\Administrator\View\Regradings; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\DependentListsHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView {
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
	protected function addToolbarForLayoutDefault():void
	{
		//Title
		ToolbarHelper::title('Danh sách yêu cầu phúc khảo');

		// Add buttons to the toolbar
		ToolbarHelper::appendGoHome();
		ToolbarHelper::appendButton('core.manage', 'download', 'Bảng thu phí','regradings.downloadRegradingFee');
		ToolbarHelper::appendButton('core.manage', 'checkmark-circle', 'Chấp nhận','regradings.accept',true,'btn btn-success');
		ToolbarHelper::appendButton('core.manage', 'cancel-circle', 'Từ chối','regradings.reject',true,'btn btn-danger');
		ToolbarHelper::appendButton('eqa.supervise', 'plus-circle', 'Tạo yêu cầu PK','regradings.add',false,'btn btn-success');
		ToolbarHelper::appendDelete('regradings.delete','Xóa yêu cầu PK','Bạn có chắc muốn xóa yêu cầu phúc khảo?','eqa.supervise');
		ToolbarHelper::appendButton('core.manage', 'list', 'Phân công chấm','regradings.assignRegradingExaminers');
		ToolbarHelper::appendButton('core.manage', 'download', 'Bài thi iTest','regradings.downloadHybridRegradings');
		ToolbarHelper::appendButton('core.manage', 'download', 'Bài thi viết','regradings.downloadPaperRegradings');
		ToolbarHelper::appendButton('core.manage', 'download', 'Phiếu chấm thi viết','regradings.downloadPaperRegradingSheets');
		ToolbarHelper::appendButton('core.manage', 'download', 'Tổng hợp','regradings.download');
	}

	protected function prepareDataForLayoutAdd()
	{
		$this->form = FormHelper::getBackendForm('com_eqa.regradings.add', 'addRegradings.xml',[]);
		$this->wam->useScript('com_eqa.dependent_lists');
		DependentListsHelper::setup3Level(
			$this->wam,
			'',
			'examseason_id',
			'exam_id',
			'learner_ids',
			' -Chọn môn thi- ',
			'',
			Route::_('index.php?option=com_eqa&task=examseason.getJsonListOfExams', false),
			Route::_('index.php?option=com_eqa&task=exam.getJsonListOfExaminees', false)
		);
	}

	protected function addToolbarForLayoutAdd()
	{
		//Title
		ToolbarHelper::title('Thêm yêu cầu phúc khảo cho thí sinh');

		// Add buttons to the toolbar
		ToolbarHelper::save('regradings.add');
		$url = Route::_('index.php?option=com_eqa&view=regradings', false);
		ToolbarHelper::appendCancelLink($url);
	}
}
