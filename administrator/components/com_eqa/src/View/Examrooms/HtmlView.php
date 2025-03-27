<?php
namespace Kma\Component\Eqa\Administrator\View\Examrooms; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView {
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new EqaListLayoutItemFields();
        $option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $option->check = EqaListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('dayofweek', 'COM_EQA_DAY_OF_WEEK', false, false, 'text-center');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('start', 'COM_EQA_EXAM_TIME', true, false, 'text-center');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('examsession', 'COM_EQA_EXAMSESSION');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('code','COM_EQA_ROOM');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('exams', 'COM_EQA_EXAM');
        $f = new EqaListLayoutItemFieldOption('nexaminee', 'COM_EQA_NUMBER_OF_EXAMINEES', true, false, 'text-center');
        $f->urlFormatString = 'index.php?option=com_eqa&view=examroom&layout=examinees&examroom_id=%d';
	    $option->customFieldset1[] = $f;
	    $f = new EqaListLayoutItemFieldOption('nanomaly', 'COM_EQA_ANOMALY', true, false, 'text-center');
	    $f->urlFormatString = 'index.php?option=com_eqa&view=examroom&layout=anomaly&examroom_id=%d';
        $option->customFieldset1[] = $f;

            //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        //Data preprocessing
        if(!empty($this->layoutData->items)){
            foreach ($this->layoutData->items as $item){
                //Chuẩn bị thông tin về Môn thi của Phòng thi để hiển thị
	            if(!empty($item->exam_ids))
	            {
		            $examIds = explode(',', $item->exam_ids);
					$examNames = DatabaseHelper::getExamNames($examIds);
		            $item->exams = implode('<br/>',$examNames);
	            }
				else
					$item->exams = null;

                //Dẫn xuất thông tin về thời gian
                $item->dayofweek = DatetimeHelper::getDayOfWeek($item->start);
                $item->start = DatetimeHelper::getDayAndTime($item->start);
            }
        }

    }
    protected function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title($this->toolbarOption->title);
        ToolbarHelper::appendGoHome();
        ToolbarHelper::appenddButton(null,'arrow-up-2','COM_EQA_EXAM', 'exam.cancel');
        ToolbarHelper::appenddButton(null,'arrow-up-2','COM_EQA_EXAMSESSION', 'examsession.cancel');
        ToolbarHelper::appendDelete('examrooms.delete');
	    ToolbarHelper::appenddButton(null,'download','COM_EQA_EXPORT','examrooms.export',true);
	    ToolbarHelper::appenddButton(null,'warning','COM_EQA_ANOMALY','examroom.editAnomaly',true,'btn btn-danger');
    }

	protected function prepareDataForLayoutImport()
	{
		$this->form = FormHelper::getBackendForm('com_eqa.examroomreport', 'upload_examroomreport.xml',[]);
	}
	protected function addToolbarForLayoutImport()
	{
		ToolbarHelper::title(Text::_('COM_EQA_LOAD_EXAMROOM_REPORT'));
		ToolbarHelper::appenddButton('core.edit','save','JTOOLBAR_SAVE','examrooms.import',false,null,true);
		$url = \JRoute::_('index.php?option=com_eqa',false);
		ToolbarHelper::appendLink(null,$url,'JTOOLBAR_CANCEL','cancel','btn btn-danger');
	}

}
