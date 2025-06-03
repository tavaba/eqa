<?php
namespace Kma\Component\Eqa\Site\View\Gradecorrections;   //Must end with the View Name
defined('_JEXEC') or die();

use DateTime;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\Toolbar;
use Kma\Component\Eqa\Administrator\Base\EqaItemAction;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutData;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
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
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('constituentText', 'Điểm cần đính chính');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('reason', 'Mô tả yêu cầu');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('statusText', 'Trạng thái xử lý');
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

		//Bổ sung thêm tham số vào form (để controller thực hiện redirect)
		$this->layoutData->formHiddenFields['layout']='learnerrequests';

		//Tiền xử lý
		if(!empty($this->layoutData)){
			foreach ($this->layoutData->items as &$item)
			{
				$item->constituentText = ExamHelper::decodeMarkConstituent($item->constituent);
				$item->statusText = ExamHelper::decodePpaaStatus($item->status);
			}
		}
	}
	protected function setTitleAndToolbarForLayoutLearnerrequests():void
	{
		if(!isset($this->learner))
			return;

		//Title
		ToolbarHelper::title('Danh sách yêu cầu đính chính điểm của thí sinh');

		// Add buttons to the toolbar
		ToolbarHelper::deleteList('Bạn có chắc muốn xóa không?','gradecorrections.delete', 'Xóa yêu cầu');

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
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('constituentText', 'Thành phần');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('reason', 'Mô tả');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('statusText', 'Trạng thái');
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
				$item->constituentText = ExamHelper::decodeMarkConstituent($item->constituent);
			}
		}
	}
	protected function setTitleAndToolbarForLayoutSupervisor():void
	{
		//Title
		ToolbarHelper::title('Danh sách yêu cầu đính chính');

		// Add buttons to the toolbar
		ToolbarHelper::appenddButton('eqa.supervise', 'checkmark-circle', 'Chấp nhận','gradecorrections.accept',true,'btn btn-success');
		ToolbarHelper::appenddButton('eqa.supervise', 'cancel-circle', 'Từ chối','gradecorrections.showRejectForm',true,'btn btn-danger');
		ToolbarHelper::appenddButton('eqa.supervise', 'download', 'Tải danh sách','gradecorrections.download');

		// Render the toolbar
		ToolbarHelper::render();
	}


	protected function prepareDataForLayoutReject(): void
	{
		if(!Factory::getApplication()->getIdentity()->authorise('eqa.supervise','com_eqa'))
		{
			$this->accessDenied=true;
			return;
		}


		//Get data passed by the controller
		$session = Factory::getApplication()->getSession();
		$gradeCorrectionId = $session->get('gradecorrection_id');
		$session->remove('gradecorrection_id');
		if(!is_numeric($gradeCorrectionId))
		{
			$this->accessDenied=true;
			return;
		}

		$gradeCorrectionInfo = DatabaseHelper::getGradeCorrectionInfo($gradeCorrectionId);
		if(!$gradeCorrectionInfo)
		{
			$this->accessDenied=true;
			return;
		}

		$this->layoutData = new EqaListLayoutData();
		$form = FormHelper::getFrontendForm('com_eqa.rejectcorrectionrequest','rejectcorrectionrequest.xml',[]);
		$form->setValue('id',null, $gradeCorrectionId);
		$learner = $gradeCorrectionInfo->learnerCode . ' - ' . $gradeCorrectionInfo->learnerLastname . ' ' . $gradeCorrectionInfo->learnerFirstname;
		$form->setValue('learner',null, $learner);
		$form->setValue('exam', null, $gradeCorrectionInfo->exam);
		$form->setValue('constituent', null, ExamHelper::decodeMarkConstituent($gradeCorrectionInfo->constituent));
		$form->setValue('reason', null, $gradeCorrectionInfo->reason);
		$this->layoutData->form = $form;

		//Bổ sung thêm tham số form
		$this->layoutData->formHiddenFields['layout']='supervisor';
	}
	protected function setTitleAndToolbarForLayoutReject():void
	{
		//Title
		ToolbarHelper::title('Từ chối yêu cầu đính chính');

		// Add buttons to the toolbar
		ToolbarHelper::appenddButton('eqa.supervise', 'checkmark-circle', 'Từ chối yêu cầu','gradecorrections.reject',false,'btn btn-warning');
		ToolbarHelper::appenddButton('eqa.supervise', 'delete', 'Hủy','gradecorrections.cancel');

		// Render the toolbar
		ToolbarHelper::render();
	}
}