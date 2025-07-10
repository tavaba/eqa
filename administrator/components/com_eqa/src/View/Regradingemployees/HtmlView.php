<?php
namespace Kma\Component\Eqa\Administrator\View\Regradingemployees; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Field\ExamsessionemployeeField;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView {
	protected $examseason;
	protected function configureItemFieldsForLayoutDefault():void{
		$option = new EqaListLayoutItemFields();
		$option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
		$option->customFieldset1 = array();
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('name','Môn thi');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('count','Số bài', false, false,'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('examiner1','Cán bộ chấm thi 1');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('examiner2','Cán bộ chấm thi 2');

		//Set the option
		$this->itemFields = $option;
	}
	protected function prepareDataForLayoutDefault(): void
	{
		//Get the examsession
		$app = Factory::getApplication();
		$examseasonId = $app->input->getInt('examseason_id');
		if(empty($examseasonId))
		{
			echo '<div class="alert alert-danger"> Không tìm thấy kỳ thi.</div>';
			return;
		}
		$this->examseason = DatabaseHelper::getExamseasonInfo($examseasonId);
		if(empty($this->examseason))
		{
			echo '<div class="alert alert-danger"> Không tìm thấy kỳ thi.</div>';
			return;
		}

		//Khởi tạo model 'regradings'
		$factory = GeneralHelper::getMVCFactory();
		$model = $factory->createModel('regradings');

		//Setup layout data
		$this->layoutData->items = $model->getRegradingExams($examseasonId);
		$this->layoutData->filterForm = null;
		$this->layoutData->activeFilters = null;
		$this->layoutData->pagination = null;
		$this->layoutData->showPaginationLimitBox = false;
		$this->layoutData->listOrderingField = '';
		$this->layoutData->listOrderingDirection = '';

		//Setup some options
		$this->layoutData->formHiddenFields['examseason_id']=$examseasonId;

		//Preprocessing
		$field = new ExamsessionemployeeField();
		if(!empty($this->layoutData->items))
		{
			foreach ($this->layoutData->items as $exam){
				if(!$exam->examiner1Completed)
					$exam->examiner1Id = null;
				if(!$exam->examiner2Completed)
					$exam->examiner2Id = null;
				$exam->examiner1 = $field->getElementHtml("jform[$exam->id][examiner1_id]", $exam->examiner1Id);
				$exam->examiner2 = $field->getElementHtml("jform[$exam->id][examiner2_id]", $exam->examiner2Id);
			}
		}
	}
	protected function addToolbarForLayoutDefault(): void
	{
		ToolbarHelper::title('Phân công cán bộ chấm phúc khảo');
		ToolbarHelper::apply('regradings.applyRegradingExaminers');
		ToolbarHelper::save('regradings.saveRegradingExaminers');
		ToolbarHelper::appendCancelLink(\JRoute::_('index.php?option=com_eqa&view=regradings',false));
	}
}
