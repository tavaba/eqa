<?php
namespace Kma\Component\Eqa\Administrator\View\Class; //The namespace must end with the VIEW NAME.
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutData;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Model\ClassModel;
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
	protected function prepareDataForLayoutEditPam(): void
	{
		$app = Factory::getApplication();
		$classId = $app->input->getInt('class_id');
		$learnerId = $app->input->getInt('learner_id');
		if(empty($classId) || empty($learnerId))
			die('Invalid request');

		/**
		 * Load learner info
		 * @var ClassModel $model
		 */
		$model = $this->getModel();
		$this->item = $model->getLearnerInfo($classId, $learnerId);
		if(empty($this->item))
			die('Không tìm thấy thông tin HVSV được yêu cầu');

		//Set field values for 'class_id' and 'learner_id'
		$this->form = FormHelper::getBackendForm('com_eqa.class.editpam','editpam.xml',[]);
		$this->form->setValue('class_id',null,$this->item->classId);
		$this->form->setValue('learner_id',null,$this->item->learnerId);
		$this->form->setValue('pam1',null,$this->item->pam1);
		$this->form->setValue('pam2',null,$this->item->pam2);
		$this->form->setValue('pam',null,$this->item->pam);
		$this->form->setValue('allowed',null,$this->item->allowed);
		$this->form->setValue('expired',null,$this->item->expired);
		$this->form->setValue('description',null,$this->item->description);
	}

	protected function addToolbarForLayoutEditPam() : void
	{
		ToolbarHelper::title('Chỉnh sửa điểm quá trình');
		ToolbarHelper::save('class.editPam');
		$url = Route::_('index.php?option=com_eqa&view=classlearners&class_id='.$this->item->classId, false);
		ToolbarHelper::appendCancelLink($url);
	}
}
