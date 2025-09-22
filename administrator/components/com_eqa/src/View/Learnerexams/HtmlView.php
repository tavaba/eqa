<?php
namespace Kma\Component\Eqa\Administrator\View\Learnerexams; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView {
    protected $learner;
    protected function configureItemFieldsForLayoutDefault():void{
		//'attempt',   'allowed',   'isDebtor', 'anomaly',  'origMark',    'ppaa',   'ppaaMark',    'finalMark',    'moduleMark',    'moduleGrade',    'conclusion',   'description'
        $fields = $this->itemFields;      //Just shorten the name
        $fields->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('academicyear','Năm học',true,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('term', 'Học kỳ',false,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('name', 'Môn thi',true,false);
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('stimulType', 'KK', false, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('stimulValue', 'Điểm KK', false, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('pam1', 'TP1', false, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('pam2', 'TP2', false, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('pam', 'ĐQT', false, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('attempt', 'Lần', false, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('isDebtor', 'Nợ phí', false, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('anomaly', 'Bất thường', false, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('origMark', 'Điểm gốc', false, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('ppaa', 'PK', false, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('ppaaMark', 'Điểm PK', false, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('finalMark', 'Điểm thực', false, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('moduleMark', 'Điểm HP', false, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('moduleBase4Mark', 'Hệ 4', false, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('moduleGrade', 'Điểm chữ', false, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('conclusion', 'Kết luận', false, false, 'text-center');
    }
	protected function prepareDataForLayoutDefault(): void
	{
		//Prepare the model before calling parent method
		$learnerId = Factory::getApplication()->input->getInt('learner_id');
		$model = $this->getModel();
		$model->setState('filter.learner_id', $learnerId);
		parent::prepareDataForLayoutDefault();

		$this->learner = DatabaseHelper::getLearnerInfo($learnerId);
		$this->layoutData->formHiddenFields['learner_id'] = $learnerId;

		//Clear the learner id filter
		$model->setState('filter.learner_id', null);

		//preprocessing
		if(!empty($this->layoutData->items))
		{
			foreach ($this->layoutData->items as &$item) {
				$item->stimulType = $item->stimulType ? StimulationHelper::getStimulationType($item->stimulType) : '';
				$item->pam1 = $item->pam1 >=0 ? $item->pam1 : ExamHelper::markToText($item->pam1);
				$item->pam2 = $item->pam2 >=0 ? $item->pam2 : ExamHelper::markToText($item->pam2);
				$item->pam = $item->pam >=0 ? $item->pam : ExamHelper::markToText($item->pam);
				$item->isDebtor = $item->isDebtor ? 'Có' : '';
				$item->ppaa = ExamHelper::getPostPrimaryAssessmentAction($item->ppaa);
				$item->anomaly = ExamHelper::getAnomaly($item->anomaly);
				$item->conclusion = $item->conclusion ? ExamHelper::getConclusion($item->conclusion) : '';
			}
		}
	}

	protected function addToolbarForLayoutDefault(): void
    {
		ToolbarHelper::title('Danh sách môn thi của HVSV');
        ToolbarHelper::appendGoHome();
		$url = JRoute::_('index.php?option=com_eqa&view=learners', false);
		ToolbarHelper::appendLink('core.manage', $url, 'Danh sách HVSV', 'arrow-up-2');
    }
}
