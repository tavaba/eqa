<?php
namespace Kma\Component\Eqa\Administrator\View\Monitoringexams; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView
{
	protected $examseason;
	protected function configureItemFieldsForLayoutDefault():void{
		$fields = new EqaListLayoutItemFields();
		$fields->sequence = EqaListLayoutItemFields::defaultFieldSequence();

		$fields->customFieldset1[] = new EqaListLayoutItemFieldOption('name','COM_EQA_EXAM',true);
		$fields->customFieldset1[] = new EqaListLayoutItemFieldOption('testtype','Hình thức thi',true);
		$fields->customFieldset1[] = new EqaListLayoutItemFieldOption('testdate','Ngày thi',true);
		$fields->customFieldset1[] = new EqaListLayoutItemFieldOption('requirequestions','Ra đề',false,false,'text-center');
		$fields->customFieldset1[] = new EqaListLayoutItemFieldOption('questiondate', 'Giao đề');
		$f = new EqaListLayoutItemFieldOption('nexamroom', 'COM_EQA_EXAMROOM',true,false,'text-center');
		$f->urlFormatString = 'index.php?option=com_eqa&view=examrooms&filter[exam_id]=%d';
		$fields->customFieldset1[] = $f;

		$f = new EqaListLayoutItemFieldOption('nexaminee','COM_EQA_EXAMINEE',true,false,'text-center');
		$f->urlFormatString = 'index.php?option=com_eqa&view=examexaminees&exam_id=%d';
		$fields->customFieldset1[] = $f;

		$fields->customFieldset1[] = new EqaListLayoutItemFieldOption('status', 'Tiến độ',true);

		//Set the option
		$this->itemFields = $fields;
	}

	public function prepareDataForLayoutDefault(): void
	{
		//Call parent prepare
		parent::prepareDataForLayoutDefault();

		//Load additional data
		$model = $this->getModel();
		$examseasonId = $model->getState('filter.examseason_id');
		if(empty($examseasonId))
			$examseasonId = DatabaseHelper::getExamseasonInfo()->id;
		$this->examseason = DatabaseHelper::getExamseasonInfo($examseasonId);    //Maybe null

		//Layout data preprocessing
		if(!empty($this->layoutData->items)){
			foreach ($this->layoutData->items as $item) {

				if($item->usetestbank>0	|| in_array($item->testtype, [
						ExamHelper::TEST_TYPE_PRACTICE,
						ExamHelper::TEST_TYPE_PROJECT,
						ExamHelper::TEST_TYPE_THESIS]))
					$item->requirequestions = null;
				else
					$item->requirequestions = Text::_('JYES');

				//Must be done AFTER 'requirequestions
				$item->testtype = ExamHelper::getTestType($item->testtype);

				//Test date
				$examTestTime = DatabaseHelper::getExamTestTime($item->id);
				if(!empty($examTestTime))
					$item->testdate = DatetimeHelper::getDayAndMonth($examTestTime);
				else
					$item->testdate = null;

				//Question date
				if(!empty($item->questiondate))
					$item->questiondate = DatetimeHelper::getDayAndMonth($item->questiondate);

				//2. Status
				if($item->status >= ExamHelper::EXAM_STATUS_MARK_FULL)
					$item->optionRowCssClass='table-success';
				$item->status = ExamHelper::ExamStatus($item->status);

				//4. 'description' = 'description' + many else
			}
		}

	}
	public function addToolbarForLayoutDefault(): void
	{
		ToolbarHelper::title($this->toolbarOption->title);
		ToolbarHelper::appendGoHome();
	}
}
