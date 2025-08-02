<?php
namespace Kma\Component\Eqa\Administrator\View\Paperexams;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

class HtmlView extends EqaItemsHtmlView
{
    protected $examseason;
    protected function configureItemFieldsForLayoutDefault():void{
        $fields = new EqaListLayoutItemFields();
        $fields->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $fields->check = EqaListLayoutItemFields::defaultFieldCheck();

        $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('name','COM_EQA_EXAM',true);
	    $f = new EqaListLayoutItemFieldOption('nexamroom', 'COM_EQA_EXAMROOM',true,false,'text-center');
		$f->urlFormatString = 'index.php?option=com_eqa&view=examrooms&filter[exam_id]=%d';
	    $fields->customFieldset1[] = $f;

		$f = new EqaListLayoutItemFieldOption('nexaminee','COM_EQA_EXAMINEE',true,false,'text-center');
		$f->urlFormatString = 'index.php?option=com_eqa&view=examexaminees&exam_id=%d';
	    $fields->customFieldset1[] = $f;

	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('npaper', 'COM_EQA_NPAPER',true,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('nnopaper', 'COM_EQA_NNOPAPER',true,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('nsheet', 'COM_EQA_NSHEET',false,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('npackage', 'Số túi',true,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('status', 'Tiến độ',true);

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
        $this->examseason = DatabaseHelper::getExamseasonInfo($examseasonId);    //Maybe null
        if(!empty($this->examseason)) {
            $this->layoutData->formHiddenFields['examseason_id'] = $this->examseason->id;  //Được sử dụng trong trường hợp người dùng chọn 'Thêm môn thi từ lớp học phần'
        }

        //Layout data preprocessing
        if(!empty($this->layoutData->items)){
            $examIds = array_map(function ($item){return $item->id;},$this->layoutData->items);
            foreach ($this->layoutData->items as $item) {

                //1. Tính toán số lượng phòng thi, số lượng thí sinh, số lượng bài thi
	            $item->nsheet = DatabaseHelper::getExamSheetCount($item->id);
				if($item->nexaminee>0 && $item->npaper + $item->nnopaper == $item->nexaminee)
					$item->optionRowCssClass='table-success';

				//2. Status
	            $item->status = ExamHelper::ExamStatus($item->status);

                //4. 'description' = 'description' + many else
            }
        }

    }
    public function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title($this->toolbarOption->title);
	    ToolbarHelper::appendGoHome();
	    ToolbarHelper::appenddButton('core.manage','users','Phân công chấm thi', 'paperexam.editExaminers',true);
	    ToolbarHelper::appenddButton('core.manage','download','Tải sơ đồ phách', 'paperexam.exportMaskMap',true);
	    ToolbarHelper::appenddButton('core.manage','download','Tải phiếu chấm thi theo số phách', 'paperexam.exportMarkingSheet',true);
    }

	public function prepareDataForLayoutUploadmarkbymask(): void
	{
		$this->form = FormHelper::getBackendForm('com_eqa.upload_excelfile','upload_excelfile.xml',[]);
	}
	public function addToolbarForLayoutUploadmarkbymask(): void
	{
		ToolbarHelper::title('Nhập điểm chấm thi viết theo số phách');
		ToolbarHelper::appenddButton('core.edit','save','JTOOLBAR_SAVE','paperexams.uploadMarkByMask',false,null,true);
		$cancelUrl = \JRoute::_('index.php?option=com_eqa',false);
		ToolbarHelper::appendCancelLink($cancelUrl);
	}
}
