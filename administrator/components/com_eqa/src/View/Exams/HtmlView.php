<?php
namespace Kma\Component\Eqa\Administrator\View\Exams;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

class HtmlView extends EqaItemsHtmlView
{
    protected $examseason;
    protected function configureItemFieldsForLayoutDefault():void{
        $fields = new EqaListLayoutItemFields();
        $fields->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $fields->check = EqaListLayoutItemFields::defaultFieldCheck();

        $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('name','COM_EQA_EXAM',false,true);
        $f = new EqaListLayoutItemFieldOption('nexaminee','COM_EQA_EXAMINEE',true,false,'text-center');
        $f->urlFormatString='index.php?option=com_eqa&view=examexaminees&exam_id=%d';
        $fields->customFieldset1[] = $f;

        $f = new EqaListLayoutItemFieldOption('nexamroom', 'COM_EQA_EXAMROOM',true,false,'text-center');
		$f->urlFormatString = 'index.php?option=com_eqa&view=examrooms&filter[exam_id]=%d';
	    $fields->customFieldset1[] = $f;
        $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('testtype', 'COM_EQA_TESTTYPE');
        $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('usetestbank','COM_EQA_EXAM_USE_TESTBANK_OR_NOT',false, false,'text-center');
        $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('questiondeadline','COM_EQA_EXAM_QUESTION_DEADLINE',false,false,'text-center');
        $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('status','COM_EQA_PROGRESS',true);

        //Set the option
        $this->itemFields = $fields;
    }

    public function prepareDataForLayoutDefault(): void
    {
        //Call parent prepare
        parent::prepareDataForLayoutDefault();

        //Load additional data
        $model = $this->getModel();
        $examseasonId = $model->getState('filter.examseason_id');
		if(is_numeric($examseasonId))
            $this->examseason = DatabaseHelper::getExamseasonInfo($examseasonId);    //Maybe null
	    else
			$this->examseason = null;
        if(!empty($this->examseason)) {
            $this->layoutData->formHiddenFields['examseason_id'] = $this->examseason->id;  //Được sử dụng trong trường hợp người dùng chọn 'Thêm môn thi từ lớp học phần'
        }

        //Layout data preprocessing
        if(!empty($this->layoutData->items)){
            $examIds = array_map(function ($item){return $item->id;},$this->layoutData->items);
            foreach ($this->layoutData->items as $item) {
                //1. Testtype code --> Testtype string
                $item->testtype = ExamHelper::getTestType($item->testtype);

                //2. Ngân hàng câu hỏi: boolean --> Yes/No
                if($item->usetestbank)
                    $item->usetestbank = Text::_('JYES');
                else
                    $item->usetestbank = Text::_('JNO');

                //3. Trạng thái môn thi
	            if($item->status >= ExamHelper::EXAM_STATUS_MARK_FULL)
		            $item->optionRowCssClass='table-success';
	            $item->status = ExamHelper::ExamStatus($item->status);

                //4. 'description' = 'description' + many else
            }
        }

    }
    public function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title($this->toolbarOption->title);
        ToolbarHelper::appendGoHome();
        ToolbarHelper::appenddButton(null,'arrow-up-2','COM_EQA_EXAMSEASON','examseason.cancel',false);
        ToolbarHelper::appendDelete('exams.delete');
        ToolbarHelper::appenddButton('core.create','plus-2','COM_EQA_BUTTON_ADD_MANUALLY','exam.add',false,'btn btn-success');
	    ToolbarHelper::appenddButton('core.create','plus-circle','COM_EQA_BUTTON_IMPORT_EXAMS_FROM_CLASSES','examseason.addExams',false, 'btn btn-success');
	    ToolbarHelper::appenddButton('core.create','plus-circle','Thêm môn thi lại','examseason.addRetakeExams',false, 'btn btn-danger');
		ToolbarHelper::appenddButton(null,'download','Danh sách thi','exams.export',true);
		ToolbarHelper::appenddButton('core.edit','loop','Trạng thái', 'exams.recheckStatus',true);
	    ToolbarHelper::appenddButton('core.manage', 'download','Bảng điểm SV', 'exams.exportResultForLearners', true);
	    ToolbarHelper::appenddButton('core.manage', 'download','Bảng điểm ĐT', 'exams.exportResultForEms', true);
    }
}
