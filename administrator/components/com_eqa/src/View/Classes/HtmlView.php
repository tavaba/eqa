<?php
namespace Kma\Component\Eqa\Administrator\View\Classes;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\EmployeeHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new EqaListLayoutItemFields();

	    $option->sequence = EqaListLayoutItemFields::defaultFieldSequence();
	    $option->id = EqaListLayoutItemFields::defaultFieldId();
	    $option->check = EqaListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('coursegroup','COM_EQA_COURSEGROUP',true,false,'text-center');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('code', 'COM_EQA_CLASS_CODE', true,true);
        $field = new EqaListLayoutItemFieldOption('name','COM_EQA_CLASS_NAME',true,false);
        $field->altField = 'description';
        $option->customFieldset1[] = $field;
        //Trường 'lecturer' thực tế không tồn tại
        // ==> Ở phần layout cần căn cứ vào lecturer_id để tính toán Họ và tên của lecturer.
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('lecturer','COM_EQA_LECTURER');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('academicyear','COM_EQA_ACADEMICYEAR', true,false,'text-center');
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('term','COM_EQA_TERM', true,false,'text-center');
        $field = new EqaListLayoutItemFieldOption('size','COM_EQA_CLASS_SIZE', true,false,'text-center');
        $field->urlFormatString = 'index.php?option=com_eqa&view=classlearners&class_id=%d';
        $option->customFieldset1[] = $field;
        $option->customFieldset1[] = new EqaListLayoutItemFieldOption('npam','COM_EQA_PAM_ABBR',true,false,'text-center');

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        //Call parent method
        parent::prepareDataForLayoutDefault();

        //Prepare toolbar
        $this->toolbarOption->clearAllTask();
        $this->toolbarOption->taskGoHome = true;
        $this->toolbarOption->taskAddNew = true;
        $this->toolbarOption->taskDeleteList = true;
        $this->toolbarOption->taskUpload = true;

        //Prepare data
        if(!empty($this->layoutData->items)){
            foreach ($this->layoutData->items as $item){
                $item->lecturer = EmployeeHelper::getFullName($item->lecturer_id);
            }
        }

    }
    protected function addToolbarForLayoutDefault(): void
    {
	    ToolbarHelper::title('Quản lý lớp học phần');
		ToolbarHelper::appendGoHome();
	    ToolbarHelper::appendButton(['core.create','eqa.create.class'],'plus','JTOOLBAR_NEW','class.add',false,'btn btn-success');
	    ToolbarHelper::appendButton('core.delete','delete','JTOOLBAR_DELETE','class.delete',true,'btn btn-danger');
	    ToolbarHelper::appendButton(['core.create','eqa.create.class'],'upload','JTOOLBAR_UPLOAD','classes.upload');
		ToolbarHelper::appendButton(['core.create','eqa.create.class'], 'plus-circle','Thêm đồng loạt','classes.addForGroupOrCohort',false,'btn btn-success');
        $url = \JRoute::_('index.php?option=com_eqa&view=classes&layout=uploadpam',false);
		ToolbarHelper::appendButton('core.manage','download','Điểm QT','classes.downloadPam',true);
        ToolbarHelper::appendLink(['core.edit','core.edit.own','eqa.edit.class'], $url,'COM_EQA_IMPORT_PAM', 'bars');
    }
	protected function addToolbarForLayoutUpload(): void
	{
		ToolbarHelper::title('Nhập lớp học phần');
		ToolbarHelper::appendButton(['core.create','eqa.create.class'],'save','JTOOLBAR_SAVE','classes.import',false,null,true);
		ToolbarHelper::cancel('class.cancel');
	}

	protected function prepareDataForLayoutAddforgrouporcohort(): void
	{
		$this->form = FormHelper::getBackendForm('com_eqa.addclassesforgrouporcohort','addClassesForGroupOrCohort.xml', array());
	}
	protected function addToolbarForLayoutAddforgrouporcohort(): void
	{
		ToolbarHelper::title('Tạo lớp học phần theo lớp hành chính hoặc nhóm HVSV');
		ToolbarHelper::save('classes.addForGroupOrCohort');
		ToolbarHelper::cancel('class.cancel');
	}

	protected function prepareDataForLayoutUploadpam(): void
	{
		$this->form = FormHelper::getBackendForm('com_eqa.uploadpam','uploadpam.xml', array());
	}
	protected function addToolbarForLayoutUploadpam(): void
    {
		ToolbarHelper::title('Tải bảng điểm quá trình');
        ToolbarHelper::appendButton('core.edit','save','COM_EQA_IMPORT_PAM','classes.importPam',false,null,true);
        ToolbarHelper::cancel('class.cancel');
    }
}
