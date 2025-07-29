<?php
namespace Kma\Component\Eqa\Administrator\View\Learnerclasses; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView {
    protected $learner;
    protected function configureItemFieldsForLayoutDefault():void{
        $fields = $this->itemFields;      //Just shorten the name
        $fields->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('academicyear','Năm học',true,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('term', 'Học kỳ',true,false,'text-center');
	    $field = new EqaListLayoutItemFieldOption('name', 'Tên lớp',true,false);
		$field->urlFormatString = 'index.php?option=com_eqa&view=classlearners&class_id=%d';
	    $fields->customFieldset1[] = $field;
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('pam1', 'TP1');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('pam2', 'TP2');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('pam', 'ĐQT');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('expired', 'Hết quyền thi');
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
				$item->expired = $item->expired ? 'Yes' : '';
				$item->pam1 = $item->pam1 >=0 ? $item->pam1 : ExamHelper::markToText($item->pam1);
				$item->pam2 = $item->pam2 >=0 ? $item->pam2 : ExamHelper::markToText($item->pam2);
				$item->pam = $item->pam >=0 ? $item->pam : ExamHelper::markToText($item->pam);
			}
		}
	}

	protected function addToolbarForLayoutDefault(): void
    {
		ToolbarHelper::title('Danh sách các lớp học phần của HVSV');
        ToolbarHelper::appendGoHome();
		$url = JRoute::_('index.php?option=com_eqa&view=learners', false);
		ToolbarHelper::appendLink('core.manage', $url, 'Danh sách HVSV', 'arrow-up-2');
    }
}
