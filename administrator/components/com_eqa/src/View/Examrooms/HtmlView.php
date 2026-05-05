<?php
namespace Kma\Component\Eqa\Administrator\View\Examrooms; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Model\AssessmentModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends ItemsHtmlView {
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new ListLayoutItemFields();
        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new ListLayoutItemFieldOption('dayofweek', 'COM_EQA_DAY_OF_WEEK', false, false, 'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('start', 'COM_EQA_EXAM_TIME', true, false, 'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('examsessionName', 'COM_EQA_EXAMSESSION');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('code','COM_EQA_ROOM');
        $f = new ListLayoutItemFieldOption('exams', 'COM_EQA_EXAM');
		$f->printRaw = true;
	    $option->customFieldset1[] = $f;

		/*
		 * URL này không dẫn đến một view/layout cụ thể mà gửi truy vấn đến controller
		 * để controller quyết định redirect tới view/layout nào, tùy thuộc vào
		 * trường hợp thi KTHP hay thi sát hạch
		 */
        $f = new ListLayoutItemFieldOption('nexaminee', 'COM_EQA_NUMBER_OF_EXAMINEES', true, false, 'text-center');
        $f->urlFormatString = 'index.php?option=com_eqa&task=examroom.viewExaminees&examroom_id=%d';
	    $option->customFieldset1[] = $f;

	    $f = new ListLayoutItemFieldOption('nmonitor', 'CBCT', true, false, 'text-center');
	    $f->urlFormatString      = 'index.php?option=com_eqa&view=examsessionemployees&examsession_id=%d';
	    $f->urlFormatStringField = 'examsessionId';
	    $option->customFieldset1[] = $f;

	    $f = new ListLayoutItemFieldOption('nexaminer', 'CBCTChT', true, false, 'text-center');
	    $f->urlFormatString      = 'index.php?option=com_eqa&view=examsessionemployees&examsession_id=%d';
	    $f->urlFormatStringField = 'examsessionId';
	    $option->customFieldset1[] = $f;

	    $f = new ListLayoutItemFieldOption('nanomaly', 'COM_EQA_ANOMALY', true, false, 'text-center');
	    $f->urlFormatString = 'index.php?option=com_eqa&task=examroom.editAnomaly&examroom_id=%d';
        $option->customFieldset1[] = $f;

            //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        /**
         * Data preprocessing
         * @var AssessmentModel $assessmentModel
         */
		$assessmentModel = ComponentHelper::createModel('Assessment');
        if(!empty($this->layoutData->items)){
            foreach ($this->layoutData->items as $item){
                //Chuẩn bị thông tin về Môn thi của Phòng thi để hiển thị
	            if(!empty($item->examIds))
	            {
		            $examIds = explode(',', $item->examIds);
					$examNames = DatabaseHelper::getExamNames($examIds);
					foreach ($examNames as &$examName){
						$examName = htmlspecialchars($examName);
					}
					unset($examName);
		            $item->exams = implode('<br/>',$examNames);
	            }
				else
					$item->exams = $assessmentModel->getAssmentTitleForExamroom($item->id);

				//Số lượng thí sinh, bất thường tùy thuộc vào bảng Assessments hay Exams
	            $isAssessmentRoom = $item->nAssessmentExaminee>0;
	            $item->nexaminee = $isAssessmentRoom ? $item->nAssessmentExaminee : $item->nExamExaminee;
	            $item->nanomaly = $isAssessmentRoom ? $item->nAssessmentAnomaly : $item->nExamAnomaly;

				//Số lượng CBCT
	            $nmonitor=0;
	            if($item->monitor1Id) $nmonitor++;
	            if($item->monitor2Id) $nmonitor++;
	            if($item->monitor3Id) $nmonitor++;
				$item->nmonitor = $nmonitor;

				//Số lượng CBCTChT
	            $nexaminer=0;
				if($item->examiner1Id) $nexaminer++;
				if($item->examiner2Id) $nexaminer++;
				$item->nexaminer = $nexaminer;

                //Dẫn xuất thông tin về thời gian
	            $item->start = DatetimeHelper::convertToLocalTime($item->start);
                $item->dayofweek = DatetimeHelper::getDayOfWeek($item->start);
                $item->start = DatetimeHelper::getDayAndTime($item->start);
            }
        }

    }
    protected function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title($this->toolbarOption->title);
        ToolbarHelper::appendGoHome();
        ToolbarHelper::appendButton(null,'arrow-up-2','COM_EQA_EXAM', 'exam.cancel');
        ToolbarHelper::appendButton(null,'arrow-up-2','COM_EQA_EXAMSESSION', 'examsession.cancel');
        ToolbarHelper::appendDelete('examrooms.delete');
	    ToolbarHelper::appendButton(null,'download','COM_EQA_EXPORT','examrooms.export',true);
    }

	protected function prepareDataForLayoutImport(): void
	{
		$this->form = FormHelper::getBackendForm('com_eqa.examroomreport', 'upload_examroomreport.xml',[]);
	}
	protected function addToolbarForLayoutImport(): void
	{
		ToolbarHelper::title(Text::_('COM_EQA_LOAD_EXAMROOM_REPORT'));
		ToolbarHelper::appendButton('core.edit','save','JTOOLBAR_SAVE','examrooms.import',false,null,true);
		$url = Route::_('index.php?option=com_eqa',false);
		ToolbarHelper::appendLink(null,$url,'JTOOLBAR_CANCEL','cancel','btn btn-danger');
	}

}
