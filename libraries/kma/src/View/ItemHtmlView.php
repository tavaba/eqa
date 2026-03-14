<?php
namespace Kma\Library\Kma\View;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetManager;
use Kma\Library\Kma\Helper\EnglishHelper;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\Model\AdminModel;

class ItemHtmlView extends BaseHtmlView{
	public WebAssetManager $wa;
    protected $form;
    protected $item;
    protected ToolbarOption $toolbarOption;
    public function __construct($config = [])
    {
        parent::__construct($config);
	    $this->wa = ComponentHelper::getDocument()->getWebAssetManager();
        $this->toolbarOption = new ToolbarOption();
    }
    protected function loadCommonListLayoutData(ListLayoutData &$data, ListModel $listModel): void
    {
        $data->taskPrefixItem = $this->getName();
        $data->taskPrefixItems = EnglishHelper::singleToPlural($this->getName());
        $data->items = $listModel->getItems();
        $data->pagination = $listModel->getPagination();
        $data->showPaginationLimitBox = true;
        $data->listOrderingField = $this->escape($listModel->getState('list.ordering'));
        $data->listOrderingDirection = $this->escape($listModel->getState('list.direction'));
        $data->sortByOrder = $data->listOrderingField == 'ordering';
        $filterForm = $listModel->getFilterForm();
        if(!empty($filterForm)) {
            $data->filterForm = $filterForm;
            $data->showPaginationLimitBox = false;
        }
        $data->activeFilters = $listModel->getActiveFilters();
    }
    protected function prepareDataForLayoutEdit(){
	    //Layout data
        $model = $this->getModel();
	    $this->form = $this->getForm();
	    $this->item = $model->getItem();

        //Toolbar
        $textKeyPrefix = strtoupper(ComponentHelper::getName()) . '_MANAGER_';
        $this->toolbarOption->setItemEditTasks();
        if(empty($this->toolbarOption->title))
        {
            $itemUpperName = strtoupper($this->toolbarOption->taskPrefixItem);
            if(empty($this->item->id))
                $key = $textKeyPrefix . $itemUpperName . '_ADD_TITLE';
            else
                $key = $textKeyPrefix . $itemUpperName . '_EDIT_TITLE';
            $this->toolbarOption->title = Text::_($key);
        }
    }

    /**
     * Hiển thi toolbar cho item view.
     * Có khả năng tự xác định các language keys dựa theo controller name.
     * Controller name lại được xác định thông qua view name.
     * Do vậy nếu tuân thủ chuẩn xác quy tắc đặt tên class và language keys
     * thì mọi việc sẽ rất thuận tiện.
     *
     * @return void
     * @throws Exception
     * @since 1.0
     */
    protected function addToolbarForLayoutEdit(){
        if(!isset($this->toolbarOption))
            return;

        if(!$this->toolbarOption->showToolbar)
            return;

        /**
         * @var AdminModel $adminModel
         */
        $adminModel = $this->getModel();
        $canEdit = $adminModel->canEdit($this->item)
            || (empty($this->item->id) && $adminModel->canCreate());

        ToolbarHelper::title($this->toolbarOption->title);

        //Buttons
        $prefix = $this->toolbarOption->taskPrefixItem;
        if($this->toolbarOption->taskApply && $canEdit)
            ToolbarHelper::apply($prefix.'.apply');

        if($this->toolbarOption->taskSave && $canEdit)
            ToolbarHelper::save($prefix.'.save');

        if($this->toolbarOption->taskSave2New && $adminModel->canCreate() && $canEdit)
            ToolbarHelper::save2new($prefix.'.save2new');

        if($this->toolbarOption->taskCancel)
            ToolbarHelper::cancel($prefix.'.cancel');
    }

    protected function init(): void
    {
        //Complete toolbar configuration
        if(!isset($this->toolbarOption->taskPrefixItem))
            $this->toolbarOption->taskPrefixItem = $this->getName();
        if(!isset($this->toolbarOption->taskPrefixItems))
            $this->toolbarOption->taskPrefixItems = EnglishHelper::singleToPlural($this->getName());

        //Prepare layout specific data by calling preparation method whose name begins
        //with prefix 'prepareDataForLayout', and ends with layout name (the first letter must be capitalized).
        //This class already defines a method for 'edit' layout. The child class defines other methods as needed.
        $layout = $this->getLayout();
        $suffix = ucfirst($layout);
        $method = 'prepareDataForLayout'.$suffix;
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
