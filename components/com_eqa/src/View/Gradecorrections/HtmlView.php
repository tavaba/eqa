<?php
namespace Kma\Component\Eqa\Site\View\Gradecorrections;   //Must end with the View Name
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutData;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class HtmlView extends EqaItemsHtmlView{
	protected $errorMessage;
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
			if(!Factory::getApplication()->getIdentity()->authorise('eqa.supervise','com_eqa'))
				throw new Exception("Bạn không có quyền truy cập thông tin này");

			//Create and set default model
			$mvcFactory = GeneralHelper::getMVCFactory();
			$model = $mvcFactory->createModel('Gradecorrections', 'Administrator');
			$this->setModel($model, true);

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
		catch(Exception $e){
			$this->errorMessage=$e->getMessage();
		}
	}
	protected function addToolbarForLayoutDefault():void
	{
		//Title
		ToolbarHelper::title('Danh sách yêu cầu đính chính');

		if ($this->errorMessage)
			return;

		// Add buttons to the toolbar
		ToolbarHelper::appenddButton('eqa.supervise', 'download', 'Tải danh sách','gradecorrections.download');

		// Render the toolbar
		ToolbarHelper::render();
	}
}