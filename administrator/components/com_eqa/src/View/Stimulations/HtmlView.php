<?php
namespace Kma\Component\Eqa\Administrator\View\Stimulations; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView {
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new EqaListLayoutItemFields();

        $option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $option->check = EqaListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('subject_code', 'COM_EQA_SUBJECT_CODE', true,false,'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('subject', 'COM_EQA_SUBJECT');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('learner_code', 'COM_EQA_LEARNER_CODE',true,false,'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('lastname', 'COM_EQA_LASTNAME');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('firstname', 'COM_EQA_FIRSTNAME', true, false);
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('type', 'COM_EQA_TYPE', true);
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('value', 'COM_EQA_MARK', false, false, 'text-center');
	    $option->customFieldset1[] = new EqaListLayoutItemFieldOption('reason', 'COM_EQA_REASON');
	    $option->customFieldset1[] = new EqaListLayoutItemFieldOption('used', 'Đã dùng', true, false,'text-center');

        //Set the option
        $this->itemFields = $option;
    }
	protected function prepareDataForLayoutDefault(): void
	{
		parent::prepareDataForLayoutDefault();

		//Data preprocessing
		if(!empty($this->layoutData->items)){
			foreach ($this->layoutData->items as $item){
				$item->type = StimulationHelper::getStimulationType($item->type);
				if($item->used)
				{
					$item->optionRowCssClass='table-success';
					$item->used = Text::_('JYES');
				}
				else
					$item->used = Text::_('JNO');

			}
		}
	}
	protected function addToolbarForLayoutDefault(): void
	{
		ToolbarHelper::title($this->toolbarOption->title);
		ToolbarHelper::appendGoHome();
		$urlAddStimulations = \JRoute::_('index.php?option=com_eqa&view=stimulations&layout=add', false);
		ToolbarHelper::appendLink('core.create', $urlAddStimulations, 'COM_EQA_ADD','plus-circle');
		$confirmMsg = Text::_('COM_EQA_MSG_CONFIRM_DELETE');
		ToolbarHelper::appendConfirmButton('core.delete', $confirmMsg, 'delete', 'JTOOLBAR_DELETE', 'subject.clearStimulations',true,'btn btn-danger');
	}

	protected function prepareDataForLayoutAdd()
	{
		//Toolbar
		$this->toolbarOption->clearAllTask();
		$this->toolbarOption->title = Text::_('COM_EQA_STIMULATION');
		$this->toolbarOption->taskCancel = true;

		//Load form
		$name = 'com_eqa.stimulate';
		$source = 'stimulate.xml';
		$this->form = FormHelper::getBackendForm($name, $source,[]);
	}
	protected function addToolbarForLayoutAdd() : void
	{
		$option = $this->toolbarOption;
		ToolbarHelper::title($option->title);
		ToolbarHelper::appendGoHome();
		ToolbarHelper::save('subject.stimulate');
		$urlStimulations = \JRoute::_('index.php?option=com_eqa&view=stimulations');
		ToolbarHelper::appendLink('core.manage', $urlStimulations,'JTOOLBAR_CANCEL','cancel','btn btn-danger');
	}
}
