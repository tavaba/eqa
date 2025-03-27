<?php
namespace Kma\Component\Eqa\Administrator\View\Regradings; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView {
    protected function configureItemFieldsForLayoutDefault():void{
        $fields = $this->itemFields;      //Just shorten the name
        $fields->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('academicyear','Năm học',true,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('term', 'Học kỳ',true,false,'text-center');
	    $f = new EqaListLayoutItemFieldOption('name', 'Tên lớp',true,false);
		$f->urlFormatString = 'index.php?option=com_eqa&view=classlearners&class_id=%d';
	    $fields->customFieldset1[] = $f;
    }
	protected function prepareDataForLayoutDefault(): void
	{
		parent::prepareDataForLayoutDefault();

		//Set form hidden field for 'learner_id'
		$learnerId = Factory::getApplication()->input->getInt('learner_id');
		$this->layoutData->formHiddenFields['learner_id']=$learnerId;
	}
	protected function addToolbarForLayoutDefault(): void
    {
		ToolbarHelper::title('Danh sách các lớp học phần của HVSV');
        ToolbarHelper::appendGoHome();
		$url = JRoute::_('index.php?option=com_eqa&view=learners', false);
		ToolbarHelper::appendLink('core.manage', $url, 'Danh sách HVSV', 'arrow-up-2');
    }
}
