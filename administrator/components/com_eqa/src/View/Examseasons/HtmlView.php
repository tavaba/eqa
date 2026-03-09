<?php
namespace Kma\Component\Eqa\Administrator\View\Examseasons;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use DateTime;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Enum\ExamType;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

class HtmlView extends ItemsHtmlView
{

    protected function configureItemFieldsForLayoutDefault():void{
        $option = new ListLayoutItemFields();

		$option->id = ListLayoutItemFields::defaultFieldId();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new ListLayoutItemFieldOption('academicyear', 'COM_EQA_ACADEMICYEAR', false, false,'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('term', 'COM_EQA_TERM', false, false,'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('type', 'COM_EQA_GENERAL_EXAMSEASON_TYPE', false, false);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('attempt', 'COM_EQA_EXAM_ATTEMPT', false, false, 'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('name', 'COM_EQA_EXAMSEASON_NAME', false, true);
        $field = new ListLayoutItemFieldOption('nexam', 'COM_EQA_EXAM',true,false,'text-center');
        $field->urlFormatString = 'index.php?option=com_eqa&view=examseasonexams&examseason_id=%d';
        $option->customFieldset1[] = $field;
        $field = new ListLayoutItemFieldOption('nexamsession', 'COM_EQA_EXAMSESSION',true,false,'text-center');
        $field->urlFormatString = 'index.php?option=com_eqa&view=examsessions&filter[examseason_id]=%d';
        $option->customFieldset1[] = $field;
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('nentry', 'Lượt thi', false, false, 'text-end');
	    $field = new ListLayoutItemFieldOption('ppaa_req_enabled', 'Phúc khảo',false,false,'text-center');
		$field->printRaw = true;
		$field->altField='ppaa_req_deadline';
	    $option->customFieldset1[] = $field;
        $option->default = ListLayoutItemFields::defaultFieldDefault();
        $option->customFieldset2[] = new ListLayoutItemFieldOption('completed','COM_EQA_COMPLETED',false,false,'text-center');

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        //Disable 'publish' and 'unpublish' toolbar buttons
        $this->toolbarOption->taskPublish=false;
        $this->toolbarOption->taskUnpublish=false;

        //Preprocessing
        if(!empty($this->layoutData->items)) {
            foreach ($this->layoutData->items as $item) {
				$item->academicyear = DatetimeHelper::decodeAcademicYear($item->academicyear);
                $item->type = ExamType::from($item->type)->getLabel();
                if($item->completed){
                    $item->completed = Text::_('JYES');
                    $item->optionRowCssClass='table-active';
                    $item->optionIgnoreEditUrl=true;
                    $item->optionIgnoreToggleDefaultButton=true;
                }
                else
                    $item->completed=Text::_('JNO');

				//Trạng thái phúc khảo
				$timeover=false;
	            if (!empty($item->ppaa_req_deadline))
	            {
		            $deadlineTime = DateTime::createFromFormat('Y-m-d H:i:s', $item->ppaa_req_deadline);
					if($deadlineTime && $deadlineTime < new DateTime())
						$timeover=true;
	            }
				if($item->ppaa_req_enabled && !$timeover)
					$item->ppaa_req_enabled = '<span class="icon-eye text-success"> </span>';
				else
					$item->ppaa_req_enabled = '<span class="icon-stop-circle text-muted"> </span>';

            }
        }


    }
    protected function addToolbarForLayoutDefault(): void
    {
        parent::addToolbarForLayoutDefault();
	    ToolbarHelper::appendButton('core.edit.state','unlock','Mở phúc khảo','examseason.enablePpaaReq',true,'btn btn-success');
	    ToolbarHelper::appendButton('core.edit.state','lock','Đóng phúc khảo','examseason.disablePpaaReq',true,'btn btn-danger');
        ToolbarHelper::appendConfirmButton('core.edit.state','COM_EQA_MSG_CONFIRM_COMPLETE_EXAMSEASON','lock','Kết thúc đợt thi','examseasons.complete',true,'btn btn-danger');
	    ToolbarHelper::appendButton('core.edit.state', 'unlock','Mở (lại) đợt thi','examseasons.undoComplete',true, 'btn btn-success');
	    ToolbarHelper::appendButton('core.manage', 'download','DS thí sinh','examseason.exportExaminees',true);
	    ToolbarHelper::appendButton('core.manage', 'download','DS cấm thi','examseason.exportIneligibleEntries',true);
	    ToolbarHelper::appendButton('core.manage', 'download','DS kỷ luật','examseason.exportSanctions',true);
	    ToolbarHelper::appendButton('core.manage','download','Bảng điểm tổng hợp', 'examseason.exportLearnerMarks',true);
	    ToolbarHelper::appendButton('core.manage','download','Sản lượng', 'examseasons.exportProduction',true);
	    ToolbarHelper::appendButton('core.manage','download','Phổ điểm','examseasons.exportMarkStatistic',true);
	    ToolbarHelper::appendButton('core.manage','download','Báo cáo','examseasons.exportStatistic',true);
    }

}

