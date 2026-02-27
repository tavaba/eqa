<?php
namespace Kma\Component\Eqa\Administrator\View\Subjects;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use JRoute;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Library\Kma\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends ItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $field = new ListLayoutItemFieldOption('department_code', 'COM_EQA_GENERAL_SUBJECT_DEPARTMENT',true,false);
        $field->cellCssClasses = 'text-center';
        $option->customFieldset1[] = $field;
        $field = new ListLayoutItemFieldOption('code','COM_EQA_GENERAL_SUBJECT_CODE', true, true);
        $field->cellCssClasses = 'text-center';
        $option->customFieldset1[] = $field;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('name', 'COM_EQA_GENERAL_SUBJECT_NAME');
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('degree','COM_EQA_GENERAL_COURSE_DEGREE',true,false,'text-center');
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('credits','Số TC',true,false,'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('finaltesttype','COM_EQA_GENERAL_SUBJECT_TESTTYPE', true, false);
        $field = new ListLayoutItemFieldOption('testbankyear', 'COM_EQA_GENERAL_SUBJECT_TESTBANK', true, false);
        $field->cellCssClasses = 'text-center';
        $option->customFieldset1[] = $field;

        $option->published = ListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        if(!empty($this->layoutData->items)) {
            foreach ($this->layoutData->items as $item) {
                $item->finaltesttype = ExamHelper::getTestType($item->finaltesttype);
                $item->degree = CourseHelper::Degree($item->degree);
            }
        }
    }
	protected function addToolbarForLayoutDefault(): void
	{
		parent::addToolbarForLayoutDefault();
		ToolbarHelper::appendImportLink(JRoute::_('index.php?option=com_eqa&view=subjects&layout=import',false));
	}

	protected function prepareDataForLayoutImport(): void
	{
		$this->form = FormHelper::getBackendForm('com_eqa.subjects.import', 'upload_excelfile.xml', []);
	}
	protected function addToolbarForLayoutImport(): void
	{
		ToolbarHelper::title('Nhập thông tin môn học');
		ToolbarHelper::appendUpload('subjects.import');
		ToolbarHelper::cancel('subjects.cancel');
	}
}
