<?php
namespace Kma\Component\Eqa\Site\Base;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\WebAsset\WebAssetManager;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutData;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\StringHelper;

abstract class EqaItemsHtmlView extends BaseHtmlView{
	protected bool $accessDenied;
	protected WebAssetManager $wam;
    protected EqaListLayoutData $layoutData;
    protected EqaListLayoutItemFields $itemFields;
    public function __construct($config = [])
    {
        parent::__construct($config);
		$this->accessDenied = false;
	    $this->wam = GeneralHelper::getDocument()->getWebAssetManager();
	    $this->layoutData = new EqaListLayoutData();
        $this->itemFields = new EqaListLayoutItemFields();
    }

    /*
     * Nếu lớp con của lớp này ghi đè (overrite) phương thức 'prepareDataForLayoutDefault'
     * thì nó cần gọi phương thức của lớp cha (nếu cần gọi) trước khi thực hiện thao tác
     * của riêng mình.
     */
    protected function prepareDataForLayoutDefault() : void
    {
        //Layout data
        $this->layoutData->formActionParams = ['view'=>$this->getName()];
        $this->layoutData->taskPrefixItem = StringHelper::convertPluralToSingle($this->getName());
        $this->layoutData->taskPrefixItems = $this->getName();

        $this->layoutData->items = $this->get('Items');
        $pagination = $this->get('Pagination');
        if(!empty($pagination)) {
            $this->layoutData->pagination = $pagination;
            $this->layoutData->showPaginationLimitBox = true;
        }

        $state = $this->get('State');
        $this->layoutData->listOrderingField = $this->escape($state->get('list.ordering'));
        $this->layoutData->listOrderingDirection = $this->escape($state->get('list.direction'));
        $this->layoutData->sortByOrder = $this->layoutData->listOrderingField == 'ordering';

        $filterForm = $this->get('FilterForm');
        if(!empty($filterForm)) {
            $this->layoutData->filterForm = $filterForm;
            $this->layoutData->showPaginationLimitBox = false;
        }

        $activeFilters = $this->get('ActiveFilters');
        if(!empty($activeFilters))
            $this->layoutData->activeFilters = $activeFilters;
    }
    public function display($tpl = null)
    {
	    $this->wam->useStyle('com_eqa.style');
		$this->wam->useScript('select2.script');
		$this->wam->useStyle('select2.style');
	    HTMLHelper::_('bootstrap.loadCss');
	    HTMLHelper::_('bootstrap.framework');
//		$this->wam->registerAndUseStyle('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css');
//	    $this->wam->registerAndUseScript('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', [], ['defer' => true]);

	    //Xác định layout và gọi một số phương thức tương ứng để chuẩn bị dữ liệu cho việc hiển thị
	    //- prepareData***:         gọi model để nạp các Items và thực hiện tiền xử lý (nếu có)
	    //- configureItemFields***: định nghĩa các trường có trong mỗi Item
	    //- setTitleAndToolbar***:  thiết lập Title và hiển thị thanh công cụ
        $layout = $this->getLayout();
        $suffix = ucfirst($layout);
		if($suffix=='Blog')
			$suffix='Default';

		//Chuẩn bị dữ liệu để hiển thị. Đồng thời, nếu cần, kiểm tra xem người dùng
	    //có quyền truy cập layout được chỉ định hay không
	    $method = 'prepareDataForLayout'.$suffix;
	    if(method_exists($this, $method))
		    $this->$method();

		if($this->accessDenied)
		{
			echo 'Bạn không có quyền truy cập trang này<br/>';
			return;
		}

	    $method = 'configureItemFieldsForLayout'.$suffix;
	    if(method_exists($this, $method))
		    $this->$method();

	    $method = 'setTitleAndToolbarForLayout'.$suffix;
		if(method_exists($this, $method))
		{
			echo '<div class="toolbar bg-secondary-subtle p-2 mb-3 border-bottom d-flex justify-content-start align-items-center">';
			$this->$method();
			echo  '</div>';
		}

        //Display
        parent::display($tpl);
    }
}
