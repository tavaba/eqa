<?php
namespace Kma\Component\Eqa\Site\View\Learnerexams;   //Must end with the View Name
defined('_JEXEC') or die();

use DateTime;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Toolbar\Toolbar;
use Kma\Component\Eqa\Administrator\Base\EqaItemAction;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Site\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class HtmlView extends EqaItemsHtmlView{
	protected $learner;
	protected function configureItemFieldsForLayoutDefault():void{
		$option = new EqaListLayoutItemFields();

		//Các trường thông tin
		$option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
		$option->check = EqaListLayoutItemFields::defaultFieldCheck();
		$option->customFieldset1 = array();
//		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('examseason', 'Kỳ thi', true);
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('academicyear', 'Năm học', true, false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('term', 'Học kỳ', true, false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('exam', 'Môn thi', true);
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('attempt', 'Lần', false,false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('pam1', 'TP1', false,false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('pam2', 'TP2', false,false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('pam', 'ĐQT', false,false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('mark_final', 'Điểm thi', false,false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('module_mark', 'Điểm HP', false,false, 'text-center');
		$option->customFieldset1[] = new EqaListLayoutItemFieldOption('module_grade', 'Điểm chữ', false,false, 'text-center');

		$this->itemFields = $option;
	}
	protected function prepareDataForLayoutDefault(): void
	{
		//Xác định learner và đưa thông tin (id) vào model
		$username      = GeneralHelper::getCurrentUsername();
		if(!$username)
			return;
		$this->learner = DatabaseHelper::getLearnerInfo($username);
		if($this->learner){
			$model = $this->getModel();
			$model->setState('learner_id',$this->learner->id);
		}
		else
			return;

		//Call parent's method
		parent::prepareDataForLayoutDefault();

		//Preprocessing
		if(!empty($this->layoutData)){
			foreach ($this->layoutData->items as &$item)
			{
				ExamHelper::normalizeMarks($item);
			}
		}
	}
	protected function setTitleAndToolbarForLayoutDefault():void
	{
		if(!isset($this->learner))
			return;

		//Title
		ToolbarHelper::title('Danh sách môn thi của thí sinh');

		// Add buttons to the toolbar
		ToolbarHelper::appenddButton(null,'dashboard','Yêu cầu phúc khảo','learnerexams.RequestRegrading',true);
		ToolbarHelper::appenddButton(null,'warning','Yêu cầu đính chính điểm','learnerexams.ShowCorrectionRequestForm',true, 'btn btn-warning');

		// Render the toolbar
		$toolbar = Toolbar::getInstance();
		echo $toolbar->render();
	}

	public function display($tpl = null)
    {

	    //Gọi phương thức lớp cha
		parent::display();
    }
}