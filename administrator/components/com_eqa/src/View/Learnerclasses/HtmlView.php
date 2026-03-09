<?php
namespace Kma\Component\Eqa\Administrator\View\Learnerclasses; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends ItemsHtmlView {
    protected $learner;
    protected function configureItemFieldsForLayoutDefault():void{
        $fields = $this->itemFields;      //Just shorten the name
        $fields->sequence = ListLayoutItemFields::defaultFieldSequence();
        $fields->customFieldset1[] = new ListLayoutItemFieldOption('academicyear','Năm học',true,false,'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('term', 'Học kỳ',true,false,'text-center');
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('subjectCode', 'Mã HP');
	    $field = new ListLayoutItemFieldOption('name', 'Tên lớp');
		$field->urlFormatString = 'index.php?option=com_eqa&view=classlearners&class_id=%d';
	    $fields->customFieldset1[] = $field;
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('credits', 'Số TC', false, false, 'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('pam1', 'TP1', false, false, 'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('pam2', 'TP2', false, false, 'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('pam', 'ĐQT', false, false, 'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('exam', 'Thi', false, false, 'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('moduleMark', 'HP10', false, false, 'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('moduleBase4Mark', 'HP4', false, false, 'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('moduleGrade', 'Chữ', false, false, 'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('attempt', 'Lần', false, false, 'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('expired', 'Hết quyền thi', false, false, 'text-center');
    }
	protected function prepareDataForLayoutDefault(): void
	{
		parent::prepareDataForLayoutDefault();

		$learnerId = Factory::getApplication()->input->getInt('learner_id');
		$this->learner = DatabaseHelper::getLearnerInfo($learnerId);
		$this->layoutData->formHiddenFields['learner_id'] = $learnerId;

		//preprocessing
		if(!empty($this->layoutData->items))
		{
			foreach ($this->layoutData->items as &$item) {
				$item->academicyear = DatetimeHelper::decodeAcademicYear($item->academicyear);
				$item->expired = $item->expired ? 'Yes' : '';
				$item->pam1 = $item->pam1 >=0 ? $item->pam1 : ExamHelper::markToText($item->pam1);
				$item->pam2 = $item->pam2 >=0 ? $item->pam2 : ExamHelper::markToText($item->pam2);
				$item->pam = $item->pam >=0 ? $item->pam : ExamHelper::markToText($item->pam);

				//Load additional info
				$classId = $item->id;
				$lastExamResult = DatabaseHelper::getLastExamResultOfLearnerOfClass($classId,$learnerId);
				if (!empty($lastExamResult)) {
					$item->exam = $lastExamResult['mark_final'];
					$item->moduleMark = $lastExamResult['module_mark'];
					$item->moduleBase4Mark = $lastExamResult['module_base4_mark'];
					$item->moduleGrade = $lastExamResult['module_grade'];
					$item->attempt = $lastExamResult['attempt'];
				}
				else{
					$item->exam = null;
					$item->moduleMark = null;
					$item->moduleBase4Mark = null;
					$item->moduleGrade = null;
					$item->attempt = null;
				}
			}
		}

		//Set form params for layout
		$this->layoutData->formActionParams = [
			'view'=>'learnerclasses',
			'learner_id'=>$learnerId
		];
	}

	protected function addToolbarForLayoutDefault(): void
    {
		ToolbarHelper::title('Danh sách các lớp học phần của HVSV');
        ToolbarHelper::appendGoHome();
		$url = JRoute::_('index.php?option=com_eqa&view=learners', false);
		ToolbarHelper::appendLink('core.manage', $url, 'Danh sách HVSV', 'arrow-up-2');
    }
}
