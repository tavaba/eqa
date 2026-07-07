<?php
namespace Kma\Component\Eqa\Administrator\View\Examsessions; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Model\ExamsessionsModel;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends ItemsHtmlView {
    protected $examseason;
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new ListLayoutItemFields();
        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new ListLayoutItemFieldOption('dayofweek','COM_EQA_DAY_OF_WEEK',false,false,'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('dayofmonth','COM_EQA_DATE',false,false,'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('time','COM_EQA_HOUR',false,false,'text-center');

		//Cột 'Ca thi': tên ca thi
        $f = new ListLayoutItemFieldOption('name', 'COM_EQA_EXAMSESSION',false,true);
        $f->altField = 'examseason';
        $option->customFieldset1[] = $f;

	    // Cột 'Môn thi': danh sách môn thi (kèm mã) trong ca thi, render sẵn dạng HTML <ol>
	    $f = new ListLayoutItemFieldOption('exams', 'Môn thi', false, false, 'text-start');
	    $f->printRaw = true;
	    $option->customFieldset1[] = $f;

        //$option->customFieldset1[] = new ListLayoutItemFieldOption('flexible','COM_EQA_FLEXIBLE',true,false,'text-center');

        $f = new ListLayoutItemFieldOption('nexamroom','COM_EQA_EXAMROOM',true,false,'text-center');
        $f->urlFormatString = 'index.php?option=com_eqa&view=examrooms&filter[examsession_id]=%d';
        $option->customFieldset1[] = $f;

	    $option->customFieldset1[] = new ListLayoutItemFieldOption('nexaminee','COM_EQA_EXAMINEE',true,false,'text-center');

        $f = new ListLayoutItemFieldOption('nmonitor', 'COM_EQA_EXAM_MONITOR_ABBR',true,false,'text-center');
	    $f->urlFormatString = 'index.php?option=com_eqa&view=examsessionEmployees&examsession_id=%d';
	    $option->customFieldset1[] = $f;

        $f = new ListLayoutItemFieldOption('nexaminer','COM_EQA_EXAM_EXAMINER_ABBR',true,false,'text-center');
	    $f->urlFormatString = 'index.php?option=com_eqa&view=examsessionEmployees&examsession_id=%d';
	    $option->customFieldset1[] = $f;

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        /**
         * Load examseason info
         * @var ExamsessionsModel $model
         */
        $model = $this->getModel();
        $examseasonId = (int)$model->getState('filter.examseason_id');
		if($examseasonId)
            $this->examseason = DatabaseHelper::getExamseasonInfo($examseasonId);       //Maybe null

		//Preprocessing
	    if(!empty($this->layoutData->items)){
		    $examsessionIds = array_map(function ($item){return $item->id;}, $this->layoutData->items);

		    // Lấy danh sách môn thi cho tất cả ca thi trong 1 lần truy vấn (tránh N+1)
		    $examsMap = DatabaseHelper::getExamsessionExamNames($examsessionIds);

		    foreach ($this->layoutData->items as $item)
		    {
			    $item->start = DatetimeHelper::convertToLocalTime($item->start);
			    $item->flexible = $item->flexible ? Text::_('JYES') : Text::_('JNO');
			    $item->dayofweek = DatetimeHelper::getDayOfWeek($item->start);
			    $item->dayofmonth = DatetimeHelper::getDayAndMonth($item->start);
			    $item->time = DatetimeHelper::getHourAndMinute($item->start);
			    $item->nmonitor = DatabaseHelper::getExamsessionMonitorCount($item->id);
			    $item->nexaminer = DatabaseHelper::getExamsessionExaminerCount($item->id);

			    // Render cột 'Môn thi' dạng danh sách <ol>
			    $item->exams = $this->renderExamList($examsMap[$item->id] ?? []);
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
	    ToolbarHelper::appendButton('core.manage','download','Tải DS phòng thi','examsessions.exportExamrooms',true);
    }

	/**
	 * Tạo HTML danh sách môn thi (dạng <ol>) cho một ca thi.
	 * Mỗi mục hiển thị "mã môn - tên môn"; với ca sát hạch (không có mã) chỉ hiển thị tên.
	 *
	 * @param   array  $exams  Mảng object {code, name}.
	 *
	 * @return  string  Chuỗi HTML; hiển thị dấu "—" nếu chưa có môn thi nào.
	 * @since   2.1.3
	 */
	private function renderExamList(array $exams): string
	{
		if (empty($exams)) {
			return '<span class="text-muted">—</span>';
		}

		if(count($exams)==1)
		{
			$exam = $exams[0];
			$code  = trim($exam->code ?? '');
			$name  = trim($exam->name ?? '');
			$label = $code !== '' ? ($code . ' - ' . $name) : $name;
			return htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
		}

		$html = '<ol class="mb-0 ps-3">';
		foreach ($exams as $exam) {
			$code  = trim($exam->code ?? '');
			$name  = trim($exam->name ?? '');
			$label = $code !== '' ? ($code . ' - ' . $name) : $name;
			$html .= '<li>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</li>';
		}
		$html .= '</ol>';

		return $html;
	}
}
