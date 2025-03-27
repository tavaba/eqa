<?php
namespace Kma\Component\Eqa\Site\Base;
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetManager;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutData;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\StringHelper;

class EqaItemHtmlView extends BaseHtmlView{
	protected WebAssetManager $wam;
    protected $form;
    protected $item;
    public function __construct($config = [])
    {
	    $this->wam = GeneralHelper::getDocument()->getWebAssetManager();
	    parent::__construct($config);
    }

    public function display($tpl = null)
    {

	    //Load custom javascript
	    $this->wam->useScript('com_eqa.script');
	    $this->wam->useStyle('com_eqa.style');
	    $this->wam->useScript('select2.script');
	    $this->wam->useStyle('select2.style');
	    HTMLHelper::_('bootstrap.loadCss');
	    HTMLHelper::_('bootstrap.framework');

        //Prepare layout specific data by calling preparation method whose name begins
        //with prefix 'prepareDataForLayout', and ends with layout name (the first letter must be capitalized).
        //This class already defines a method for 'edit' layout. The child class defines other methods as needed.
        $layout = $this->getLayout();
        $suffix = ucfirst($layout);
        $method = 'prepareDataForLayout'.$suffix;
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
}
