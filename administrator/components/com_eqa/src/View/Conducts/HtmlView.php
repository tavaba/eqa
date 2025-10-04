<?php
namespace Kma\Component\Eqa\Administrator\View\Conducts;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\RatingHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;

class HtmlView extends EqaItemsHtmlView
{
    protected $examseason;
    protected function configureItemFieldsForLayoutDefault():void{
        $fields = new EqaListLayoutItemFields();
        $fields->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $fields->check = EqaListLayoutItemFields::defaultFieldCheck();

//	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('academicyear','Năm học',false,false,'text-center');
//	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('term','Học kỳ',false,false,'text-center');
//	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('course','Khóa',false,false,'text-center');
//	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('group','Lớp',false,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('learnerCode','Mã HVSV',false,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('lastname','Họ đệm');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('firstname','Tên');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('excusedAbsenceCount','Vắng LD',false,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('unexcusedAbsenceCount','Vắng KLD',false,false,'text-center');
	    $f = new EqaListLayoutItemFieldOption('retakeCount','HL',false,false,'text-center');
		$f->titleDesc = 'Số lượt học lại';
	    $fields->customFieldset1[] = $f;
	    $f = new EqaListLayoutItemFieldOption('resitCount','TL',false,false,'text-center');
		$f->titleDesc = 'Số lượt thi lại';
	    $fields->customFieldset1[] = $f;
	    $f = new EqaListLayoutItemFieldOption('awardCount','KT',false,false,'text-center');
		$f->titleDesc = 'Số lượt được khen thưởng';
	    $fields->customFieldset1[] = $f;
	    $f = new EqaListLayoutItemFieldOption('disciplinaryCount','KL',false,false,'text-center');
		$f->titleDesc = 'Số lượt bị kỷ luật';
	    $fields->customFieldset1[] = $f;
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('academicScore','Điểm HT',false,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('academicRating', 'XL HT',false,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('conductScore', 'Điểm RL',false,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('conductRating', 'XL RL',false,false,'text-center');

        //Set the option
        $this->itemFields = $fields;
    }

    public function prepareDataForLayoutDefault(): void
    {
        //Call parent prepare
        parent::prepareDataForLayoutDefault();

        //Layout data preprocessing
        if(!empty($this->layoutData->items)){
            foreach ($this->layoutData->items as $item) {
				$item->term = DatetimeHelper::decodeTerm($item->termCode);
				if($item->excusedAbsenceCount==0)
					$item->excusedAbsenceCount='';
	            if($item->unexcusedAbsenceCount==0)
					$item->unexcusedAbsenceCount='';
	            if($item->resitCount==0)
		            $item->resitCount='';
	            if($item->retakeCount==0)
		            $item->retakeCount='';
	            if($item->awardCount==0)
		            $item->awardCount='';
	            if($item->disciplinaryCount==0)
		            $item->disciplinaryCount='';
				if(!is_null($item->conductRating))
	                $item->conductRating = RatingHelper::decodeToAbbr($item->conductRating);
				if(!is_null($item->academicRating))
	                $item->academicRating = RatingHelper::decodeToAbbr($item->academicRating);
            }
        }

    }
    public function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title('Kết quả rèn luyện HVSV');
	    ToolbarHelper::appendGoHome();
		$url = Route::_('index.php?option=com_eqa&view=conducts&layout=default',false);
		ToolbarHelper::appendLink('core.manage',$url,'Làm mới','loop','btn btn-success');
	    ToolbarHelper::appendUpload('conducts.import','Nhập kết quả rèn luyện');
	    ToolbarHelper::appendButton('core.edit','fas fa-calculator','Tính kết quả học tập','conducts.caclculateAcacdemicResults',true);
		$msg = 'Trước khi tải về bảng thống kê, cần chọn "Năm học" và "Học kỳ" thông qua bộ lọc trên trang!';
	    ToolbarHelper::appendConfirmButton(null,$msg,'download','Thống kê theo lớp','conducts.exportClassesReport',false);
	    ToolbarHelper::appendConfirmButton(null,$msg,'download','Thống kê theo khóa','conducts.exportCoursesReport',false);
    }

	protected function prepareDataForLayoutImport(): void
	{
		$this->uploadForm = FormHelper::getBackendForm('com_eqa.conducts.import', 'upload_conducts.xml',[]);
	}

	protected function addToolbarForLayoutImport(): void
	{
		ToolbarHelper::title('Nhập kết quả rèn luyện HVSV');
		ToolbarHelper::save('conducts.import');
		$cancelUrl = Route::_('index.php?option=com_eqa&view=conducts',false);
		ToolbarHelper::appendCancelLink($cancelUrl);
	}

}
