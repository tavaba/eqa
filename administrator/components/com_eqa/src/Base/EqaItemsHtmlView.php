<?php
namespace Kma\Component\Eqa\Administrator\Base;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetManager;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\StringHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

abstract class EqaItemsHtmlView extends BaseHtmlView{
	protected WebAssetManager $wam;
    protected EqaListLayoutData $layoutData;
    protected EqaListLayoutItemFields $itemFields;
    protected EqaToolbarOption $toolbarOption;
    public function __construct($config = [])
    {
        parent::__construct($config);
	    $this->wam = GeneralHelper::getDocument()->getWebAssetManager();
	    $this->layoutData = new EqaListLayoutData();
        $this->itemFields = new EqaListLayoutItemFields();
        $this->toolbarOption = new EqaToolbarOption();
    }

    /**
     * Phương thức (ảo) này chỉ định các trường và tính chất của chúng trong list layout
     * @return void
     * @since 1.0
     */
    abstract protected function configureItemFieldsForLayoutDefault():void;

    /*
     * Nếu lớp con của lớp này ghi đè (overrite) một trong các phương thức 'prepareData...'
     * thì nó cần gọi phương thức của lớp cha (nếu cần gọi) trước khi thực hiện thao tác
     * của riêng mình.
     */
    protected function prepareDataForLayoutDefault() : void
    {
        //Toolbar
        $this->toolbarOption->setDefaultListTasks();
        $upperControllerNameItems = strtoupper($this->toolbarOption->taskPrefixItems);
        $this->toolbarOption->title = Text::_('COM_EQA_MANAGER_'.$upperControllerNameItems.'_TITLE');


        //Layout data
        $this->layoutData->formActionParams = ['view'=>$this->getName()];
        $this->layoutData->taskPrefixItem = StringHelper::convertPluralToSingle($this->getName());
        $this->layoutData->taskPrefixItems = $this->getName();

		$model = $this->getModel();
        $this->layoutData->items = $model->getItems();
        $pagination = $model->getPagination();
        if(!empty($pagination)) {
            $this->layoutData->pagination = $pagination;
            $this->layoutData->showPaginationLimitBox = true;
        }

        $state = $model->getState();
        $this->layoutData->listOrderingField = $this->escape($state->get('list.ordering'));
        $this->layoutData->listOrderingDirection = $this->escape($state->get('list.direction'));
        $this->layoutData->sortByOrder = $this->layoutData->listOrderingField == 'ordering';

        $filterForm = $model->getFilterForm();
        if(!empty($filterForm)) {
            $this->layoutData->filterForm = $filterForm;
            $this->layoutData->showPaginationLimitBox = false;
        }

        $activeFilters = $model->getActiveFilters();
        if(!empty($activeFilters))
            $this->layoutData->activeFilters = $activeFilters;
    }
    protected function prepareDataForLayoutUpload() : void
    {
        //Toolbar
        $this->toolbarOption->setUploadTasks();
        $upperControllerNameItems = strtoupper($this->toolbarOption->taskPrefixItems);
        $this->toolbarOption->title = Text::_('COM_EQA_MANAGER_'.$upperControllerNameItems.'_UPLOAD_TITLE');

        //Data
        $model = $this->getModel();
        $this->uploadForm = $model->getUploadForm();
    }

    /*
     * Các lớp con nếu ghi đè (overrite) một trong các phương thức 'addToolbar...' hoặc 'get....'
     * thì cần ghi đè toàn bộ, không gọi đến phương thức của lớp cha.
     */
    protected function addToolbarForLayoutDefault() : void
    {
        if(!isset($this->toolbarOption))
            return;

        if(!$this->toolbarOption->showToolbar)
            return;

        //Title
        ToolbarHelper::title($this->toolbarOption->title);

        //Buttons
        $option = $this->toolbarOption;
        $prefixSingle = $option->taskPrefixItem;
        $prefixPlural = $option->taskPrefixItems;
        if($this->toolbarOption->taskGoHome){
            ToolbarHelper::link(\JRoute::_('index.php?option=com_eqa'),'COM_EQA_BUTTON_GO_HOME','home');
            ToolbarHelper::divider();
        }
        if($option->taskAddNew && $option->canDo['core.create'])
            ToolbarHelper::addNew($prefixSingle.'.add');
        if($option->taskEditList && $option->canDo['core.edit'])
            ToolbarHelper::editList($prefixSingle.'.edit');
        if($option->taskDeleteList && $option->canDo['core.delete'])
            ToolbarHelper::deleteList(Text::_('COM_EQA_MSG_CONFIRM_DELETE'),$prefixPlural.'.delete');
        if($option->taskPublish && ($option->canDo['core.edit'] || $option->canDo['core.edit.state']))
            ToolbarHelper::publish($prefixPlural.'.publish', 'JTOOLBAR_PUBLISH', true);
        if($option->taskPublish && ($option->canDo['core.edit'] || $option->canDo['core.edit.state']))
            ToolbarHelper::unpublish($prefixPlural.'.unpublish', 'JTOOLBAR_UNPUBLISH', true);
        if($option->taskResetOrder && $option->canDo['core.edit'])
            ToolbarHelper::custom($prefixSingle.'.resetorder','icon-loop','',Text::_('COM_EQA_BUTTON_RESET_ORDER_LABEL'),false);
        if($option->taskUpload && $option->canDo['core.create'])
            ToolbarHelper::custom($prefixPlural.'.upload','icon-upload','',Text::_('JTOOLBAR_UPLOAD'),false);
        if($option->taskPreferences && $option->canDo['core.options'])
            ToolbarHelper::preferences('com_eqa');
    }
    protected function addToolbarForLayoutUpload() : void
    {
        if(!isset($this->toolbarOption))
            return;

        if(!$this->toolbarOption->showToolbar)
            return;

        //Title
        ToolbarHelper::title($this->toolbarOption->title);

        //Buttons
        $option = $this->toolbarOption;
        $prefixSingle = $option->taskPrefixItem;
        $prefixPlural = $option->taskPrefixItems;
        if($this->toolbarOption->taskGoHome){
            ToolbarHelper::link(\JRoute::_('index.php?option=com_eqa'),'COM_EQA_BUTTON_GO_HOME_LABEL','home');
            ToolbarHelper::divider();
        }
        if($option->taskImport && $option->canDo['core.create'])
            ToolbarHelper::custom($prefixPlural.'.import','icon-upload','',Text::_('JTOOLBAR_UPLOAD'),false);
        if($option->taskCancel)
            ToolbarHelper::cancel($prefixSingle.'.cancel');
    }
    public function getLayoutData(): EqaListLayoutData
    {
        return $this->layoutData;
    }
    public function getListLayoutItemFields(): EqaListLayoutItemFields
    {
        return $this->itemFields;
    }

    public function display($tpl = null)
    {
        //Load custom javascript
	    $this->wam->useScript('com_eqa.script');
	    $this->wam->useStyle('com_eqa.style');
		$this->wam->useScript('select2.script');
		$this->wam->useStyle('select2.style');

        //Complete toolbar options if needed
        if(!isset($this->toolbarOption->canDo))
            $this->toolbarOption->canDo = GeneralHelper::getActions();
        if(!isset($this->toolbarOption->taskPrefixItems))
            $this->toolbarOption->taskPrefixItems = $this->getName();
        if(!isset($this->toolbarOption->taskPrefixItem))
            $this->toolbarOption->taskPrefixItem = StringHelper::convertPluralToSingle($this->getName());

        //Prepare layout specific data by calling preparation method whose name begins
        //with prefix 'prepareDataForLayout', and ends with layout name (the first letter must be capitalized).
        //This class already defines a method for 'edit' layout. The child class defines other methods as needed.
        $layout = $this->getLayout();
        $suffix = ucfirst($layout);
	    if($suffix=='Blog')
		    $suffix='Default';

        $method = 'prepareDataForLayout'.$suffix;
	    if(method_exists($this, $method))
		    $this->$method();

	    $method = 'configureItemFieldsForLayout'.$suffix;
		if(method_exists($this, $method))
			$this->$method();

        //Add layout specific toolbar
        $method = 'addToolbarForLayout'.$suffix;
	    if(method_exists($this, $method))
		    $this->$method();

        //Display
        parent::display($tpl);
    }
}
