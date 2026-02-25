<?php
namespace Kma\Library\Kma\View;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetManager;
use Kma\Library\Kma\Helper\EnglishHelper;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\Model\AdminModel;
use Kma\Library\Kma\Model\ListModel;

abstract class ItemsHtmlView extends BaseHtmlView{
    protected WebAssetManager $wa;
    protected ListLayoutData $layoutData;
    protected ListLayoutItemFields $itemFields;
    protected ToolbarOption $toolbarOption;
    protected mixed $item;
    public function __construct($config = [])
    {
        parent::__construct($config);
	    $this->wa = ComponentHelper::getDocument()->getWebAssetManager();
	    $this->layoutData = new ListLayoutData();
        $this->itemFields = new ListLayoutItemFields();
        $this->toolbarOption = new ToolbarOption();
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
        $titleKey = strtoupper(ComponentHelper::getName()).'_MANAGER_'.$upperControllerNameItems.'_TITLE';
        $this->toolbarOption->title = Text::_($titleKey);


        //Layout data
        $this->layoutData->formActionParams['view'] = $this->getName();
        $this->layoutData->taskPrefixItem = EnglishHelper::pluralToSingle($this->getName());
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

        /**
         * @var ListModel $listModel
         */
        $listModel = $this->getModel();
        $items = $this->layoutData->items;

        //Title
        ToolbarHelper::title($this->toolbarOption->title);

        //Buttons
        $option = $this->toolbarOption;
        $prefixSingle = $option->taskPrefixItem;
        $prefixPlural = $option->taskPrefixItems;
        $deletionConfirmKey = strtoupper(ComponentHelper::getName()) . '_MSG_CONFIRM_DELETE';
        if($this->toolbarOption->taskGoHome){
            ToolbarHelper::appendGoHome();
        }
        if($option->taskAddNew && $listModel->canCreate())
            ToolbarHelper::addNew($prefixSingle.'.add');

        if($option->taskEditList && $listModel->canEditAny($items))
            ToolbarHelper::editList($prefixSingle.'.edit');

        if($option->taskDeleteList && $listModel->canDeleteAny($items))
            ToolbarHelper::deleteList(Text::_($deletionConfirmKey),$prefixPlural.'.delete');

        if($option->taskPublish && $listModel->canEditStateAny($items))
            ToolbarHelper::publish($prefixPlural.'.publish', 'JTOOLBAR_PUBLISH', true);

        if($option->taskPublish && $listModel->canEditStateAny($items))
            ToolbarHelper::unpublish($prefixPlural.'.unpublish', 'JTOOLBAR_UNPUBLISH', true);

        if($option->taskUpload && $listModel->canCreate())
            ToolbarHelper::custom($prefixPlural.'.upload','icon-upload','',Text::_('JTOOLBAR_UPLOAD'),false);
    }
    protected function prepareDataForLayoutImport() : void
    {
        //Toolbar
        $this->toolbarOption->setUploadTasks();
        $upperControllerNameItems = strtoupper($this->toolbarOption->taskPrefixItems);
        $titleKey = strtoupper(ComponentHelper::getName()).'_MANAGER_'.$upperControllerNameItems.'_IMPORT_TITLE';
        $this->toolbarOption->title = Text::_($titleKey);

        /**
         * Data
         * @var ListModel $model
         */
        $model = $this->getModel();
        $this->uploadForm = $model->getImportForm();
    }

    protected function addToolbarForLayoutImport() : void
    {
        if(!isset($this->toolbarOption))
            return;

        if(!$this->toolbarOption->showToolbar)
            return;

        /**
         * @var ListModel $listModel
         */
        $listModel = $this->getModel();


        //Title
        ToolbarHelper::title($this->toolbarOption->title);

        //Buttons
        $option = $this->toolbarOption;
        $prefixSingle = $option->taskPrefixItem;
        $prefixPlural = $option->taskPrefixItems;
        if($this->toolbarOption->taskGoHome){
            ToolbarHelper::appendGoHome();
            ToolbarHelper::divider();
        }
        if($option->taskImport && $listModel->canCreate())
            ToolbarHelper::custom($prefixPlural.'.import','icon-upload','',Text::_('JTOOLBAR_UPLOAD'),false);
        if($option->taskCancel)
            ToolbarHelper::cancel($prefixSingle.'.cancel');
    }
    public function getLayoutData(): ListLayoutData
    {
        return $this->layoutData;
    }
    public function getListLayoutItemFields(): ListLayoutItemFields
    {
        return $this->itemFields;
    }

    protected function init(): void
    {
        if(!isset($this->toolbarOption->taskPrefixItems))
            $this->toolbarOption->taskPrefixItems = $this->getName();
        if(!isset($this->toolbarOption->taskPrefixItem))
            $this->toolbarOption->taskPrefixItem = EnglishHelper::pluralToSingle($this->getName());

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
    }
    public function display($tpl = null)
    {
        //Initialize
        $this->init();

        //Display
        parent::display($tpl);
    }
}
