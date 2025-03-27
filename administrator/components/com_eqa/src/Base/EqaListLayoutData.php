<?php
namespace Kma\Component\Eqa\Administrator\Base;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Pagination\Pagination;

/*
 * Class này định nghĩa một số thông tin thường gặp mà View có thể muốn truyền cho Layout của mình.
 * Trong trường hợp cần truyền các thông tin khác, View sẽ thêm thuộc tính vào chính View object
 * hoặc inject thêm thuộc tính vào object của class này. Khi đó, layout sẽ cần biết để xử lý thích hợp.
 *
 * Ghi chú: ở đây không áp dụng Property's Type Decleration vì như vậy sẽ khiến cho việc kiểm tra kiểu
 * khi khởi tạo dữ liệu rất phiền phức, làm cho mã bị rối.
 */
class EqaListLayoutData
{
    public $viewName;
    public $formActionParams;
    public $formHiddenFields;
    public $taskPrefixItem;
    public $taskPrefixItems;
    public $item;
    public $items;
    public $form;
    public $filterForm;
    public $pagination;
    public bool $showPaginationLimitBox;
    public $listOrderingField;
    public $listOrderingDirection;
    public bool $sortByOrder;
    public $activeFilters;
}