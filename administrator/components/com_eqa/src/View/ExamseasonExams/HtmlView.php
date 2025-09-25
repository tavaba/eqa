<?php
namespace Kma\Component\Eqa\Administrator\View\ExamseasonExams;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Model\ExamseasonModel;

class HtmlView extends EqaItemsHtmlView
{
    protected $examseason;
    protected function configureItemFieldsForLayoutDefault():void{
        $fields = new EqaListLayoutItemFields();
        $fields->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $fields->check = EqaListLayoutItemFields::defaultFieldCheck();

        $f = new EqaListLayoutItemFieldOption('name','COM_EQA_EXAM');
		$f->urlFormatString='index.php?option=com_eqa&view=exam&layout=edit&id=%d';
	    $fields->customFieldset1[] = $f;
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
		//Determin the examseason id
	    $examseasonId = Factory::getApplication()->input->getInt('examseason_id');
		if(empty($examseasonId))
			die('Không xác định được kỳ thi');

	    /**
	     * Load the examseason item
	     * @var ExamseasonModel $itemModel
	     **/
	    $mvcFactory = GeneralHelper::getMVCFactory();
		$itemModel = $mvcFactory->createModel('Examseason');
		$this->item = $itemModel->getItem($examseasonId);

	    /**
	     * Prepare the list model by setting state filter for the examseason id
	     */
		$listModel = $this->getModel();   //Get the list model of this view
	    $listModel->setState('filter.examseason_id',$examseasonId);

        //Call parent prepare
        parent::prepareDataForLayoutDefault();

        //Layout data preprocessing
        if(!empty($this->layoutData->items)){
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

		//Setup form hidden field to keep the examseason id
	    $this->layoutData->formHiddenFields = ['examseason_id' => $examseasonId];

    }
    public function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title('Danh sách môn thi của kỳ thi');
        ToolbarHelper::appendGoHome();
        ToolbarHelper::appendButton(null,'arrow-up-2','COM_EQA_EXAMSEASON','examseason.cancel',false);
        ToolbarHelper::appendDelete('exams.delete');
        ToolbarHelper::appendButton('core.create','plus-2','COM_EQA_BUTTON_ADD_MANUALLY','exam.add',false,'btn btn-success');
	    ToolbarHelper::appendButton('core.create','plus-circle','Thêm theo môn học','examseason.addExams',false, 'btn btn-success');
		$msg = 'Hãy kiểm tra lại, đảm bảo đã chọn đúng Kỳ thi! Nếu thêm vào nhầm kỳ thi, sẽ tốn
		rất nhiều thời gian để xóa các môn thi';
	    ToolbarHelper::appendConfirmButton('core.create', $msg,'plus-circle','Thêm theo lớp học phần','examseason.addExamsForClasses',false, 'btn btn-success');
		$msg = 'Hãy đọc kỹ phần hướng dẫn trước khi thực hiện chức năng này: 
		(1)Bạn cần phải tải danh sách môn thi và thí sinh trước khi thực hiện chức năng này 
		(2)Hãy kiểm tra lại một lần nữa, đảm bảo là đã chọn đúng Kỳ thi, nếu chọn nhầm thì xóa rất lâu.
		Do phải rà soát toàn CSDL nên thời gian thực hiện có thể tốn vài phút.';
	    ToolbarHelper::appendConfirmButton('core.create',$msg,'plus-circle','Thêm môn thi lại','examseason.addRetakeExams',false, 'btn btn-danger');
		ToolbarHelper::appendButton(null,'download','Danh sách thi','exams.export',true);
		ToolbarHelper::appendButton('core.edit','loop','Trạng thái', 'exams.recheckStatus',true);
	    ToolbarHelper::appendButton('core.manage', 'download','Bảng điểm SV', 'exams.exportResultForLearners', true);
	    ToolbarHelper::appendButton('core.manage', 'download','Bảng điểm ĐT (Lần 1)', 'exams.exportResultForEms', true);
	    ToolbarHelper::appendButton('core.manage', 'download','Bảng điểm ĐT (Lần 2)', 'exams.exportResultForEms2', true);
    }
}
