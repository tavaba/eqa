<?php
namespace Kma\Library\Kma\View;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\WebAsset\WebAssetManager;
use Kma\Library\Kma\Helper\EnglishHelper;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\StateHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\Model\ListModel;
use Kma\Library\Kma\Service\EnglishService;

abstract class ItemsHtmlView extends BaseHtmlView{
	/**
	 * Trong trường hợp tên model có nhiều từ (multi-word) theo dạng CamelCase
	 * thì cơ chế nạp model của Joomla sẽ không phát hiện được model khi thực
	 * thi trong Linux (case-sensitive). Việc chỉ định tên của model sẽ giúp
	 * xử lý tình huống này.
	 * Ví dụ: $listModelName = 'ExamseasonExams'
	 */
	protected ?string $listModelName=null;
	protected ?EnglishService $englishService=null;
    protected WebAssetManager $wa;
    protected ListLayoutData $layoutData;
    protected ListLayoutItemFields $itemFields;
    protected ToolbarOption $toolbarOption;
    protected mixed $item;
    public function __construct($config = [])
    {
        parent::__construct($config);
	    $this->wa = ComponentHelper::getDocument()->getWebAssetManager();
		$this->englishService = ComponentHelper::getEnglishService();
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
	    $viewName = $this->getName();
        $this->layoutData->formActionParams['view'] = $viewName;
        $this->layoutData->taskPrefixItem = $this->englishService
	        ? $this->englishService->pluralToSingular($viewName)
	        : EnglishHelper::pluralToSingular($viewName);
        $this->layoutData->taskPrefixItems = $viewName;

	    /**
	     * @var ListModel $model
	     */
		$model = $this->getModel();
        $this->layoutData->items = $model->getItems();
	    if($this->layoutData->items === false)
		    throw new Exception($model->getError());
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

	    /**
	     * @var ListModel $listModel
	     */
	    $listModel = $this->getModel();
		if(empty($listModel))
			return;
	    $items = $this->layoutData->items;
		if(empty($items))
			$items = [];

	    if($option->taskAddNew && $listModel->canCreate())
            ToolbarHelper::addNew($prefixSingle.'.add');

        if($option->taskEditList && $listModel->canEditAny($items))
            ToolbarHelper::editList($prefixSingle.'.edit');

        /*
         * Các nút đổi trạng thái và nút xóa.
         * Tách thành hai nhánh vì hai nhóm thực thể có mô hình trạng thái khác nhau
         * (xem StateHelper::STATES_FULL và STATES_BASIC).
         */
        $isFourStateMode = $listModel instanceof ListModel && $listModel->isFourStateMode();

        if ($isFourStateMode) {
            $this->addStateToolbarButtons($listModel, $items, $prefixPlural, $deletionConfirmKey);
        } else {
            $this->addBasicStateToolbarButtons($listModel, $items, $prefixPlural, $deletionConfirmKey);
        }

        if($option->taskUpload && $listModel->canCreate())
            ToolbarHelper::custom($prefixPlural.'.upload','icon-upload','',Text::_('JTOOLBAR_UPLOAD'),false);
    }

    /**
     * Dựng các nút đổi trạng thái cho thực thể dùng ĐỦ 4 TRẠNG THÁI.
     *
     * Áp dụng đúng mô hình của Joomla đối với article/category:
     *   - các nút đổi trạng thái gom vào một dropdown 'Hành động';
     *   - chỉ hiển thị những chuyển trạng thái CÓ Ý NGHĨA với bộ lọc hiện tại
     *     (đang lọc riêng 'Đang dùng' thì không cần nút 'Kích hoạt');
     *   - nút xóa vĩnh viễn CHỈ xuất hiện khi bộ lọc đang ở 'Thùng rác'.
     *
     * Bảng quyết định:
     *
     *   | Bộ lọc trạng thái     | Nút trong dropdown                              | Nút xóa        |
     *   |-----------------------|-------------------------------------------------|----------------|
     *   | '' (mặc định)         | Kích hoạt, Tạm ngừng, Lưu trữ, Bỏ vào thùng rác | (không)        |
     *   | 1  — Đang dùng        | Tạm ngừng, Lưu trữ, Bỏ vào thùng rác            | (không)        |
     *   | 0  — Tạm ngừng        | Kích hoạt, Lưu trữ, Bỏ vào thùng rác            | (không)        |
     *   | 2  — Đã lưu trữ       | Kích hoạt, Tạm ngừng, Bỏ vào thùng rác          | (không)        |
     *   | -2 — Thùng rác        | Kích hoạt, Tạm ngừng, Lưu trữ                   | Xóa vĩnh viễn  |
     *   | '*' — Tất cả          | Kích hoạt, Tạm ngừng, Lưu trữ, Bỏ vào thùng rác | (không)        |
     *
     * Hàng đầu tiên đủ cả bốn nút là có chủ ý: bộ lọc mặc định hiển thị ĐỒNG THỜI
     * bản ghi 'Đang dùng' và 'Tạm ngừng' (xem ListModel::applyStateFilter()), nên
     * cả hai chiều chuyển trạng thái đều có ý nghĩa. Điều này tự động đúng nhờ
     * các phép so sánh chặt (!==) bên dưới: $filterValue lúc đó là chuỗi rỗng nên
     * không trùng với bất kỳ mã trạng thái nào.
     *
     * @param   ListModel  $listModel           Model của danh sách.
     * @param   array      $items               Các item đang hiển thị.
     * @param   string     $prefixPlural        Tiền tố task của Items Controller.
     * @param   string     $deletionConfirmKey  Text key của thông báo xác nhận xóa.
     *
     * @return  void
     * @since   1.0.5
     */
    protected function addStateToolbarButtons(
        ListModel $listModel,
        array $items,
        string $prefixPlural,
        string $deletionConfirmKey
    ): void {
        $option      = $this->toolbarOption;
        $filterValue = $listModel->getStateFilterValue();
        $canEditState = $listModel->canEditStateAny($items);

        $showPublish   = $option->taskPublish   && $filterValue !== StateHelper::STATE_PUBLISHED;
        $showUnpublish = $option->taskUnpublish && $filterValue !== StateHelper::STATE_UNPUBLISHED;
        $showArchive   = $option->taskArchive   && $filterValue !== StateHelper::STATE_ARCHIVED;
        $showTrash     = $option->taskTrash     && $filterValue !== StateHelper::STATE_TRASHED;

        if ($canEditState && ($showPublish || $showUnpublish || $showArchive || $showTrash)) {
            $toolbar = Toolbar::getInstance();

            $dropdown = $toolbar->dropdownButton('status-group', Text::_('JTOOLBAR_CHANGE_STATUS'))
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            /*
             * Cố ý dùng standardButton() thay cho các phương thức rút gọn
             * publish()/unpublish()/archive()/trash() của Toolbar: dạng
             * standardButton($icon, $text, $task) cho phép đặt nhãn tiếng Việt
             * một cách tường minh, và đây cũng là dạng mà ToolbarHelper của thư
             * viện đã dùng ổn định từ trước. Kết quả hiển thị tương đương, vì
             * các phương thức rút gọn của Joomla cũng chỉ là StandardButton với
             * icon và task đặt sẵn.
             */
            $buttons = [];

            if ($showPublish) {
                $buttons[] = ['publish',   'JTOOLBAR_PUBLISH',          $prefixPlural . '.publish'];
            }

            if ($showUnpublish) {
                $buttons[] = ['unpublish', 'JTOOLBAR_UNPUBLISH',          $prefixPlural . '.unpublish'];
            }

            if ($showArchive) {
                $buttons[] = ['archive',   'JTOOLBAR_ARCHIVE',            $prefixPlural . '.archive'];
            }

            if ($showTrash) {
                $buttons[] = ['trash',     'JTOOLBAR_TRASH',   $prefixPlural . '.trash'];
            }

            foreach ($buttons as [$icon, $text, $task]) {
                $childBar->standardButton($icon, $text, $task)->listCheck(true);
            }
        }

        /*
         * Xóa vĩnh viễn: chỉ cho phép từ màn hình thùng rác, đúng như Joomla.
         * Việc kiểm tra quyền ở mức từng bản ghi vẫn do
         * Kma\Library\Kma\Model\AdminModel::canDelete() đảm nhiệm khi task chạy.
         */
        if ($option->taskDeleteList
            && $filterValue === StateHelper::STATE_TRASHED
            && $listModel->canDeleteAny($items)) {
            ToolbarHelper::appendDelete(
                $prefixPlural . '.delete',
                'Xóa vĩnh viễn',
                Text::_($deletionConfirmKey)
            );
        }
    }

    /**
     * Dựng các nút đổi trạng thái cho thực thể chỉ dùng 2 TRẠNG THÁI.
     *
     * Giữ nguyên hành vi vốn có: hai nút Publish/Unpublish rời nhau và nút xóa
     * luôn hiển thị (nhóm này không có thùng rác nên không thể ràng buộc nút xóa
     * theo bộ lọc).
     *
     * @param   ListModel  $listModel           Model của danh sách.
     * @param   array      $items               Các item đang hiển thị.
     * @param   string     $prefixPlural        Tiền tố task của Items Controller.
     * @param   string     $deletionConfirmKey  Text key của thông báo xác nhận xóa.
     *
     * @return  void
     * @since   1.0.5
     */
    protected function addBasicStateToolbarButtons(
        ListModel $listModel,
        array $items,
        string $prefixPlural,
        string $deletionConfirmKey
    ): void {
        $option = $this->toolbarOption;

        if($option->taskDeleteList && $listModel->canDeleteAny($items))
            ToolbarHelper::deleteList(Text::_($deletionConfirmKey), $prefixPlural.'.delete');

        $canEditState = $listModel->canEditStateAny($items);

        if($option->taskPublish && $canEditState)
            ToolbarHelper::publish($prefixPlural.'.publish', 'JTOOLBAR_PUBLISH', true);

        /*
         * Trước đây dòng dưới kiểm tra nhầm $option->taskPublish, khiến cờ
         * $option->taskUnpublish không có tác dụng. Đã sửa (1.0.5).
         */
        if($option->taskUnpublish && $canEditState)
            ToolbarHelper::unpublish($prefixPlural.'.unpublish', 'JTOOLBAR_UNPUBLISH', true);
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
		//Init
		if(!empty($this->listModelName))
		{
			//Try to create a model with the default prefix (site or administrator)
			$listModel = ComponentHelper::createModel($this->listModelName);

			//If failed, try to create a model with 'administrator' prefix
			//because front-end sometimes uses back-end models
			if(empty($listModel))
				$listModel = ComponentHelper::createModel($this->listModelName,'administrator');

			//If a model created successfully, make it the default model
			if(!empty($listModel))
				$this->setModel($listModel, true);
		}

		$viewName = $this->getName();
        if(!isset($this->toolbarOption->taskPrefixItems))
            $this->toolbarOption->taskPrefixItems = $viewName;
        if(!isset($this->toolbarOption->taskPrefixItem))
            $this->toolbarOption->taskPrefixItem = $this->englishService
	            ? $this->englishService->pluralToSingular($viewName)
	            : EnglishHelper::pluralToSingular($viewName);

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
