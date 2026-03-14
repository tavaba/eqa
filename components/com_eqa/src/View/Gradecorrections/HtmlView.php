<?php
namespace Kma\Component\Eqa\Site\View\Gradecorrections;   //Must end with the View Name
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\DataObject\ExamseasonInfo;
use Kma\Component\Eqa\Administrator\Enum\MarkConstituent;
use Kma\Component\Eqa\Administrator\Enum\PpaaStatus;
use Kma\Component\Eqa\Administrator\Model\GradecorrectionsModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView{
	protected ?ExamseasonInfo $examseason;
	protected ?string $errorMessage;
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
		try
		{
			if(!Factory::getApplication()->getIdentity()->authorise('eqa.supervise','com_eqa'))
				throw new Exception("Bạn không có quyền truy cập thông tin này");

			/**
			 * Create and set default model
			 * @var GradecorrectionsModel $model
			 */
			$model = ComponentHelper::createModel('Gradecorrections', 'Administrator');
			$this->setModel($model, true);

			//Gọi phương thức lớp cha
			parent::prepareDataForLayoutDefault();

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
		ToolbarHelper::appendButton('eqa.supervise', 'download', 'Tải danh sách','gradecorrections.download');

		// Render the toolbar
		ToolbarHelper::render();
	}
}