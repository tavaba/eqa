<?php
namespace Kma\Component\Eqa\Administrator\View\Conducts;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Helper\TermHelper;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Library\Kma\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\RatingHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends ItemsHtmlView
{
    protected $examseason;
    protected function configureItemFieldsForLayoutDefault():void{
        $fields = new ListLayoutItemFields();
        $fields->sequence = ListLayoutItemFields::defaultFieldSequence();
        $fields->check = ListLayoutItemFields::defaultFieldCheck();

//	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('academicyear','Năm học',false,false,'text-center');
//	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('term','Học kỳ',false,false,'text-center');
//	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('course','Khóa',false,false,'text-center');
//	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('group','Lớp',false,false,'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('learnerCode','Mã HVSV',true,false,'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('lastname','Họ đệm');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('firstname','Tên', true);
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('excusedAbsenceCount','Vắng LD',true,false,'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('unexcusedAbsenceCount','Vắng KLD',true,false,'text-center');
	    $f = new ListLayoutItemFieldOption('retakeCount','HL',true,false,'text-center');
		$f->titleDesc = 'Số lượt học lại';
	    $fields->customFieldset1[] = $f;
	    $f = new ListLayoutItemFieldOption('resitCount','TL',true,false,'text-center');
		$f->titleDesc = 'Số lượt thi lại';
	    $fields->customFieldset1[] = $f;
	    $f = new ListLayoutItemFieldOption('awardCount','KT',true,false,'text-center');
		$f->titleDesc = 'Số lượt được khen thưởng';
	    $fields->customFieldset1[] = $f;
	    $f = new ListLayoutItemFieldOption('disciplinaryCount','KL',true,false,'text-center');
	    $f->titleDesc = 'Số lượt bị kỷ luật';
	    $fields->customFieldset1[] = $f;
	    $f = new ListLayoutItemFieldOption('totalCredits','Số TC',true,false,'text-center');
	    $f->titleDesc = 'Tổng số tín chỉ';
	    $fields->customFieldset1[] = $f;
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('academicScore','Điểm HT',true,false,'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('academicRating', 'XL HT',true,false,'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('conductScore', 'Điểm RL',true,false,'text-center');
	    $fields->customFieldset1[] = new ListLayoutItemFieldOption('conductRating', 'XL RL',true,false,'text-center');

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
				$item->term = TermHelper::decodeTerm($item->termCode);
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
		ToolbarHelper::appendLink('core.manage',$url,'Làm mới trang','loop','btn btn-success');
	    ToolbarHelper::appendUpload('conducts.import','Nhập kết quả rèn luyện');
	    ToolbarHelper::appendButton('core.edit','fas fa-calculator','Tính kết quả học tập','conducts.caclculateAcacdemicResults',true);
	    $msg = 'Trước khi thực hiện tính trung bình năm, cần chọn "Năm học" và "Khóa đào tạo" thông qua bộ lọc trên trang!';
	    ToolbarHelper::appendConfirmButton('core.edit',$msg,'fas fa-calculator','Tính trung bình năm','conducts.caclculateAcacdemicYearResults',false);
		ToolbarHelper::deleteList('','conducts.delete');
		$msg = 'Trước khi tải về bảng thống kê, cần chọn "Năm học" và "Học kỳ" thông qua bộ lọc trên trang!';
	    ToolbarHelper::appendConfirmButton(null,$msg,'download','Thống kê theo lớp','conducts.exportClassesReport',false);
	    ToolbarHelper::appendConfirmButton(null,$msg,'download','Thống kê theo khóa','conducts.exportCoursesReport',false);
    }

	protected function prepareDataForLayoutImport(): void
	{
		$this->form = FormHelper::getBackendForm('com_eqa.conducts.import', 'upload_conducts.xml',[]);
	}

	protected function addToolbarForLayoutImport(): void
	{
		ToolbarHelper::title('Nhập kết quả rèn luyện HVSV');
		ToolbarHelper::save('conducts.import');
		$cancelUrl = Route::_('index.php?option=com_eqa&view=conducts',false);
		ToolbarHelper::appendCancelLink($cancelUrl);
	}

}
