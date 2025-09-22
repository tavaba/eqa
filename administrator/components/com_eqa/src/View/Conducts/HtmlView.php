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
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('resitCount','Thi lại',false,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('retakeCount','Học lại',false,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('awardCount','KT',false,false,'text-center');
	    $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('disciplinaryCount','KL',false,false,'text-center');
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
	    ToolbarHelper::appendButton('core.edit','fas fa-calculator','Tính kết quả học tập','conducts.caclculateAcacdemicResults',true);
	    ToolbarHelper::appendUpload('conducts.import','Nhập kết quả rèn luyện');
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
