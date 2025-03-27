<?php
namespace Kma\Component\Eqa\Administrator\View\Examsessionemployees; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaItemHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutData;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Field\EmployeeField;
use Kma\Component\Eqa\Administrator\Field\ExamsessionemployeeField;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView {
	protected $examsession;
	protected function configureItemFieldsForLayoutDefault():void{
		$option = new EqaListLayoutItemFields();
		$option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
		$option->customFieldset1 = array();
		$f = new EqaListLayoutItemFieldOption('examroom','COM_EQA_EXAMROOM');
		$f->altField = 'description';   //Will be prepared
		$option->customFieldset1[] = $f;
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('monitor1','COM_EQA_EXAM_MONITOR1_ABBR');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('monitor2','COM_EQA_EXAM_MONITOR2_ABBR');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('monitor3','COM_EQA_EXAM_MONITOR3_ABBR');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('examiner1','COM_EQA_EXAM_EXAMINER1_ABBR');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('examiner2','COM_EQA_EXAM_EXAMINER2_ABBR');


		//Set the option
		$this->itemFields = $option;
	}
	protected function prepareDataForLayoutDefault(): void
	{
		//Get the examsession
		$app = Factory::getApplication();
		$examsessionId = $app->input->getInt('examsession_id');
		if(empty($examsessionId))
			return;
		$this->examsession = DatabaseHelper::getExamsessionInfo($examsessionId);
		if(empty($this->examsession))
			return;

		//Set the model state
		$model = $this->getModel();
		$model->setState('filter.examsession_id', $examsessionId);

		//Call parent
		parent::prepareDataForLayoutDefault();

		//Setup some options
		$this->layoutData->formHiddenFields['examsession_id']=$examsessionId;

		//Preprocessing
		$field = new ExamsessionemployeeField();
		if(!empty($this->layoutData->items))
		{
			foreach ($this->layoutData->items as $item){
				$item->monitor1 = $field->getElementHtml("jform[$item->id][monitor1_id]", $item->monitor1_id);
				$item->monitor2 = $field->getElementHtml("jform[$item->id][monitor2_id]", $item->monitor2_id);
				$item->monitor3 = $field->getElementHtml("jform[$item->id][monitor3_id]", $item->monitor3_id);
				$item->examiner1 = $field->getElementHtml("jform[$item->id][examiner1_id]", $item->examiner1_id);
				$item->examiner2 = $field->getElementHtml("jform[$item->id][examiner2_id]", $item->examiner2_id);

				//Nếu đã phân công CBCT, CBCTChT thì đặt nền xanh
				if(!empty($item->monitor1_id) || !empty($item->examiner1_id))
					$item->optionRowCssClass = 'table-success';

				//Thông tin mô tả về phòng thi
				$examroom = DatabaseHelper::getExamroomInfo($item->id);
				if(!empty($examroom))
				{
					$desc = '';
					if(!empty($examroom->exams))
						$desc .= 'Môn thi: ' . implode(', ', $examroom->exams) . '.';
					if(is_numeric($examroom->testtype))
						$desc .= ' Hình thức thi: ' . ExamHelper::getTestType($examroom->testtype) . '.';
					$desc .= ' Số thí sinh: ' . $examroom->examineeCount;
				}
				else
					$desc = 'Không xác định được thông tin phòng thi';
				$item->description = $desc;
			}
		}

	}
	protected function addToolbarForLayoutDefault(): void
	{
		ToolbarHelper::appendGoHome();
		ToolbarHelper::appendGoBack('examsession.cancel','COM_EQA_EXAMSESSION');
		ToolbarHelper::appendGoBack('examroom.cancel','COM_EQA_EXAMROOM');
		ToolbarHelper::apply('examsessionemployees.apply');
		ToolbarHelper::save('examsessionemployees.save');
		ToolbarHelper::cancel('examsession.cancel');
	}
}
