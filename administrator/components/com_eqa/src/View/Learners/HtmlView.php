<?php
namespace Kma\Component\Eqa\Administrator\View\Learners;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Library\Kma\View\ItemAction;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Library\Kma\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends ItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new ListLayoutItemFieldOption('admissionyear','COM_EQA_COURSE_ADMISSION_YEAR',true,false,'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('course', 'COM_EQA_GENERAL_COURSE', true, false, 'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('group', 'COM_EQA_GENERAL_EDUGROUP', true, false, 'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('code', 'COM_EQA_GENERAL_CODE_LEARNER', true,true,'text-center');
        $option->customFieldset1[] = ListLayoutItemFields::defaultFieldLastname();
        $option->customFieldset1[] = ListLayoutItemFields::defaultFieldFirstname();
		$option->customFieldset1[] = new ListLayoutItemFieldOption('debtor','COM_EQA_DEBT', false,false,'text-center');

        $option->published = ListLayoutItemFields::defaultFieldPublished();

		//Actions on item
	    $option->actions = [];
		$actionViewClasses = new ItemAction();
		$actionViewClasses->icon = 'users';
		$actionViewClasses->text = 'Danh sách các lớp học phần';
		$actionViewClasses->urlFormatString = JRoute::_('index.php?option=com_eqa&view=learnerclasses&learner_id=%d');
		$option->actions[] = $actionViewClasses;
	    $actionViewClasses = new ItemAction();
	    $actionViewClasses->icon = 'puzzle';
	    $actionViewClasses->text = 'Danh sách môn thi';
	    $actionViewClasses->urlFormatString = JRoute::_('index.php?option=com_eqa&view=learnerexams&learner_id=%d');
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
		ToolbarHelper::appendButton('core.edit','smiley','COM_EQA_RESET_DEBT','learners.resetDebt',true,'btn btn-success');
		ToolbarHelper::appendButton('core.edit','smiley-sad','COM_EQA_SET_DEBT','learners.setDebt',true,'btn btn-danger');
	}

	protected function prepareDataForLayoutAdddebtors(): void
	{
		$this->form = FormHelper::getBackendForm('com_eqa.debtors','debtors.xml',[]);
	}
	protected function addToolbarForLayoutAdddebtors(): void
	{
		ToolbarHelper::title(Text::_('COM_EQA_DEBTORS'));
		ToolbarHelper::appendGoHome();
		ToolbarHelper::appendButton('core.edit','save','JTOOLBAR_SAVE','learners.addDebtors',false,null, true);
		ToolbarHelper::cancel('learner.cancel');
	}
	protected function prepareDataForLayoutUpload(): void
	{
		$this->form = FormHelper::getBackendForm('com_eqa.upload.learners','upload_learners.xml', array());
	}
	protected function addToolbarForLayoutUpload(): void
	{
		ToolbarHelper::title('Nhập HVSV');
		ToolbarHelper::appendButton(['core.create','eqa.create.class'],'save','JTOOLBAR_SAVE','learners.import',false,null,true);
		ToolbarHelper::cancel('learner.cancel');
	}

}
