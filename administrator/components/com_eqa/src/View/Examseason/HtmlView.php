<?php
namespace Kma\Component\Eqa\Administrator\View\Examseason; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaItemHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutData;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\StringHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemHtmlView {
    protected $examseason;
    protected EqaListLayoutData $listLayoutData;
    protected EqaListLayoutItemFields $listLayoutItemFields;
    protected function prepareDataForLayoutAddexams(): void
    {
        $examseasonId = Factory::getApplication()->input->getInt('examseason_id');

        //Toolbar
        $this->toolbarOption->clearAllTask();
        $this->toolbarOption->title = Text::_('COM_EQA_EXAMSEASON_ADD_EXAMS');
        $this->toolbarOption->taskPrefixItem = $this->getName();
        $this->toolbarOption->taskPrefixItems = StringHelper::convertSingleToPlural($this->getName());

        //Get the Item
        $itemModel = $this->getModel('examseason');
        if(!empty($examseasonId))
            $this->examseason = $itemModel->getItem($examseasonId);
        else{
            $this->examseason = $itemModel->getDefaultItem();
            $examseasonId = $this->examseason->id;
        }

        //Set up the DEFAULT model, namely the list model 'subjects'
        //This model allow the layout to utilize ViewHelper to display a list of subjects
        //that the user can select to 'import' exams
        $existingSubjectIds = $itemModel->getSubjectIdsByExamseasonId($examseasonId);
        $termSubjectIds = $itemModel->getSubjectIdsByTerm($this->examseason->academicyear_id, $this->examseason->term);
        $limitSubjectIds = array_diff($termSubjectIds, $existingSubjectIds);
        $factory = GeneralHelper::getMVCFactory();
        $listModel = $factory->createModel('subjects');
        $listModel->setState('filter.limit_subject_ids',$limitSubjectIds);
        $this->setModel($listModel, true);

        //Prepare list layout data
        $this->listLayoutData = new EqaListLayoutData();
        $this->loadCommonListLayoutData($this->listLayoutData, $listModel);
        $this->listLayoutData->formActionParams = [
            'view' => 'examseason',
            'layout' => 'addexams',
            'examseason_id' => $examseasonId
        ];
        $this->listLayoutData->formHiddenFields=[
            'phase'=>'getdata'
        ];


        //Cleanup list model's state after successful retrieving data
        $listModel->setState('filter.limit_subject_ids',null);


        //Preprocess the layout data
        if(!empty($this->listLayoutData->items)) {
            foreach ($this->listLayoutData->items as $item) {
                $item->finaltesttype = ExamHelper::getTestType($item->finaltesttype);
                $item->degree = CourseHelper::Degree($item->degree);
            }
        }


        //Prepare list layout item fields
        $this->listLayoutItemFields = new EqaListLayoutItemFields();
        $itemFields = $this->listLayoutItemFields; //Just shorten the name

        $itemFields->sequence = EqaListLayoutItemFields::defaultFieldSequence();
        $itemFields->check = EqaListLayoutItemFields::defaultFieldCheck();

        $itemFields->customFieldset1 = array();
        $field = new EqaListLayoutItemFieldOption('department_code', 'COM_EQA_GENERAL_SUBJECT_DEPARTMENT',true,false);
        $field->cssClass = 'text-center';
        $itemFields->customFieldset1[] = $field;
        $field = new EqaListLayoutItemFieldOption('code','COM_EQA_GENERAL_SUBJECT_CODE', true, false);
        $field->cssClass = 'text-center';
        $itemFields->customFieldset1[] = $field;
        $itemFields->customFieldset1[] = new EqaListLayoutItemFieldOption('name', 'COM_EQA_GENERAL_SUBJECT_NAME');
        $itemFields->customFieldset1[] = new EqaListLayoutItemFieldOption('degree','COM_EQA_GENERAL_COURSE_DEGREE',true,false,'text-center');
        $itemFields->customFieldset1[] = new EqaListLayoutItemFieldOption('finaltesttype','COM_EQA_GENERAL_SUBJECT_TESTTYPE', true, false);
        $field = new EqaListLayoutItemFieldOption('testbankyear', 'COM_EQA_GENERAL_SUBJECT_TESTBANK', true, false);
        $field->cssClass = 'text-center';
        $itemFields->customFieldset1[] = $field;
        $itemFields->published = EqaListLayoutItemFields::defaultFieldPublished();
    }
    protected function addToolbarForLayoutAddexams(): void
    {
        $option = $this->toolbarOption;
        ToolbarHelper::title($option->title);
        ToolbarHelper::save($option->taskPrefixItem.'.addExams');
        ToolbarHelper::cancel($option->taskPrefixItem.'.cancel');
    }
}
