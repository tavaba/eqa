<?php
namespace Kma\Component\Eqa\Administrator\Base;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetManager;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\StringHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class EqaItemHtmlView extends BaseHtmlView{
	protected WebAssetManager $wam;
    protected $form;
    protected $item;
    protected EqaToolbarOption $toolbarOption;
    public function __construct($config = [])
    {
	    $this->wam = GeneralHelper::getDocument()->getWebAssetManager();
	    parent::__construct($config);
        $this->toolbarOption = new EqaToolbarOption();
    }

    /**
     * Child classes should set value for the property $toolbarOption
     * before calling this method.
     * @param $tpl
     * @return void
     * @throws Exception
     * @since 1.0
     */
    public function display($tpl = null)
    {

	    //Load custom javascript
	    $this->wam->useScript('com_eqa.script');
	    $this->wam->useStyle('com_eqa.style');
	    $this->wam->useScript('select2.script');
	    $this->wam->useStyle('select2.style');

        //Complete toolbar configuration
        if(!isset($this->toolbarOption->canDo))
            $this->toolbarOption->canDo = GeneralHelper::getActions();
        if(!isset($this->toolbarOption->taskPrefixItem))
            $this->toolbarOption->taskPrefixItem = $this->getName();
        if(!isset($this->toolbarOption->taskPrefixItems))
            $this->toolbarOption->taskPrefixItems = StringHelper::convertSingleToPlural($this->getName());

        //Prepare layout specific data by calling preparation method whose name begins
        //with prefix 'prepareDataForLayout', and ends with layout name (the first letter must be capitalized).
        //This class already defines a method for 'edit' layout. The child class defines other methods as needed.
        $layout = $this->getLayout();
        $suffix = ucfirst($layout);
        $method = 'prepareDataForLayout'.$suffix;
        $this->$method();

        //Add layout specific toolbar
        $method = 'addToolbarForLayout'.$suffix;
        $this->$method();

        //Display
        parent::display($tpl);
    }

    protected function loadCommonListLayoutData(EqaListLayoutData &$data, ListModel $listModel): void
    {
        $data->taskPrefixItem = $this->getName();
        $data->taskPrefixItems = StringHelper::convertSingleToPlural($this->getName());
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
	    $this->form = $this->getForm();
	    $this->item = $this->get('Item');

        //Toolbar
        $this->toolbarOption->setItemEditTasks();
        if(empty($this->toolbarOption->title))
        {
            $itemUpperName = strtoupper($this->toolbarOption->taskPrefixItem);
            if(empty($this->item->id))
                $key = 'COM_EQA_MANAGER_'.$itemUpperName.'_ADD_TITLE';
            else
                $key = 'COM_EQA_MANAGER_'.$itemUpperName.'_EDIT_TITLE';
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

        ToolbarHelper::title($this->toolbarOption->title);

        //Buttons
        $canDo = $this->toolbarOption->canDo;
        $prefix = $this->toolbarOption->taskPrefixItem;
        if($this->toolbarOption->taskApply && $canDo['core.edit'])
            ToolbarHelper::apply($prefix.'.apply');
        if($this->toolbarOption->taskSave && $canDo['core.edit'])
            ToolbarHelper::save($prefix.'.save');
        if($this->toolbarOption->taskSave2New && $canDo['core.create'] && $canDo['core.edit'])
            ToolbarHelper::save2new($prefix.'.save2new');
        if($this->toolbarOption->taskCancel)
            ToolbarHelper::cancel($prefix.'.cancel');
    }
}
