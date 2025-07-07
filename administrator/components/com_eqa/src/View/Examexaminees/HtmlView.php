<?php
namespace Kma\Component\Eqa\Administrator\View\Examexaminees; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView {
    protected $exam;
    protected function configureItemFieldsForLayoutDefault():void{
        $this->itemFields = new EqaListLayoutItemFields();
        $fields = $this->itemFields;      //Just shorten the name
        $fields->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $fields->check = EqaListLayoutItemFields::defaultFieldCheck();

	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('code','COM_EQA_EXAMINEE_CODE_ABBR', true, false, 'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('learner_code','COM_EQA_LEARNER_CODE', true, false, 'text-center');
        $fields->customFieldset1[] = EqaListLayoutItemFields::defaultFieldLastname();
        $fields->customFieldset1[] = EqaListLayoutItemFields::defaultFieldFirstname();
        $f = new EqaListLayoutItemFieldOption('attempt', 'COM_EQA_EXAM_ATTEMPT_ABBR', true, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_EXAM_ATTEMPT');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('pam1', 'COM_EQA_PAM1_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_PAM1');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('pam2', 'COM_EQA_PAM2_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_PAM2');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('pam', 'COM_EQA_PAM_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_PAM');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('allowed', 'COM_EQA_ALLOWED_TO_TAKE_EXAM_ABBR', true, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_ALLOWED_TO_TAKE_EXAM');
        $fields->customFieldset1[] = $f;
		$fields->customFieldset1[] = new EqaListLayoutItemFieldOption('debtor','COM_EQA_DEBT',true,false,'text-center');
		$f =new EqaListLayoutItemFieldOption('stimulation', 'KK', true, false, 'text-center');
		$f->titleDesc = 'Chế độ khuyến khích (nếu có)';
	    $fields->customFieldset1[] = $f;
		$fields->customFieldset1[] = new EqaListLayoutItemFieldOption('anomaly', 'Bất thường');
	    $f = new EqaListLayoutItemFieldOption('mark_final', 'COM_EQA_MARK_FINALEXAM_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_MARK_FINALEXAM');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('module_mark', 'COM_EQA_MODULE_MARK_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_MODULE_MARK');
        $fields->customFieldset1[] = $f;
        $f = new EqaListLayoutItemFieldOption('module_grade', 'COM_EQA_MODULE_GRADE_ABBR', false, false, 'text-center');
        $f->titleDesc=Text::_('COM_EQA_MODULE_GRADE');
        $fields->customFieldset1[] = $f;
		$fields->customFieldset1[] = new EqaListLayoutItemFieldOption('conclusion', 'Kết luận');
    }
    protected function prepareDataForLayoutDefault(): void
    {
        //Prepare the model before calling parent
        $examId = Factory::getApplication()->input->get('exam_id');
        $model = $this->getModel();
        $model->setState('filter.exam_id',$examId);
        parent::prepareDataForLayoutDefault();

        //Tham số dưới đây sẽ khiến DisplayController luôn redirect tới view và layout mong muốn
        //giúp cố định 'exam_id'
        $this->layoutData->formActionParams = [
            'view'=>'examexaminees',
            'layout'=>'default',
            'exam_id'=>$examId
        ];

        //Class Item
        $this->exam = DatabaseHelper::getExamInfo($examId);

        //Layout data preprocessing
        if(!empty($this->layoutData->items))
        {
            foreach ($this->layoutData->items as $item)
            {
                if($item->allowed)
                    $item->allowed = Text::_('JYES');
                else {
                    $item->allowed = Text::_('JNO');
                    $item->optionRowCssClass='table-danger';
                }

	            if($item->debtor){
		            $item->debtor = Text::_('JYES');
		            $item->optionRowCssClass='table-danger';
	            }
	            else {
		            $item->debtor = Text::_('JNO');
	            }

				if($item->stimulation !== null)
					$item->stimulation = StimulationHelper::getStimulationType($item->stimulation);

				$item->anomaly = ExamHelper::getAnomaly($item->anomaly);

				if(!is_null($item->conclusion))
					$item->conclusion = ExamHelper::getConclusion($item->conclusion);

				if($item->pam1<0)
					$item->pam1 = ExamHelper::specialMarkToText($item->pam1);
	            if($item->pam2<0)
		            $item->pam2 = ExamHelper::specialMarkToText($item->pam2);
	            if($item->pam<0)
		            $item->pam = ExamHelper::specialMarkToText($item->pam);
            }
        }

    }
    protected function addToolbarForLayoutDefault(): void
    {
        $option = $this->toolbarOption;
        ToolbarHelper::title($option->title);
        $url = JRoute::_('index.php?option=com_eqa&view=exams',false);
        ToolbarHelper::appendLink(null,$url,'COM_EQA_EXAM', 'arrow-up-2');
	    ToolbarHelper::appenddButton('core.create','plus-2','COM_EQA_BUTTON_ADD_EXAMINEES','exam.addExaminees',false,'btn btn-success');
	    ToolbarHelper::appenddButton('core.create','plus-2','Thêm HVSV chưa đạt','exam.addFailedExaminees',false,'btn btn-success');
        ToolbarHelper::appendDelete('exam.removeExaminees');
	    ToolbarHelper::appenddButton('core.edit', 'loop','Khuyến khích','exam.stimulate',false, 'btn btn-success');
		$msg = 'Điều này có thể làm xáo trộn trạng thái thí sinh môn thi. Bạn có chắc muốn thực hiện?';
		ToolbarHelper::appendConfirmButton('core.edit',$msg, 'loop','Nợ phí','exam.updateDebt',false,'btn btn-success');
	    ToolbarHelper::appenddButton('core.edit','pause','Hoãn thi','exam.delay',true, 'btn btn-danger');
	    ToolbarHelper::appenddButton('core.edit','play','Hủy hoãn thi','exam.undoDelay',true, 'btn btn-success');
	    ToolbarHelper::appenddButton('core.create','calendar','Chia phòng ngẫu nhiên','exam.distribute',false);
		$urlDistribute2 = JRoute::_('index.php?option=com_eqa&view=exam&layout=distribute2&exam_id='.$this->exam->id, false);
	    ToolbarHelper::appendLink('core.create', $urlDistribute2,'Chia phòng theo lớp', 'calendar');
	    ToolbarHelper::appenddButton(null,'download','Xuất môn thi','exam.export');
	    ToolbarHelper::appenddButton(null,'download','Xuất ca iTest','exam.exportitest');
    }
}
