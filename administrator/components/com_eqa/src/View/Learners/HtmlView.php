<?php
namespace Kma\Component\Eqa\Administrator\View\Learners;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemAction;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new EqaListLayoutItemFields();

        $option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $option->check = EqaListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('admissionyear','COM_EQA_COURSE_ADMISSION_YEAR',true,false,'text-center');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('course', 'COM_EQA_GENERAL_COURSE', true, false, 'text-center');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('group', 'COM_EQA_GENERAL_EDUGROUP', true, false, 'text-center');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('code', 'COM_EQA_GENERAL_CODE_LEARNER', true,true,'text-center');
        $option->customFieldset1[] = EqaListLayoutItemFields::defaultFieldLastname();
        $option->customFieldset1[] = EqaListLayoutItemFields::defaultFieldFirstname();
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('debtor','COM_EQA_DEBT', false,false,'text-center');

        $option->published = EqaListLayoutItemFields::defaultFieldPublished();

		//Actions on item
	    $option->actions = [];
		$actionViewClasses = new EqaItemAction();
		$actionViewClasses->icon = 'users';
		$actionViewClasses->text = 'Danh sách các lớp học phần';
		$actionViewClasses->urlFormatStringForItemId = JRoute::_('index.php?option=com_eqa&view=learnerclasses&learner_id=%d');
		$option->actions[] = $actionViewClasses;
	    $actionViewClasses = new EqaItemAction();
	    $actionViewClasses->icon = 'puzzle';
	    $actionViewClasses->text = 'Danh sách môn thi';
	    $actionViewClasses->urlFormatStringForItemId = JRoute::_('index.php?option=com_eqa&view=learnerexams&learner_id=%d');
	    $option->actions[] = $actionViewClasses;

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
		//Call parent
        parent::prepareDataForLayoutDefault();

		//Toolbar
	    $this->toolbarOption->taskUpload = true;

		//Data preprocessing
	    if(!empty($this->layoutData->items)){
			foreach ($this->layoutData->items as $item)
			{
				$item->debtor = $item->debtor ? Text::_('JYES') : Text::_('JNO');
			}
	    }
    }
	protected function addToolbarForLayoutDefault(): void
	{
		parent::addToolbarForLayoutDefault();
		ToolbarHelper::appenddButton('core.edit','smiley','COM_EQA_RESET_DEBT','learners.resetDebt',true,'btn btn-success');
		ToolbarHelper::appenddButton('core.edit','smiley-sad','COM_EQA_SET_DEBT','learners.setDebt',true,'btn btn-danger');
	}

	protected function prepareDataForLayoutAdddebtors(): void
	{
		$this->form = FormHelper::getBackendForm('com_eqa.debtors','debtors.xml',[]);
	}
	protected function addToolbarForLayoutAdddebtors(): void
	{
		ToolbarHelper::title(Text::_('COM_EQA_DEBTORS'));
		ToolbarHelper::appendGoHome();
		ToolbarHelper::appenddButton('core.edit','save','JTOOLBAR_SAVE','learners.addDebtors',false,null, true);
		ToolbarHelper::cancel('learner.cancel');
	}
}
