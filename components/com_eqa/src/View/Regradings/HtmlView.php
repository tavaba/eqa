<?php
namespace Kma\Component\Eqa\Site\View\Regradings;   //Must end with the View Name
defined('_JEXEC') or die();

use DateTime;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\Toolbar;
use Kma\Component\Eqa\Administrator\Base\EqaItemAction;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Site\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class HtmlView extends EqaItemsHtmlView{
	protected $learner;
	protected function configureItemFieldsForLayoutLearnerrequests():void{
		$option = new EqaListLayoutItemFields();

		//Các trường thông tin
		$option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
		$option->check = EqaListLayoutItemFields::defaultFieldCheck();
		$option->customFieldset1 = array();
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('exam', 'Môn thi');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('credits', 'Số tín chỉ');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('statusText', 'Trạng thái phúc khảo');
		$this->itemFields = $option;
	}
	protected function prepareDataForLayoutLearnerrequests(): void
	{
		//Xác định learner và đưa thông tin (id) vào model
		$username      = GeneralHelper::getCurrentUsername();
		if(!$username)
			return;
		$this->learner = DatabaseHelper::getLearnerInfo($username);
		if($this->learner){
			$model = $this->getModel();
			$model->setState('learner_id',$this->learner->id);
		}
		else
			return;

		//Gọi phương thức lớp cha
		parent::prepareDataForLayoutDefault();

		//Bổ sung thêm tham số form
		$this->layoutData->formHiddenFields['layout']='learnerrequests';

		//Tiền xử lý
		if(!empty($this->layoutData)){
			foreach ($this->layoutData->items as &$item)
			{
				$item->statusText = ExamHelper::decodePpaaStatus($item->status);
			}
		}
	}
	protected function setTitleAndToolbarForLayoutLearnerrequests():void
	{
		if(!isset($this->learner))
			return;

		//Title
		ToolbarHelper::title('Danh sách yêu cầu phúc khảo của thí sinh');

		// Add buttons to the toolbar
		ToolbarHelper::deleteList('Bạn có chắc muốn xóa không?','regradings.delete', 'Xóa yêu cầu');

		// Render the toolbar
		ToolbarHelper::render();
	}


	protected function configureItemFieldsForLayoutSupervisor():void{
		$option = new EqaListLayoutItemFields();

		//Các trường thông tin
		$option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
		$option->check = EqaListLayoutItemFields::defaultFieldCheck();
		$option->customFieldset1 = array();
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('code', 'Mã HVSV');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('lastname', 'Họ đệm');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('firstname', 'Tên');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('exam', 'Môn thi');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('statusText', 'Trạng thái phúc khảo');
		$this->itemFields = $option;
	}
	protected function prepareDataForLayoutSupervisor(): void
	{
		if(!Factory::getApplication()->getIdentity()->authorise('eqa.supervise','com_eqa'))
		{
			$this->accessDenied=true;
			return;
		}

		//Gọi phương thức lớp cha
		parent::prepareDataForLayoutDefault();

		//Bổ sung thêm tham số form
		$this->layoutData->formHiddenFields['layout']='supervisor';

		//Tiền xử lý
		if(!empty($this->layoutData)){
			foreach ($this->layoutData->items as &$item)
			{
				$item->statusText = ExamHelper::decodePpaaStatus($item->status);
				switch ($item->status){
					case ExamHelper::EXAM_PPAA_STATUS_ACCEPTED:
						$item->optionRowCssClass='table-success';
						break;
					case ExamHelper::EXAM_PPAA_STATUS_REJECTED:
						$item->optionRowCssClass='table-danger';
						break;
				}
			}
		}
	}
	protected function setTitleAndToolbarForLayoutSupervisor():void
	{
		//Title
		ToolbarHelper::title('Danh sách yêu cầu phúc khảo');

		// Add buttons to the toolbar
		ToolbarHelper::appenddButton('eqa.supervise', 'checkmark-circle', 'Chấp nhận','regradings.accept',true,'btn btn-success');
		ToolbarHelper::appenddButton('eqa.supervise', 'cancel-circle', 'Từ chối','regradings.reject',true,'btn btn-danger');
		ToolbarHelper::appenddButton('eqa.supervise', 'download', 'Tải danh sách','regradings.download');

		// Render the toolbar
		ToolbarHelper::render();
	}
}