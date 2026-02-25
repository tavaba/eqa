<?php
namespace Kma\Component\Eqa\Administrator\View\Cohortlearners; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends ItemsHtmlView {
    protected $cohort;
    protected function configureItemFieldsForLayoutDefault():void{
        $fields = $this->itemFields;      //Just shorten the name
        $fields->sequence = ListLayoutItemFields::defaultFieldSequence();
        $fields->check = ListLayoutItemFields::defaultFieldCheck();
        $fields->customFieldset1[] = new ListLayoutItemFieldOption('code','COM_EQA_LEARNER_CODE',true,false,'text-center');
        $fields->customFieldset1[] = ListLayoutItemFields::defaultFieldLastname();
        $fields->customFieldset1[] = ListLayoutItemFields::defaultFieldFirstname();
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
            'cohort_id'=>$cohortId
        ];

        //Cohort Item
	    $mvcFactory = ComponentHelper::getMVCFactory();
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
