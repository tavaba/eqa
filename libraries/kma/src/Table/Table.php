<?php
namespace Kma\Library\Kma\Table;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table as BaseTable;
use Joomla\Database\DatabaseDriver;
use Kma\Library\Kma\Helper\EnglishHelper;
use Kma\Library\Kma\Helper\ComponentHelper;
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
     * Timestamp fields types and their possible column names in the database
     *
     * @var array
     * @since 1.0.0
     */
	protected array $timeStampFieldTypes = ['created', 'created_by', 'modified', 'modified_by'];
	protected array $timestampFieldOptions = [
		'created' => ['created', 'created_at', 'created_on'],
		'created_by' => ['created_by', 'creator_id'],
		'modified' => ['modified', 'updated', 'modified_at', 'updated_at', 'modified_on', 'updated_on'],
		'modified_by' => ['modified_by', 'updated_by', 'modifier_id'],
	];

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
     * Cache for table columns
     *
     * @var array
     * @since 1.0.0
     */
    protected static array $allTableColumns = [];

	/**
	 * Cache danh sách cột DATETIME theo tên bảng, dùng chung trong cùng một request.
	 *
	 * Cấu trúc: [ '#__table_name' => ['col_a', 'col_b', ...], ... ]
	 *
	 * @var array<string, string[]>
	 * @since 1.0.0
	 */
	private static array $datetimeFields = [];

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

        return parent::store($updateNulls);
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

        $tableName = $this->_tbl;

        // Check cache first
        if (!isset(static::$allTableColumns[$tableName])) {
            try {
                static::$allTableColumns[$tableName] = $this->_db->getTableColumns($tableName, false);
            } catch (Exception $e) {
                // If we can't get columns, assume no timestamp fields
                static::$allTableColumns[$tableName] = [];
            }
        }

        $columns = static::$allTableColumns[$tableName];
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

        // Reset timestamp fields to defaults if they exist
        foreach ($this->detectedTimestampFields as $fieldName) {
            if (property_exists($this, $fieldName)) {
                if (in_array($fieldName, ['created', 'modified', 'updated'])) {
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
	 * Trả về danh sách tên các cột có kiểu DATETIME trong bảng hiện tại.
	 * Ngoại trừ cột 'checked_out_time' (nếu có)
	 * Kết quả được cache theo tên bảng để tránh truy vấn lặp lại nhiều lần
	 * trong cùng một request (đặc biệt hữu ích khi xử lý nhiều bản ghi).
	 *
	 * Ví dụ sử dụng:
	 * <code>
	 *   $datetimeFields = $this->getDatetimeFields();
	 *   // → ['created_at', 'updated_at', 'start_time', ...]
	 * </code>
	 *
	 * @return string[]  Mảng các tên cột DATETIME (có thể rỗng nếu bảng không có cột nào).
	 *
	 * @since 1.0.0
	 */
	public function getDatetimeFields(): array
	{
		$tableName = $this->_tbl;

		if (isset(self::$datetimeFields[$tableName])) {
			return self::$datetimeFields[$tableName];
		}

		// getTableColumns($table, false) trả về mảng object đầy đủ metadata,
		// trong đó mỗi object có property 'Type' chứa kiểu dữ liệu SQL.
		// Check cache first
		if (!isset(static::$allTableColumns[$tableName])) {
			try {
				static::$allTableColumns[$tableName] = $this->_db->getTableColumns($tableName, false);
			} catch (Exception $e) {
				// If we can't get columns, assume no timestamp fields
				static::$allTableColumns[$tableName] = [];
			}
		}
		$columns = static::$allTableColumns[$tableName];

		//Loại bỏ cột 'checked_out_time' khỏi danh sách
		if(isset($columns['checked_out_time']))
			unset($columns['checked_out_time']);

		$datetimeFields = [];

		foreach ($columns as $columnName => $columnInfo) {
			if (strtolower($columnInfo->Type) === 'datetime') {
				$datetimeFields[] = $columnName;
			}
		}

		self::$datetimeFields[$tableName] = $datetimeFields;

		return $datetimeFields;
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