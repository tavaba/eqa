<?php

/**
 * @package     Kma\Library\Kma\Model
 * @since       1.1.0
 */

namespace Kma\Library\Kma\Model;

defined('_JEXEC') or die();

use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Library\Kma\Helper\DatetimeHelper;

/**
 * Base model cho view nhật ký hệ thống (Logs).
 *
 * Chứa toàn bộ logic query, filter, ordering và sinh filter form động
 * cho bảng log. Schema bảng log (dùng chung cho mọi component):
 *
 *   id            BIGINT UNSIGNED   PK AUTO_INCREMENT
 *   user_id       INT UNSIGNED      NULL  → FK #__users.id
 *   username      VARCHAR(150)      NULL  (snapshot tại thời điểm ghi log)
 *   action        SMALLINT UNSIGNED NOT NULL
 *   is_success    TINYINT(1)        NOT NULL DEFAULT 0
 *   error_message VARCHAR(500)      NULL
 *   object_type   SMALLINT UNSIGNED NOT NULL
 *   object_id     BIGINT UNSIGNED   NOT NULL
 *   object_title  VARCHAR(500)      NULL
 *   old_value     TEXT              NULL
 *   new_value     TEXT              NULL
 *   extra_data    TEXT              NULL
 *   ip_address    BINARY(16)        NULL  (INET6_ATON / INET6_NTOA)
 *   created_at    DATETIME(3)       NOT NULL (UTC)
 *
 * Lớp con BẮT BUỘC override:
 *   - getLogTable(): string        → tên bảng, ví dụ '#__eqa_logs'
 *   - getActionClass(): string     → FQCN lớp Action của component
 *   - getObjectTypeClass(): string → FQCN enum ObjectType của component
 *
 * Ví dụ lớp con (com_eqa):
 * -----------------------------------------------------------------------
 *   class LogsModel extends \Kma\Library\Kma\Model\BaseLogsModel
 *   {
 *       protected function getLogTable(): string
 *       {
 *           return '#__eqa_logs';
 *       }
 *
 *       protected function getActionClass(): string
 *       {
 *           return \Kma\Component\Eqa\Administrator\Constant\Action::class;
 *       }
 *
 *       protected function getObjectTypeClass(): string
 *       {
 *           return \Kma\Component\Eqa\Administrator\Enum\ObjectType::class;
 *       }
 *   }
 * -----------------------------------------------------------------------
 *
 * @since 1.1.0
 */
abstract class LogsModel extends ListModel
{
	// =========================================================================
	// Abstract — bắt buộc override ở lớp con
	// =========================================================================

	/**
	 * FQCN của lớp Action của component.
	 * Lớp này phải extends \Kma\Library\Kma\Constant\Action
	 * và cung cấp static method getOptions(): array<int, string>.
	 *
	 * Ví dụ: \Kma\Component\Eqa\Administrator\Constant\Action::class
	 *
	 * @return string
	 * @since 1.1.0
	 */
	abstract public function getActionClass(): string;

	/**
	 * FQCN của enum ObjectType của component.
	 * Phải là backed int enum, mỗi case có method getLabel(): string.
	 *
	 * Ví dụ: \Kma\Component\Eqa\Administrator\Enum\ObjectType::class
	 *
	 * @return string
	 * @since 1.1.0
	 */
	abstract public function getObjectTypeClass(): string;

	// =========================================================================
	// Constructor & state
	// =========================================================================

	/**
	 * @param  array                    $config
	 * @param  MVCFactoryInterface|null $factory
	 */
	public function __construct($config = [], ?MVCFactoryInterface $factory = null)
	{
		// Whitelist các cột hợp lệ để sort — ngăn SQL injection qua list.ordering
		$config['filter_fields'] = [
			'l.id',
			'l.created_at',
			'l.user_id',
			'l.username',
			'l.action',
			'l.is_success',
			'l.object_type',
			'l.object_id',
			'l.object_title',
		];

		parent::__construct($config, $factory);
		$this->disableLogging();
	}

	/**
	 * Mặc định sắp xếp theo thời gian tạo mới nhất lên đầu.
	 *
	 * @since 1.1.0
	 */
	protected function populateState($ordering = 'creationTime', $direction = 'DESC'): void
	{
		parent::populateState($ordering, $direction);
	}

	/**
	 * Override the parent method with an 'emtpy' function
	 * since no logging action is done within this model
	 * @return int
	 *
	 * @since 1.0.3
	 */
	protected function getLogObjectType(): int
	{
		return 0;
	}



	// =========================================================================
	// Filter form — sinh động từ XML string, không dùng file XML
	// =========================================================================

	/**
	 * Override getFilterForm để sinh filter form hoàn toàn từ XML string
	 * được xây dựng trong bộ nhớ, không phụ thuộc file filter_logs.xml trên đĩa.
	 *
	 * Tuân thủ đúng cơ chế của Joomla:
	 *   - Gọi $this->loadForm() (từ FormBehaviorTrait) thay vì Form::getInstance()
	 *   - $source bắt đầu bằng '<' → loadForm() tự nhận ra là XML string
	 *     và gọi $form->load($source) thay vì $form->loadFile()
	 *   - Truyền ['load_data' => $loadData] để loadForm() gọi loadFormData()
	 *     (override bên dưới) nhằm bind đúng giá trị filter hiện tại vào form
	 *   - Form name unique theo context → tránh cache conflict giữa các component
	 *
	 * @param  array  $data      Không dùng trực tiếp (Joomla truyền vào nhưng
	 *                           dữ liệu thực lấy qua loadFormData()).
	 * @param  bool   $loadData  TRUE → bind giá trị state hiện tại vào form fields.
	 * @return Form|null
	 * @since  1.1.0
	 */
	public function getFilterForm($data = [], $loadData = true): ?Form
	{
		// Form name unique theo context để tránh cache conflict giữa các component
		$formName = $this->context . '.filter';

		try {
			return $this->loadForm(
				$formName,
				$this->buildFilterFormXml(),   // bắt đầu bằng '<' → loadForm() tự load từ string
				['control' => '', 'load_data' => $loadData]
			);
		} catch (\RuntimeException) {
			return null;
		}
	}

	/**
	 * Xây dựng XML string hoàn chỉnh cho filter form.
	 *
	 * Cấu trúc XML tuân thủ Joomla Form API:
	 *   <form>
	 *     <fields name="filter">
	 *       <field name="search" ... />
	 *       <field name="action" type="list"> <option .../> ... </field>
	 *       ...
	 *     </fields>
	 *   </form>
	 *
	 * @return string  XML hợp lệ.
	 * @since  1.1.0
	 */
	private function buildFilterFormXml(): string
	{
		$actionOptionsXml     = $this->buildActionOptionsXml();
		$objectTypeOptionsXml = $this->buildObjectTypeOptionsXml();
		$userOptionsXml       = $this->buildUserOptionsXml();

		return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<form>
    <fields name="filter">

        <!-- Tìm kiếm tự do: username (snapshot), tên đầy đủ, object_title -->
        <field
            name="search"
            type="search"
            label="Tìm kiếm"
            hint="Tên đăng nhập, tên người dùng, tiêu đề đối tượng..."
            onchange="this.form.submit();"
        />

        <!-- Hành động — options sinh từ getActionClass()::getOptions() -->
        <field
            name="action"
            type="list"
            label="Hành động"
            class="select2-basic"
            onchange="this.form.submit();"
        >
            <option value="">-- Tất cả hành động --</option>
            {$actionOptionsXml}
        </field>

        <!-- Loại đối tượng — options sinh từ getObjectTypeClass()::cases() -->
        <field
            name="object_type"
            type="list"
            label="Loại đối tượng"
            class="select2-basic"
            onchange="this.form.submit();"
        >
            <option value="">-- Tất cả đối tượng --</option>
            {$objectTypeOptionsXml}
        </field>

        <!-- Kết quả thao tác -->
        <field
            name="is_success"
            type="list"
            label="Kết quả"
            onchange="this.form.submit();"
        >
            <option value="">-- Tất cả --</option>
            <option value="1">Thành công</option>
            <option value="0">Thất bại</option>
        </field>

        <!-- Người dùng — chỉ liệt kê user có trong bảng log -->
        <field
            name="user_id"
            type="list"
            label="Người dùng"
            class="select2-basic"
            onchange="this.form.submit();"
        >
            <option value="">-- Tất cả người dùng --</option>
            {$userOptionsXml}
        </field>

        <!-- Từ ngày (người dùng nhập Local Time, model tự convert UTC) -->
        <field
            name="date_from"
            type="calendar"
            label="Từ ngày"
            format="%Y-%m-%d"
            showtime="false"
            onchange="this.form.submit();"
        />

        <!-- Đến ngày -->
        <field
            name="date_to"
            type="calendar"
            label="Đến ngày"
            format="%Y-%m-%d"
            showtime="false"
            onchange="this.form.submit();"
        />

    </fields>
</form>
XML;
	}

	/**
	 * Sinh chuỗi XML các thẻ <option> cho field 'action',
	 * lấy từ getActionClass()::getOptions().
	 *
	 * getOptions() trả về array<int, string> (value → label).
	 *
	 * @return string  Chuỗi các thẻ <option ...>...</option>.
	 * @since  1.1.0
	 */
	private function buildActionOptionsXml(): string
	{
		$actionClass = $this->getActionClass();
		$options     = $actionClass::getOptions(); // array<int, string>
		$parts       = [];

		foreach ($options as $value => $label) {
			$parts[] = sprintf(
				'<option value="%d">%s</option>',
				(int) $value,
				htmlspecialchars((string) $label, ENT_XML1, 'UTF-8')
			);
		}

		return implode("\n            ", $parts);
	}

	/**
	 * Sinh chuỗi XML các thẻ <option> cho field 'object_type',
	 * lấy từ getObjectTypeClass()::cases().
	 *
	 * Mỗi case của backed int enum phải có method getLabel(): string.
	 *
	 * @return string  Chuỗi các thẻ <option ...>...</option>.
	 * @since  1.1.0
	 */
	private function buildObjectTypeOptionsXml(): string
	{
		$objectTypeClass = $this->getObjectTypeClass();
		$cases           = $objectTypeClass::cases(); // array of BackedEnum
		$parts           = [];

		foreach ($cases as $case) {
			$parts[] = sprintf(
				'<option value="%d">%s</option>',
				$case->value,
				htmlspecialchars($case->getLabel(), ENT_XML1, 'UTF-8')
			);
		}

		return implode("\n            ", $parts);
	}

	/**
	 * Sinh chuỗi XML các thẻ <option> cho field 'user_id',
	 * lấy từ getUserOptions().
	 *
	 * @return string  Chuỗi các thẻ <option ...>...</option>.
	 * @since  1.1.0
	 */
	private function buildUserOptionsXml(): string
	{
		$parts = [];

		foreach ($this->getUserOptions() as $userId => $label) {
			$parts[] = sprintf(
				'<option value="%d">%s</option>',
				(int) $userId,
				htmlspecialchars($label, ENT_XML1, 'UTF-8')
			);
		}

		return implode("\n            ", $parts);
	}

	// =========================================================================
	// Lấy danh sách người dùng có trong log (dùng cho filter dropdown)
	// =========================================================================

	/**
	 * Trả về danh sách người dùng đã xuất hiện trong bảng log,
	 * dùng để render dropdown filter 'Người dùng'.
	 *
	 * Chỉ liệt kê những user có ít nhất một bản ghi trong bảng log —
	 * không lấy toàn bộ #__users để tránh danh sách quá dài và vô nghĩa.
	 *
	 * Mỗi option có dạng: "username (Họ tên)" nếu user còn tồn tại,
	 * hoặc "username (đã xóa)" nếu tài khoản đã bị xóa khỏi #__users.
	 *
	 * @return array<int, string>  Mảng [user_id => label] để render <option>.
	 * @since  1.1.0
	 */
	public function getUserOptions(): array
	{
		$db        = $this->getDatabase();
		$tableName = $this->logService->getLogTableName();

		// Lấy tất cả (user_id, username snapshot) phân biệt có trong log,
		// kèm tên đầy đủ hiện tại từ #__users (LEFT JOIN → NULL nếu đã xóa).
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('l.user_id',  'user_id'),
				$db->quoteName('l.username', 'snapshot_name'),   // tên lưu lúc ghi log
				$db->quoteName('u.name',     'full_name'),        // tên hiện tại (NULL nếu xóa)
			])
			->from($db->quoteName($tableName, 'l'))
			->leftJoin(
				$db->quoteName('#__users', 'u') .
				' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('l.user_id')
			)
			->where($db->quoteName('l.user_id') . ' IS NOT NULL')
			->group([
				$db->quoteName('l.user_id'),
				$db->quoteName('l.username'),
				$db->quoteName('u.name'),
			])
			->order($db->quoteName('l.username') . ' ASC');

		$db->setQuery($query);
		$rows = $db->loadObjectList();

		$options = [];
		foreach ($rows as $row) {
			$userId = (int) $row->user_id;
			$label  = $row->snapshot_name ?? ('User #' . $userId);

			// Nếu tên đầy đủ hiện tại khác username snapshot → hiển thị cả hai
			if (!empty($row->full_name) && $row->full_name !== $row->snapshot_name) {
				$label .= ' (' . $row->full_name . ')';
			} elseif (empty($row->full_name)) {
				// User đã bị xóa khỏi #__users
				$label .= ' (đã xóa)';
			}

			$options[$userId] = $label;
		}

		return $options;
	}

	// =========================================================================
	// Query
	// =========================================================================

	/**
	 * Xây dựng câu truy vấn danh sách log.
	 *
	 * Ghi chú kỹ thuật:
	 *   - ip_address lưu BINARY(16) → INET6_NTOA() khi SELECT để trả về chuỗi IP.
	 *   - created_at là UTC DATETIME(3) → giữ nguyên khi SELECT;
	 *     convert sang Local Time thực hiện ở tầng View.
	 *   - username trong log là snapshot → vẫn hiển thị được dù user bị xóa.
	 *   - LEFT JOIN #__users để lấy tên đầy đủ hiện tại (có thể NULL).
	 *
	 * @return \Joomla\Database\QueryInterface
	 * @since  1.1.0
	 */
	public function getListQuery()
	{
		$db    = $this->getDatabase();
		$tableName = $this->logService->getLogTableName();

		$query = $db->getQuery(true)
			->from($db->quoteName($tableName, 'l'))
			->leftJoin($db->quoteName('#__users', 'u'), 'u.id = l.user_id')
			->select([
				$db->quoteName('l.id',                  'id'),
				$db->quoteName('l.user_id',             'operatorId'),
				$db->quoteName('l.username',            'operatorOldName'),
				$db->quoteName('u.username',            'operatorUsername'),
				$db->quoteName('u.name',                'operatorName'),
				$db->quoteName('l.action',              'action'),
				$db->quoteName('l.is_success',          'isSuccess'),
				$db->quoteName('l.error_message',       'errorMessage'),
				$db->quoteName('l.object_type',         'objectType'),
				$db->quoteName('l.object_id',           'objectId'),
				$db->quoteName('l.object_title',        'objectTitle'),
				$db->quoteName('l.old_value',           'oldValue'),
				$db->quoteName('l.new_value',           'newValue'),
				$db->quoteName('l.extra_data',          'extraData'),
				$db->quoteName('l.created_at',          'creationTime'),
				// BINARY(16) → chuỗi IP hiển thị được
				'INET6_NTOA(' . $db->quoteName('l.ip_address') . ')'
				. ' AS ' . $db->quoteName('ipAddress'),
				// Tên đầy đủ hiện tại (NULL nếu user đã bị xóa)
			]);

		// ----- Filters -----

		// Tìm kiếm tự do: username snapshot, tên đầy đủ, object_title
		$search = trim((string) $this->getState('filter.search'));
		if ($search !== '') {
			$like = $db->quote('%' . $db->escape($search) . '%');
			$query->where(
				'(' .
				$db->quoteName('l.username')       . ' LIKE ' . $like .
				' OR ' . $db->quoteName('u.name')         . ' LIKE ' . $like .
				' OR ' . $db->quoteName('l.object_title') . ' LIKE ' . $like .
				')'
			);
		}

		// Lọc theo action (SMALLINT)
		$action = $this->getState('filter.action');
		if (is_numeric($action) && (int) $action > 0) {
			$query->where($db->quoteName('l.action') . ' = ' . (int) $action);
		}

		// Lọc theo người dùng (user_id)
		$userId = $this->getState('filter.user_id');
		if (is_numeric($userId) && (int) $userId > 0) {
			$query->where($db->quoteName('l.user_id') . ' = ' . (int) $userId);
		}

		// Lọc theo object_type (SMALLINT)
		$objectType = $this->getState('filter.object_type');
		if (is_numeric($objectType) && (int) $objectType > 0) {
			$query->where($db->quoteName('l.object_type') . ' = ' . (int) $objectType);
		}

		// Lọc theo kết quả — dùng is_numeric() vì '0' là giá trị hợp lệ
		$isSuccess = $this->getState('filter.is_success');
		if (is_numeric($isSuccess)) {
			$query->where($db->quoteName('l.is_success') . ' = ' . (int) $isSuccess);
		}

		// Lọc theo khoảng thời gian
		// Người dùng nhập Local Time → convert sang UTC trước khi so sánh với DB
		$dateFrom = trim((string) $this->getState('filter.date_from'));
		if ($dateFrom !== '') {
			$utcFrom = DatetimeHelper::convertToUtc($dateFrom . ' 00:00:00');
			if ($utcFrom !== null) {
				$query->where($db->quoteName('l.created_at') . ' >= ' . $db->quote($utcFrom));
			}
		}

		$dateTo = trim((string) $this->getState('filter.date_to'));
		if ($dateTo !== '') {
			$utcTo = DatetimeHelper::convertToUtc($dateTo . ' 23:59:59');
			if ($utcTo !== null) {
				$query->where($db->quoteName('l.created_at') . ' <= ' . $db->quote($utcTo));
			}
		}

		// ----- Ordering -----
		$orderingCol = $db->escape($this->getState('list.ordering', 'l.created_at'));
		$orderingDir = $db->escape($this->getState('list.direction', 'DESC'));
		$query->order($db->quoteName($orderingCol) . ' ' . $orderingDir);

		return $query;
	}

	/**
	 * @inheritDoc
	 * @since 1.1.0
	 */
	public function getStoreId($id = '')
	{
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.action');
		$id .= ':' . $this->getState('filter.user_id');
		$id .= ':' . $this->getState('filter.object_type');
		$id .= ':' . $this->getState('filter.is_success');
		$id .= ':' . $this->getState('filter.date_from');
		$id .= ':' . $this->getState('filter.date_to');

		return parent::getStoreId($id);
	}
}
