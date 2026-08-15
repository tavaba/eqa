<?php
namespace Kma\Library\Kma\Table;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table as BaseTable;
use Joomla\Database\DatabaseDriver;
use Kma\Library\Kma\Helper\EnglishHelper;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\StateHelper;
use Kma\Library\Kma\Service\EnglishService;

class Table extends BaseTable{
    /**
     * The name of the component that use this table (e.g. 'com_mycom')
     *
     * @var string
     * @since 1.0.0
     */
    protected string $componentName;

    /**
     * The short name of the component that use this table (e.g. 'mycom')
     *
     * @var string
     * @since 1.0.0
     */
    protected string $componentShortName;

    /**
     * The type alias of this table ('foo' for 'FooTable')
     *
     * @var string
     * @since 1.0.0
     */
    protected string $itemName;

	protected ?EnglishService $englishService = null;

	/**
	 * Tên cột trạng thái chuẩn của thư viện.
	 *
	 * Toàn bộ hệ sinh thái (com_eqa, com_survey) dùng tên cột 'state', đồng bộ
	 * với #__content của Joomla core.
	 *
	 * @var string
	 * @since 1.0.5
	 */
	public const string STATE_COLUMN = 'state';

	/**
	 * Timestamp field types and their possible column names in the database.
	 *
	 * Đây là NGUỒN DUY NHẤT (single source of truth) của danh sách tên cột timestamp.
	 * Các thành phần khác (ví dụ view Logs khi tính khác biệt giữa old_value và new_value)
	 * truy cập danh sách này qua Table::getTimestampColumnNames() thay vì khai báo lại.
	 *
	 * @var array<string, string[]>
	 * @since 1.0.0
	 */
	public const TIMESTAMP_COLUMN_OPTIONS = [
		'created'     => ['created', 'created_at', 'created_on'],
		'created_by'  => ['created_by', 'creator_id'],
		'modified'    => ['modified', 'updated', 'modified_at', 'updated_at', 'modified_on', 'updated_on'],
		'modified_by' => ['modified_by', 'updated_by', 'modifier_id'],
	];

    /**
     * Timestamp fields types and their possible column names in the database
     *
     * @var array
     * @since 1.0.0
     */
	protected array $timeStampFieldTypes = ['created', 'created_by', 'modified', 'modified_by'];
	protected array $timestampFieldOptions = self::TIMESTAMP_COLUMN_OPTIONS;

	/**
	 * Tập trạng thái mà bảng này chấp nhận.
	 *
	 * Lớp con của các thực thể VẬN HÀNH (kỳ thi, ca thi, lớp học phần...) khai báo
	 * lại thành StateHelper::STATES_BASIC để chặn giá trị 'lưu trữ'/'thùng rác'.
	 *
	 * @var int[]
	 * @since 1.0.5
	 */
	protected array $supportedStates = StateHelper::STATES_FULL;

	/**
	 * Bảng này có cột trạng thái hay không. Được xác định trong constructor.
	 *
	 * @var bool
	 * @since 1.0.5
	 */
	protected bool $hasStateColumn = false;

	/**
	 * Trả về danh sách phẳng (flat) các tên cột timestamp có thể xuất hiện trong CSDL.
	 *
	 * Dùng khi cần loại các cột timestamp ra khỏi một xử lý nào đó mà không có sẵn
	 * instance của Table — ví dụ view Logs loại chúng khỏi phép so sánh old/new value.
	 *
	 * Ví dụ:
	 *   Table::getTimestampColumnNames()
	 *     → ['created','created_at','created_on','created_by','creator_id','modified',...]
	 *
	 *   Table::getTimestampColumnNames(['modified', 'modified_by'])
	 *     → ['modified','updated','modified_at','updated_at','modified_on','updated_on',
	 *        'modified_by','updated_by','modifier_id']
	 *
	 * @param  string[]  $types  Các nhóm cần lấy ('created', 'created_by', 'modified',
	 *                           'modified_by'). Mảng rỗng → lấy tất cả các nhóm.
	 * @return string[]          Danh sách tên cột, đã loại trùng và đánh lại chỉ số.
	 * @since  1.2.0
	 */
	public static function getTimestampColumnNames(array $types = []): array
	{
		$groups = $types === []
			? self::TIMESTAMP_COLUMN_OPTIONS
			: array_intersect_key(self::TIMESTAMP_COLUMN_OPTIONS, array_flip($types));

		return array_values(array_unique(array_merge(...array_values($groups))));
	}

    /**
     * Detected timestamp fields for this table
     *
     * @var array
     * @since 1.0.0
     */
    protected array $detectedTimestampFields = [];

    /**
     * Whether timestamp fields have been detected
     *
     * @var boolean
     * @since 1.0.0
     */
    protected bool $timestampFieldsDetected = false;

    /**
     * Danh sách tên cột (giá trị thực tế của $detectedTimestampFields cho type 'created'
     * và 'created_by') mà tại thời điểm load() gần nhất đang mang giá trị NULL trong CSDL.
     *
     * Dùng để đảm bảo: nếu một bản ghi cũ đang có 'created'/'created_by' = NULL (dữ liệu
     * lịch sử, tạo ra trước khi cơ chế tự động điền timestamp tồn tại) thì khi admin edit
     * và store() lại, các cột này vẫn được giữ nguyên NULL, không bị ghi đè bởi giá trị
     * rỗng '' do form/handleNullDatetimeFields() sinh ra (gây lỗi 'Incorrect datetime value').
     *
     * @var array
     * @since 1.0.4
     */
    protected array $originalNullFields = [];

    /**
     * Cache for table columns
     *
     * @var array
     * @since 1.0.0
     */
    protected static array $allTableColumns = [];

	public function __construct(DatabaseDriver $db, string $tableName='', string $keyName='')
    {
        $className = get_class($this);                                                 //Result: Kma\Library\Kma\Table\FooTable
        $shortClassName = basename(str_replace('\\', '/', $className));  //Result: FooTable

        //Initialize some properties
        $this->componentName = ComponentHelper::getName();
        $this->componentShortName = ComponentHelper::getNameWithoutPrefix();
	    $this->englishService = ComponentHelper::getEnglishService();
	    $this->itemName = strtolower(substr($shortClassName,0,strlen($shortClassName)-5)); //Result: foo
        $this->typeAlias = $this->componentName . '.' . $this->itemName;                                //Result: com_mycom.foo

        //Prepare table name and key to call parent constructor
        if(empty($tableName)){
	        $suffix = $this->englishService
		        ? $this->englishService->singularToPlural($this->itemName)
		        : EnglishHelper::singularToPlural($this->itemName);
            $tableName = '#__' . $this->componentShortName . '_' . $suffix;
        }
        if(empty($keyName))
            $keyName='id';
        parent::__construct($tableName,$keyName, $db);

        // Detect timestamp fields for this table
        $this->detectTimestampFields();

        // Detect the state column and register the column alias (see detectStateColumn())
        $this->detectStateColumn();
    }

	/**
	 * Phát hiện cột trạng thái và đăng ký column alias.
	 *
	 * ĐÂY LÀ MẮT XÍCH BẮT BUỘC. Joomla core tra cột trạng thái thông qua
	 * getColumnAlias('published'):
	 *   - Joomla\CMS\Table\Table::publish()
	 *   - Joomla\CMS\MVC\Model\AdminModel::publish()
	 *   - Joomla\CMS\MVC\Model\AdminModel::batch*()
	 * Cột thực tế trong CSDL của chúng ta tên là 'state', nên nếu không khai báo
	 * alias thì mọi thao tác publish/unpublish/archive/trash sẽ hỏng.
	 *
	 * @return  void
	 * @since   1.0.5
	 */
	protected function detectStateColumn(): void
	{
		$columns = $this->getCachedTableColumns();

		if (isset($columns[self::STATE_COLUMN])) {
			$this->hasStateColumn = true;
			$this->setColumnAlias('published', self::STATE_COLUMN);
		}
	}

	/**
	 * Bảng này có cột trạng thái hay không.
	 *
	 * @return  bool
	 * @since   1.0.5
	 */
	public function hasStateColumn(): bool
	{
		return $this->hasStateColumn;
	}

	/**
	 * Tập trạng thái mà bảng này chấp nhận.
	 *
	 * @return  int[]
	 * @since   1.0.5
	 */
	public function getSupportedStates(): array
	{
		return $this->supportedStates;
	}

	/**
	 * Lấy (và cache) danh sách cột của bảng hiện tại.
	 *
	 * @return  array
	 * @since   1.0.5
	 */
	protected function getCachedTableColumns(): array
	{
		$tableName = $this->_tbl;

		if (!isset(static::$allTableColumns[$tableName])) {
			try {
				static::$allTableColumns[$tableName] = $this->_db->getTableColumns($tableName, false);
			} catch (Exception $e) {
				static::$allTableColumns[$tableName] = [];
			}
		}

		return static::$allTableColumns[$tableName];
	}

    protected function _getAssetName(): string
	{
        return $this->componentName . '.' . $this->itemName . '.' . $this->id;
	}

	protected function _getAssetTitle(): string
	{
        //If the table has a column named `name` or `title`, use that.
        if(isset($this->title))
            return $this->title;
        if(isset($this->name))
            return $this->name;

        //Otherwise, use the table name itself
		return $this->itemName;
	}

	/**
	 * @throws \Exception
     * @since 1.0.0
	 */
	protected function _getAssetParentId(BaseTable|null $table = null, $id = null): int
	{
        // Build the query to get the asset id for the component.
        // By default, the component itsefl is the parent of all other assets
        $db = $this->_db;
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__assets'))
            ->where($db->quoteName('name') . ' = ' . $db->quote($this->componentName));

        // Get the asset id from the database.
        $db->setQuery($query);
        $result = $db->loadResult();
        if ($result)
        {
            return (int) $result;
        }

        // Fallback to parent method if component asset not found
        return parent::_getAssetParentId($table, $id);
	}

    /**
     * Method to store a row in the database from the Table instance properties.
     * Automatically handles timestamp fields if they exist.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success.
     * @since 1.0.0
     */
    public function store($updateNulls = true)
    {
        $this->populateTimestampFields();
        $this->normalizeState();

        return parent::store($updateNulls);
    }

	/**
	 * Chuẩn hóa giá trị cột trạng thái trước khi ghi xuống CSDL.
	 *
	 * Chặn hai tình huống:
	 *   - giá trị rác/NULL do form hoặc mã nghiệp vụ cũ để lại;
	 *   - giá trị 'lưu trữ'/'thùng rác' bị gán cho thực thể chỉ hỗ trợ 2 trạng thái.
	 *
	 * @return  void
	 * @since   1.0.5
	 */
	protected function normalizeState(): void
	{
		if (!$this->hasStateColumn) {
			return;
		}

		$column = self::STATE_COLUMN;

		$this->$column = StateHelper::sanitize(
			$this->$column ?? null,
			$this->supportedStates,
			StateHelper::STATE_PUBLISHED
		);
	}

	public function bind($src, $ignore = [])
	{
        // Convert src to array if it's an object
        if (is_object($src)) {
            $src = get_object_vars($src);
        }

        //Process rules
		if(isset($src['rules']) && is_array($src['rules'])){
			$this->setRules($src['rules']);
		}

        //Call parent bind()
		return parent::bind($src, $ignore);
	}

    /**
     * Method to load a row from the database by primary key and bind the fields to the Table instance properties.
     *
     * @param   mixed    $keys   An optional primary key value to load the row by, or an array of fields to match.
     * @param   boolean  $reset  True to reset the default values before loading the new row.
     *
     * @return  boolean  True if successful. False if the row not found or on error.
     * @since 1.0.0
     */
    public function load($keys = null, $reset = true): bool
    {
        $result = parent::load($keys, $reset);

        if ($result) {
            // Phải ghi nhận cột nào đang thực sự NULL trong CSDL TRƯỚC KHI
            // handleNullDatetimeFields() chuyển NULL thành chuỗi rỗng để hiển thị form.
            $this->detectOriginalNullFields();
            $this->handleNullDatetimeFields();
        }

        return $result;
    }

    /**
     * Detect which timestamp fields exist in the current table
     *
     * @return  void
     * @since 1.0.0
     */
    protected function detectTimestampFields(): void
    {
        if ($this->timestampFieldsDetected) {
            return;
        }

        $columns = $this->getCachedTableColumns();
        $this->detectedTimestampFields = [];

        // Check which timestamp fields exist in this table
	    foreach ($this->timeStampFieldTypes as $type)
	    {
			if(isset($this->detectedTimestampFields[$type]))
				continue;
		    foreach ($columns as $columnName => $columnInfo)
		    {
				if (in_array($columnName, $this->timestampFieldOptions[$type]))
					$this->detectedTimestampFields[$type] = $columnName;
		    }
	    }
	    $this->timestampFieldsDetected = true;
    }

    /**
     * Ghi nhận những cột 'created'/'created_by' hiện đang mang giá trị NULL
     * trong CSDL (vừa được load() vào object, còn nguyên giá trị null của PHP).
     * Phải được gọi TRƯỚC handleNullDatetimeFields(), vì hàm đó sẽ chuyển
     * NULL của cột kiểu ngày ('created') thành chuỗi rỗng để phục vụ hiển thị form.
     *
     * @return  void
     * @since 1.0.4
     */
    protected function detectOriginalNullFields(): void
    {
        $this->originalNullFields = [];

        foreach (['created', 'created_by'] as $type) {
            $fieldName = $this->detectedTimestampFields[$type] ?? null;
            if ($fieldName && !isset($this->$fieldName)) {
                $this->originalNullFields[] = $fieldName;
            }
        }
    }

    /**
     * Populate timestamp fields automatically
     *
     * @return  void
     * @throws Exception
     * @since 1.0.0
     */
    protected function populateTimestampFields(): void
    {
        if (empty($this->detectedTimestampFields)) {
            return;
        }

        $now = Factory::getDate()->toSql();
        $user = Factory::getApplication()->getIdentity();
        $userId = $user->id;
        $isNewRecord = !(int) $this->{$this->_tbl_key};

        // Handle created fields (only for new records)
        if ($isNewRecord) {
	        $createdField = $this->detectedTimestampFields['created'] ?? null;
	        if ($createdField) {
                if (empty($this->$createdField) || $this->$createdField === $this->_db->getNullDate()) {
                    $this->$createdField = $now;
                }
            }

	        $createdByField = $this->detectedTimestampFields['created_by'] ?? null;
            if ($createdByField) {
                if (empty($this->$createdByField)) {
                    $this->$createdByField = $userId;
                }
            }
        } else {
            // Bản ghi đã tồn tại: 'created'/'created_by' không được phép thay đổi qua edit.
            // Nếu tại thời điểm load() cột này đang NULL (dữ liệu cũ, tạo trước khi cơ chế
            // tự động điền timestamp tồn tại), ép lại về NULL bất kể form/handleNullDatetimeFields()
            // đã gán giá trị gì (thường là '' hoặc 0), để tránh lỗi khi ghi xuống CSDL và
            // tránh làm sai lệch dữ liệu (vd: biến NULL thành 0 cho created_by).
            foreach (['created', 'created_by'] as $type) {
                $fieldName = $this->detectedTimestampFields[$type] ?? null;
                if ($fieldName && in_array($fieldName, $this->originalNullFields, true)) {
                    $this->$fieldName = null;
                }
            }
        }

        // Handle modified/updated fields (always for existing records, conditionally for new)
        $modifiedField = $this->detectedTimestampFields['modified'] ?? null;
        if ($modifiedField) {
            $this->$modifiedField = $now;
        }

        $modifiedByField = $this->detectedTimestampFields['modified_by'] ?? null;
        if ($modifiedByField) {
            $this->$modifiedByField = $userId;
        }
    }


    /**
     * Handle null datetime fields after loading
     *
     * @return  void
     * @since 1.0.0
     */
    protected function handleNullDatetimeFields(): void
    {
        if (empty($this->detectedTimestampFields)) {
            return;
        }

        $nullDate = $this->_db->getNullDate();

        foreach ($this->detectedTimestampFields as $type => $fieldName) {
            // Only handle datetime fields, not user ID fields
            if (in_array($type, ['created', 'modified', 'updated'])) {
                if (isset($this->$fieldName) && ($this->$fieldName === $nullDate || empty($this->$fieldName))) {
                    $this->$fieldName = '';
                }
            }
        }
    }

    /**
     * Method to reset class properties to the defaults
     *
     * @return  void
     * @since 1.0.0
     */
    public function reset()
    {
        parent::reset();

        // Reset timestamp fields to defaults if they exist.
        // Duyệt theo type => fieldName (thay vì chỉ theo giá trị) để so sánh đúng LOẠI field
        // ('created'/'modified'/'updated') bất kể tên cột thực tế trong CSDL là gì
        // (vd: 'created_at', 'updated_on'...). Trước đây so sánh nhầm $fieldName (tên cột)
        // với danh sách type, khiến các cột có tên khác 'created'/'modified'/'updated'
        // bị set về 0 thay vì null khi khởi tạo form trắng.
        foreach ($this->detectedTimestampFields as $type => $fieldName) {
            if (property_exists($this, $fieldName)) {
                if (in_array($type, ['created', 'modified'])) {
                    $this->$fieldName = null;
                } else {
                    $this->$fieldName = 0;
                }
            }
        }
    }

    public function getAssetName(): string
    {
        return $this->_getAssetName();
    }

	/**
	 * Lấy snapshot toàn bộ dữ liệu hiện tại của row trong bộ nhớ.
	 * Ví dụ: sau khi store() xong, lấy new_value để ghi log
	 * $table->load($id);
	 * $table->mark = 8.5;
	 * $table->store();
	 *
	 * $newValue = $table->getSnapshot();  // ✅ Đọc trực tiếp — không cần query thêm
	 * @since 1.0.3
	 */
	public function getSnapshot(): array
	{
		// getFields() trả về danh sách cột thực tế của bảng DB
		// — đây là cách Joomla 5 khuyến nghị thay cho getProperties()
		$fields = $this->getFields();
		$snapshot = [];

		foreach (array_keys($fields) as $col) {
			$snapshot[$col] = $this->$col ?? null;
		}

		return $snapshot;
	}

	/**
	 * Load một row và trả về snapshot, KHÔNG thay đổi trạng thái hiện tại.
	 * Được dùng khi cần lấy trạng thái trước khi thay đổi, tức là cần đọc từ DB trong khi
	 * $this chưa được load hoặc giá trị trong bộ nhớ đã bị ghi đè:
	 * Ví dụ: trong save() của Model, cần lấy old_value trước khi lưu
	 * public function save($data): bool
	 * {
	 *      $table = $this->getTable();
	 *      $id    = $data['id'] ?? 0;
	 *
	 *      // $this->table chưa load gì — phải query DB để lấy trạng thái cũ
	 *      $oldValue = $id ? $table->loadSnapshot($id) : null;
	 *
	 *      $result   = parent::save($data);
	 *
	 *      // Lúc này $table đã được parent::save() load và store xong
	 *      $newValue = $table->getSnapshot();  // Đọc trực tiếp — không query thêm
	 *
	 *      $this->writeLog(new LogEntry(
	 *          // ...
	 *          oldValue: $oldValue,
	 *          newValue: $newValue,
	 *      ));
	 *
	 *      return $result;
	 * }
	 */
	public function loadSnapshot(int $id): array
	{
		$clone = clone $this;
		if (!$clone->load($id)) return [];
		return $clone->getSnapshot();
	}

}
