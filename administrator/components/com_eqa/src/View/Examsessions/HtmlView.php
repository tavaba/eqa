<?php
namespace Kma\Component\Eqa\Administrator\View\Examsessions; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use DateTime;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView {
    protected $examseason;
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new EqaListLayoutItemFields();
        $option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $option->check = EqaListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('dayofweek','COM_EQA_DAY_OF_WEEK',false,false,'text-center');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('dayofmonth','COM_EQA_DATE',false,false,'text-center');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('time','COM_EQA_HOUR',false,false,'text-center');
        $f = new EqaListLayoutItemFieldOption('name', 'COM_EQA_EXAMSESSION',false,true);
        $f->altField = 'examseason';
        $option->customFieldset1[] = $f;

        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('flexible','COM_EQA_FLEXIBLE',true,false,'text-center');

        $f = new EqaListLayoutItemFieldOption('nexamroom','COM_EQA_EXAMROOM',true,false,'text-center');
        $f->urlFormatString = 'index.php?option=com_eqa&view=examrooms&filter[examsession_id]=%d';
        $option->customFieldset1[] = $f;

	    $option->customFieldset1[] = new EqaListLayoutItemFieldOption('nexaminee','COM_EQA_EXAMINEE',true,false,'text-center');

        $f = new EqaListLayoutItemFieldOption('nmonitor', 'COM_EQA_EXAM_MONITOR_ABBR',true,false,'text-center');
	    $f->urlFormatString = 'index.php?option=com_eqa&view=examsessionemployees&examsession_id=%d';
	    $option->customFieldset1[] = $f;

        $f = new EqaListLayoutItemFieldOption('nexaminer','COM_EQA_EXAM_EXAMINER_ABBR',true,false,'text-center');
	    $f->urlFormatString = 'index.php?option=com_eqa&view=examsessionemployees&examsession_id=%d';
	    $option->customFieldset1[] = $f;

        $option->customFieldset1[] = EqaListLayoutItemFields::defaultFieldDescription();

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        //Load examseason
        $model = $this->getModel();
        $examseasonId = $model->getState('filter.examseason_id');
        $this->examseason = DatabaseHelper::getExamseasonInfo($examseasonId);       //Maybe null

        //Preprocessing
        if(!empty($this->layoutData->items)){
            $examsessionIds = array_map(function ($item){return $item->id;}, $this->layoutData->items);
            $examroomCounts = DatabaseHelper::getExamsessionExamroomCounts($examsessionIds);
            $examineeCounts = DatabaseHelper::getExamsessionExamineeCounts($examsessionIds);
            foreach ($this->layoutData->items as $item)
            {
                $item->flexible = $item->flexible ? Text::_('JYES') : Text::_('JNO');
                $item->dayofweek = DatetimeHelper::getDayOfWeek($item->start);
                $item->dayofmonth = DatetimeHelper::getDayAndMonth($item->start);
                $item->time = DatetimeHelper::getHourAndMinute($item->start);
                $item->nexaminee = $examineeCounts[$item->id];
                $item->nexamroom = $examroomCounts[$item->id];
	            $item->nmonitor = DatabaseHelper::getExamsessionMonitorCount($item->id);
	            $item->nexaminer = DatabaseHelper::getExamsessionExaminerCount($item->id);
            }
        }
    }
    protected function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title($this->toolbarOption->title);
        ToolbarHelper::appendGoHome();
        ToolbarHelper::appendButton('core.manage','arrow-up-2','COM_EQA_EXAMSEASON','examseason.cancel',false);
        ToolbarHelper::appendButton('core.manage','arrow-up-2','COM_EQA_EXAM','exam.cancel',false);
        ToolbarHelper::appendButton('core.create','plus-2','COM_EQA_BUTTON_ADD','examsession.add',false,'btn btn-success');
        ToolbarHelper::appendButton('core.create','plus-circle','COM_EQA_BUTTON_ADD_BATCH','examsession.addbatch',false,'btn btn-success');
        ToolbarHelper::appendDelete('examsessions.delete');
    }
}
