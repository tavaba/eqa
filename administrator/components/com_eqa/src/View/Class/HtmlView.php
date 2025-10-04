<?php
namespace Kma\Component\Eqa\Administrator\View\Class; //The namespace must end with the VIEW NAME.
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutData;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use SimpleXMLElement;

defined('_JEXEC') or die();

class HtmlView extends EqaItemHtmlView {
    protected object $class;
    protected EqaListLayoutData $listLayoutData;
    protected EqaListLayoutItemFields $listLayoutItemFields;

	protected function addToolbarForLayoutEdit(): void
	{
		ToolbarHelper::title($this->toolbarOption->title);
		ToolbarHelper::appendButton(['core.create','eqa.create.class','core.edit','eqa.edit.class','core.edit.own'],'save','JTOOLBAR_SAVE','class.save',false,'btn btn-success');
		ToolbarHelper::cancel('class.cancel');
	}

	protected function prepareDataForLayoutImportlearners(): void
	{
		$app = Factory::getApplication();
		$classId = $app->input->getInt('class_id',0);

		//Load the class
		$model = $this->getModel();
		$this->class = $model->getItem($classId);

		//Load form
		$this->form = FormHelper::getBackendForm('com_eqa.classlearners_import','upload_excelfile.xml', []);
		FormHelper::addField($this->form,'class_id','hidden', $classId, null, 'upload');
	}
	protected function addToolbarForLayoutImportlearners(): void
	{
		ToolbarHelper::title('Nhập danh sách HVSV vào lớp học phần');
		ToolbarHelper::appendUpload('class.importLearners');
		ToolbarHelper::cancel('classlearners.cancel');
	}
	protected function prepareDataForLayoutImportpams(): void
	{
		$app = Factory::getApplication();
		$classId = $app->input->getInt('class_id',0);

		//Load the class
		$model = $this->getModel();
		$this->class = $model->getItem($classId);

		//Load form
		$this->form = FormHelper::getBackendForm('com_eqa.classlearners_import','upload_excelfile.xml', []);
		FormHelper::addField($this->form,'class_id','hidden', $classId, null, 'upload');
	}
	protected function addToolbarForLayoutImportpams(): void
	{
		ToolbarHelper::title('Nhập ĐQT cho lớp học phần');
		ToolbarHelper::appendUpload('class.importPams');
		ToolbarHelper::cancel('classlearners.cancel');
	}
    protected function prepareDataForLayoutAddlearners(): void
    {
        //Toolbar
        $this->toolbarOption->title = Text::_('COM_EQA_MANAGER_CLASS_ADDLEARNERS_TITLE');

        //Data
        $model = $this->getModel();
        $classId = Factory::getApplication()->input->getInt('class_id');
        $this->form = $model->getCustomForm('com_eqa.addlearners','addlearners',[]);
        $this->class = $model->getItem($classId);
    }

    protected function addToolbarForLayoutAddlearners() : void
    {
        ToolbarHelper::title($this->toolbarOption->title);
        ToolbarHelper::save('class.addLearners');
		ToolbarHelper::cancel('classlearners.cancel');
    }
}
