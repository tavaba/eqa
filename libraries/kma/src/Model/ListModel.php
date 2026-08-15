<?php
namespace Kma\Library\Kma\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel as BaseListModel;
use Joomla\CMS\User\User;
use Joomla\Database\QueryInterface;
use Kma\Library\Kma\DataObject\LogEntry;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\EnglishHelper;
use Kma\Library\Kma\Helper\StateHelper;
use Kma\Library\Kma\Service\EnglishService;
use Kma\Library\Kma\Service\LogService;
use Kma\Library\Kma\Service\MailService;

/**
 * Class này sẽ được thừa kế bởi các Items Model
 *
 * @since 1.0
 */
abstract class ListModel extends BaseListModel
{
	/**
	 * An instance of LogService that is retrived from DIC by default
	 * ListModel không thực hiện CRUD nên không ghi log trực tiếp.
	 * Tuy nhiên cần inject LogService để subclass có thể ghi log nếu cần.
	 */
	protected ?LogService $logService=null;
	protected ?EnglishService $englishService=null;
	protected ?MailService $mailService=null;

	/** Bật/Tắt chế độ ghi log. Tự động bật trong constructor nếu tồn tại $logService */
	protected bool $loggingEnabled=false;

	/**
     * @var User Current logged-in user. It is initialized by the constructor.
     * @since 1.0.0
     */
    protected User $user;

    /**
     * @var string The entity type (the singular form of the model name).
     *              It is initialized by the constructor.
     * @since 1.0.0
     */
    protected string $entityType;

    /**
     * @var string|null Name of action defined in the ACL system which allows users to perform delete operation on their own records.
     *                      For example, if you define 'com.delete.own' here,
     *                      then the user who created an item will be able to delete his/her own items.
     *                      Note that this right may not be defined in the ACL system. Therefore, wrap it inside a try/catch block.
     * @since 1.0.0
     */
    protected ?string $actionDeleteOwn=null;

    /**
     * @var string|null Name of field containing the ID of the creator of the item.
     *                      This field is used when checking ownership of an item.
     *                      For example, if you define 'created_by' here,
     *                      then the user who created an item will be able to perform all operations on his/her own items.
     * @since 1.0.0
     */
    protected ?string $ownerIdField=null;

	/**
	 * Tên cột trạng thái (kèm alias của bảng chính) dùng trong getListQuery().
	 *
	 * Lớp con ghi đè khi bảng chính mang alias khác 'a', hoặc khi cần lọc theo
	 * trạng thái của một bảng được JOIN vào.
	 *
	 * @var    string
	 * @since  1.0.5
	 */
	protected string $stateColumn = 'a.state';

	/**
	 * Tập trạng thái mà thực thể này hỗ trợ.
	 *
	 * Mặc định CÓ CHỦ Ý là bộ rút gọn 2 trạng thái. Lý do: phần lớn List Model
	 * trong component không quản lý danh mục (danh sách thí sinh, bài thi, lượt
	 * phúc tra...) và nhiều bảng thậm chí không có cột trạng thái. Để mặc định
	 * là 4 trạng thái sẽ khiến toolbar của các màn hình đó mọc thêm nút 'Lưu
	 * trữ'/'Thùng rác' vô nghĩa.
	 *
	 * Model của các thực thể DANH MỤC khai báo lại:
	 *
	 *     protected array $supportedStates = StateHelper::STATES_FULL;
	 *
	 * @var    int[]
	 * @since  1.0.5
	 */
	protected array $supportedStates = StateHelper::STATES_BASIC;

	/**
	 * Danh sách các state nằm NGOÀI namespace 'filter.' cần được đưa vào store id.
	 * Lớp con có thể bổ sung khi có state đặc biệt ảnh hưởng tới getListQuery().
	 *
	 * @var    string[]
	 * @since  1.0.4
	 */
	protected array $storeIdExtraStates = ['list.ordering', 'list.direction'];

	/**
	 * Danh sách các state cần LOẠI TRỪ khỏi store id, ghi bằng tên đầy đủ
	 * (ví dụ 'filter.foo'). Dùng cho các state không ảnh hưởng tới kết quả truy vấn.
	 *
	 * @var    string[]
	 * @since  1.0.4
	 */
	protected array $storeIdIgnoredStates = [];

	/*
	 * Some properties for caching purposes
	 */
    private bool $__canCreate;
    private bool $__canEditAny;
    private bool $__canEditStateAny;
    private bool $__canDeleteAny;
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        parent::__construct($config, $factory);

	    //Resolve the LogService instance
	    $this->logService = ComponentHelper::getLogService();
	    if($this->logService)
		    $this->loggingEnabled = true;
		$this->englishService = ComponentHelper::getEnglishService();
		$this->mailService = ComponentHelper::getMailService();

        /*
         * Initialize some properties
         */
        $this->ownerIdField=$config['owner_id_field']??'created_by';
        $this->actionDeleteOwn=$config['action_delete_own']??'com.delete.own';

        /**
         * Determine the current user. Note that the BaseModel class already has the
         * getCurrentUser() method, but Joomla docs mentions that this method may return
         * an empty User object.
         */
        $this->user = Factory::getApplication()->getIdentity();

        //Get the entity type from the model name
        $this->entityType = $this->englishService
	        ? $this->englishService->pluralToSingular($this->getName())
	        : EnglishHelper::pluralToSingular($this->getName());
    }

	/**
	 * Thiêt lập LogService thay cho instance được khởi tạo mặc định trong constructor
	 *
	 * @param   LogService  $logService
	 * @since 1.0.3
	 */
	public function setLogService(LogService $logService)
	{
		$this->logService = $logService;
	}

	/**
	 * Subclass khai báo object_type của mình — chỉ một lần.
	 */
	abstract protected function getLogObjectType(): int;

	public function enableLogging(): static  { $this->loggingEnabled = true;  return $this; }
	public function disableLogging(): static { $this->loggingEnabled = false; return $this; }

	/**
	 * Ghi log tùy chọn cho các action đặc biệt (không phải CRUD chuẩn).
	 * Đồng bộ với method cùng tên trong {@see \Kma\Library\Kma\Model\AdminModel}.
	 *
	 * @param   LogEntry  $entry
	 *
	 * @return  void
	 * @since   1.0.4
	 */
	protected function writeLog(LogEntry $entry): void
	{
		if ($this->loggingEnabled && $this->logService) {
			$this->logService->write($entry);
		}
	}


	/**
     * Method to autopopulate the model state.
     * @since 1.0.0
     */
    protected function populateState($ordering = null, $direction = null): void
    {
        $ordering = $this->getUserStateFromRequest($this->context . '.ordercol', 'filter_order', $ordering);
		if(empty($ordering))
			$ordering = 'id';
        $direction = $this->getUserStateFromRequest($this->context . '.orderdirn', 'filter_order_Dir', $direction);
		if(empty($direction))
			$direction = 'ASC';
        parent::populateState($ordering, $direction);
    }

	// =========================================================================
	// Bộ lọc trạng thái
	// =========================================================================

	/**
	 * Tập trạng thái mà thực thể này hỗ trợ.
	 *
	 * @return  int[]
	 * @since   1.0.5
	 */
	public function getSupportedStates(): array
	{
		return $this->supportedStates;
	}

	/**
	 * Thực thể này có dùng đủ 4 trạng thái hay không.
	 *
	 * View dựa vào đây để quyết định dựng toolbar kiểu Joomla (có dropdown
	 * 'Hành động', có thùng rác) hay kiểu rút gọn (chỉ Publish/Unpublish).
	 *
	 * @return  bool
	 * @since   1.0.5
	 */
	public function isFourStateMode(): bool
	{
		return count($this->supportedStates) > 2;
	}

	/**
	 * Giá trị hiện tại của bộ lọc trạng thái, đã chuẩn hóa.
	 *
	 * Trả về đúng một trong ba dạng:
	 *   - StateHelper::FILTER_DEFAULT ('')  — người dùng CHƯA chọn gì;
	 *   - StateHelper::FILTER_ALL ('*')     — người dùng chọn 'Tất cả trạng thái';
	 *   - một mã trạng thái hợp lệ (int)    — người dùng chọn một trạng thái cụ thể.
	 *
	 * Lưu ý: cố ý KHÔNG quy trường hợp 'chưa chọn' về STATE_PUBLISHED. Hai tình
	 * huống đó cho ra hai câu truy vấn khác nhau (xem applyStateFilter()) và cũng
	 * cho ra hai bộ nút toolbar khác nhau (xem ItemsHtmlView::addStateToolbarButtons()).
	 *
	 * @return  int|string
	 * @since   1.0.5
	 */
	public function getStateFilterValue(): int|string
	{
		$value = $this->getState('filter.state');

		if ($value === StateHelper::FILTER_ALL) {
			return StateHelper::FILTER_ALL;
		}

		if ($value === null || $value === '') {
			return StateHelper::FILTER_DEFAULT;
		}

		return StateHelper::sanitize($value, $this->supportedStates, StateHelper::STATE_PUBLISHED);
	}

	/**
	 * Bộ lọc trạng thái hiện đang ở chế độ 'Thùng rác' hay không.
	 *
	 * @return  bool
	 * @since   1.0.5
	 */
	public function isStateFilterTrashed(): bool
	{
		return $this->getStateFilterValue() === StateHelper::STATE_TRASHED;
	}

	/**
	 * Áp điều kiện lọc theo trạng thái vào truy vấn danh sách.
	 *
	 * Thay thế cho khối mã lặp lại ở mọi List Model trước đây:
	 *
	 *     $published = $this->getState('filter.published');
	 *     if (is_numeric($published)) {
	 *         $query->where('a.published = ' . (int) $published);
	 *     }
	 *
	 * Khối mã cũ có khiếm khuyết nghiêm trọng: khi bộ lọc để trống thì KHÔNG áp
	 * điều kiện nào, nên danh sách sẽ lộ cả bản ghi đã lưu trữ và đã bỏ vào thùng
	 * rác. Quy tắc mới:
	 *
	 *   | filter.state            | Điều kiện SQL        |
	 *   |-------------------------|----------------------|
	 *   | '' hoặc null (mặc định) | state IN (1, 0)      |
	 *   | '*'                     | (không áp điều kiện) |
	 *   | mã trạng thái hợp lệ    | state = <mã>         |
	 *   | giá trị không hợp lệ    | state = 1            |
	 *
	 * Hàng đầu tiên bám sát hành vi của Joomla với article/category: mặc định ẩn
	 * 'Đã lưu trữ' và 'Thùng rác', nhưng VẪN hiện bản ghi 'Tạm ngừng' để quản trị
	 * viên không tưởng nhầm là mất dữ liệu ngay sau khi tạm ngừng một bản ghi.
	 *
	 * Với thực thể chỉ hỗ trợ 2 trạng thái, phép giao trong
	 * StateHelper::getDefaultVisibleStates() cho ra đúng {1, 0} — tức là toàn bộ
	 * bản ghi, giữ nguyên hành vi vốn có của các màn hình đó.
	 *
	 * @param   QueryInterface  $query   Truy vấn cần bổ sung điều kiện.
	 * @param   string|null     $column  Tên cột trạng thái. Null = dùng $this->stateColumn.
	 *
	 * @return  void
	 * @since   1.0.5
	 */
	protected function applyStateFilter(QueryInterface $query, ?string $column = null): void
	{
		$column = $column ?: $this->stateColumn;
		$value  = $this->getStateFilterValue();

		// Người dùng chọn 'Tất cả trạng thái' — không áp điều kiện nào
		if ($value === StateHelper::FILTER_ALL) {
			return;
		}

		// Người dùng chưa chọn gì — áp tập trạng thái hiển thị mặc định
		if ($value === StateHelper::FILTER_DEFAULT) {
			$states = StateHelper::getDefaultVisibleStates($this->supportedStates);

			$query->where(
				$column . ' IN (' . implode(', ', array_map('intval', $states)) . ')'
			);

			return;
		}

		// Người dùng chọn một trạng thái cụ thể
		$query->where($column . ' = ' . (int) $value);
	}

	/**
	 * Sinh store id phản ánh ĐẦY ĐỦ mọi tham số truy vấn.
	 *
	 * Store id là khóa của các bộ nhớ đệm nội bộ trong một request:
	 * $this->cache (items), getTotal() và getStart(). Nếu hai lần gọi có state
	 * khác nhau nhưng store id giống nhau, lần gọi sau sẽ nhận kết quả của lần
	 * gọi trước — sai dữ liệu và sai phân trang.
	 *
	 * Phương thức này tự động thu thập:
	 *   1. Toàn bộ state thuộc namespace 'filter.';
	 *   2. Các state khai báo trong $storeIdExtraStates;
	 *   3. Trừ đi các state khai báo trong $storeIdIgnoredStates.
	 *
	 * Nhờ vậy, lớp con KHÔNG cần override getStoreId() nữa; thêm filter mới vào
	 * getListQuery() là store id tự động cập nhật theo.
	 *
	 * Lưu ý kỹ thuật: KHÔNG khai báo return type cho phương thức này. Joomla core
	 * cũng không khai báo, và nhiều lớp con hiện hữu đang override mà không có
	 * return type — thêm ': string' ở đây sẽ gây lỗi covariance (Fatal error).
	 *
	 * @param   string  $id  Store id gốc do lớp con truyền vào.
	 *
	 * @return  string
	 * @since   1.0.4
	 */
	protected function getStoreId($id = '')
	{
		$parts = [];

		// 1. Toàn bộ state thuộc namespace 'filter.'
		$stateArray = $this->getState()->toArray();

		if (!empty($stateArray['filter']) && is_array($stateArray['filter'])) {
			foreach ($stateArray['filter'] as $name => $value) {
				$parts['filter.' . $name] = $value;
			}
		}

		// 2. Các state bổ sung nằm ngoài namespace 'filter.'
		foreach ($this->storeIdExtraStates as $name) {
			$parts[$name] = $this->getState($name);
		}

		// 3. Loại trừ các state không ảnh hưởng tới truy vấn
		foreach ($this->storeIdIgnoredStates as $name) {
			unset($parts[$name]);
		}

		// 4. Sắp xếp theo tên để chữ ký ổn định, không phụ thuộc thứ tự set state
		ksort($parts);

		// 5. Chuẩn hóa giá trị và ghép thành chữ ký
		$signature = [];

		foreach ($parts as $name => $value) {
			$signature[] = $name . '=' . $this->normalizeStoreIdValue($value);
		}

		$id .= ':' . hash('sha256', implode('|', $signature));

		return parent::getStoreId($id);
	}

	/**
	 * Chuyển một giá trị state bất kỳ thành chuỗi dùng được trong store id.
	 *
	 * Phân biệt rõ null (chưa chọn) với chuỗi rỗng (chọn giá trị rỗng), vì hai
	 * trạng thái này cho ra hai câu truy vấn khác nhau. Giá trị array/object được
	 * quy về dấu vân tay nội dung thay vì nối chuỗi trực tiếp — nối trực tiếp sẽ
	 * gây cảnh báo 'Array to string conversion' trên PHP 8.
	 *
	 * @param   mixed  $value  Giá trị state cần chuẩn hóa.
	 *
	 * @return  string
	 * @since   1.0.4
	 */
	protected function normalizeStoreIdValue(mixed $value): string
	{
		if ($value === null) {
			return '~null~';
		}

		if (is_bool($value)) {
			return $value ? '1' : '0';
		}

		if (is_scalar($value)) {
			return (string) $value;
		}

		return hash('sha256', serialize($value));
	}

    /**
     * 'ImportForm' có nghĩa là form để import dữ liệu.
     * Cần lưu ý quy tắc đặt tên cho import form và cả tên file tương ứng
     * để đảm bảo phương thức này hoạt động thông suốt.
     * @param string $name
     * @param string $source    Name of the XML form file without extension
     *
     * @return false|Form
     * @throws Exception
     * @since 1.0
     */
    public function getImportForm(string $name='', string $source=''): bool|Form
    {
        $modelName = $this->getName();
        if(empty($name))
            $name = $this->option . '.import_'.$modelName;
        if(empty($source))
            $source = 'import_'.$modelName;         //Name of the XML form file without extension
        $form = $this->loadForm($name,$source);
        if(empty( $form))
            return false;
        else return $form;
    }

    /**
     * Check if user owns the record
     *
     * @param User $user User object
     * @param object $record The record object
     * @return  boolean  True if user owns the record, false otherwise
     *
     * @since   1.0.0
     */
    protected function checkOwnership(User $user, object $record): bool
    {
        return $this->ownerIdField
            && isset($record->{$this->ownerIdField})
            && (int)$record->{$this->ownerIdField} == $user->id;
    }
    protected function getAssetName(int $itemId): string
    {
        return "$this->option.$this->name.$itemId";
    }

    /**
     * Check if the current user can create a new item.
     * @param string|null $specificAction The specific action defined in the ACL system
     *              which allows users to create new items of specific entity type.
     *              For example, 'com.create.survey'.
     * @return bool
     * @since 1.0.0
     */
    public function canCreate(?string $specificAction=null):bool
    {
        $user = $this->user;
        if(isset($this->__canCreate))
            return $this->__canCreate;

        if($user->authorise('core.create', $this->option))
        {
            $this->__canCreate = true;
            return true;
        }

        $this->__canCreate = $specificAction && $user->authorise($specificAction, $this->option);
        return $this->__canCreate;
    }

    public function canEditAny(array $items): bool
    {
        if(isset($this->__canEditAny))
            return $this->__canEditAny;
        $user = $this->user;

        //1. If there are no items at all, we cannot edit them
        if (!count($items))
        {
            $this->__canEditAny=false;
            return false;
        }

        //2. If the user has permission to 'core.edit.own', we'll check if
        //the user owns any of these items.
        $hasPermissionToEditOwn = $user->authorise('core.edit.own', $this->option);
        if ($hasPermissionToEditOwn)
        {
            foreach ($items as $item) {
                if ($this->checkOwnership($user, $item))
                {
                    $this->__canEditAny=true;
                    return true;
                }
            }
        }

        //3.1. If there's no 'asset_id' field, we'll check for component-level permissions
        if(!property_exists($items[0], 'asset_id'))
        {
            $this->__canEditAny=$user->authorise('core.edit', $this->option);
            return $this->__canEditAny;
        }

        //3.2. Otherwise, we need to check if the user has permission to edit
        // each individual item.
        foreach ($items as $item)
        {
            $assetName = $this->getAssetName($item->id);
            if($user->authorise('core.edit', $assetName))
            {
                $this->__canEditAny=true;
                return true;
            }
        }

        //The user cannot edit any of these items
        $this->__canEditAny=false;
        return false;
    }
     public function canEditStateAny(array $items): bool
    {
        if(isset($this->__canEditStateAny))
            return $this->__canEditStateAny;
        $user = $this->user;

        //1. If there are no items at all, we cannot edit them
        if (!count($items))
        {
            $this->__canEditStateAny=false;
            return false;
        }

        //2. If the user has permission to 'core.edit.own', we'll check if
        //the user owns any of these items.
        $hasPermissionToEditOwn = $user->authorise('core.edit.own', $this->option);
        if ($hasPermissionToEditOwn)
        {
            foreach ($items as $item) {
                if ($this->checkOwnership($user, $item))
                {
                    $this->__canEditStateAny=true;
                    return true;
                }
            }
        }

        //3.1 If there's no 'asset_id' field, we'll check for component-level permissions
        if(!property_exists($items[0], 'asset_id'))
        {
            $this->__canEditStateAny = $user->authorise('core.edit', $this->option)
                || $user->authorise('core.edit.state', $this->option);
            return $this->__canEditStateAny;
        }

        //3.2. Otherwise, we need to check if the user has permission to edit state
        // each individual item.
        foreach ($items as $item)
        {
            $assetName = $this->getAssetName($item->id);
            if($user->authorise('core.edit', $assetName)
                || $user->authorise('core.edit.state', $assetName))
            {
                $this->__canEditStateAny = true;
                return true;
            }
        }

        //The user cannot edit any of these items
        $this->__canEditStateAny = false;
        return false;
    }

    public function canDeleteAny(array $items): bool
    {
        if(isset($this->__canDeleteAny))
            return $this->__canDeleteAny;
        $user = $this->user;

        //1. If there are no items at all, we cannot delete them
        if (!count($items))
        {
            $this->__canDeleteAny=false;
            return false;
        }

        //2. If the user has permission to delete his/her own records, we'll check if
        //the user owns any of these items.
        $hasPermissionToDeleteOwn = $this->actionDeleteOwn
            && $user->authorise($this->actionDeleteOwn, $this->option);
        if ($hasPermissionToDeleteOwn)
        {
            foreach ($items as $item) {
                if ($this->checkOwnership($user, $item))
                {
                    $this->__canDeleteAny=true;
                    return true;
                }
            }
        }

        //3.1. If there's no 'asset_id' field, we'll check for component-level permissions
        if(!property_exists($items[0], 'asset_id'))
        {
            $this->__canDeleteAny = $user->authorise('core.delete', $this->option);
            return $this->__canDeleteAny;
        }

        //3.2. Otherwise, we need to check if the user has permission to delete
        // each individual item.
        foreach ($items as $item)
        {
            $assetName = $this->getAssetName($item->id);
            if($user->authorise('core.delete', $assetName))
            {
                $this->__canDeleteAny=true;
                return true;
            }
        }

        //5. The user cannot delete any of these items
        $this->__canDeleteAny=false;
        return false;
    }
}
