<?php
namespace Kma\Component\Eqa\Administrator\View\Cohortlearners; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView {
    protected $cohort;
    protected function configureItemFieldsForLayoutDefault():void{
        $fields = $this->itemFields;      //Just shorten the name
        $fields->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $fields->check = EqaListLayoutItemFields::defaultFieldCheck();
        $fields->customFieldset1[] = new EqaListLayoutItemFieldOption('code','COM_EQA_LEARNER_CODE',true,false,'text-center');
        $fields->customFieldset1[] = EqaListLayoutItemFields::defaultFieldLastname();
        $fields->customFieldset1[] = EqaListLayoutItemFields::defaultFieldFirstname();
    }
    protected function prepareDataForLayoutDefault(): void
    {
        //Prepare the model before calling parent
        $cohortId = Factory::getApplication()->input->get('cohort_id');
		if(empty($cohortId))
			die();
        $model = $this->getModel();
        $model->setState('filter.cohort_id',$cohortId);
        parent::prepareDataForLayoutDefault();

        //Tham số dưới đây sẽ khiến DisplayController luôn redirect tới view và layout mong muốn
        //giúp cố định 'cohort_id'
        $this->layoutData->formActionParams = [
            'view'=>'cohortlearners',
            'layout'=>'default',
            'cohort_id'=>$cohortId
        ];

        //Cohort Item
	    $mvcFactory = GeneralHelper::getMVCFactory();
		$cohortModel = $mvcFactory->createModel('Cohort', 'Administrator');
	    $this->cohort = $cohortModel->getItem($cohortId);

    }
    protected function addToolbarForLayoutDefault(): void
    {
		ToolbarHelper::title('Danh sách HVSV của nhóm');
        ToolbarHelper::appendGoHome();
		ToolbarHelper::appendGoBack('cohort.cancel', 'Nhóm');
		ToolbarHelper::appendDelete('cohort.removeLearners');
	    ToolbarHelper::addNew('cohort.addLearners', 'Thêm HVSV');
    }
}
