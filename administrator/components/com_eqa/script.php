<?php
/**
 * @package     Com_Eqa
 * @subpackage  Installation Script
 *
 * Migration v2.0.0
 * ----------------
 * Các thay đổi schema so với v1.x:
 *
 *   created_at  DATETIME     → giữ nguyên (không thay đổi)
 *   created_by  VARCHAR(255) → giữ nguyên tên, đổi kiểu INT(11), giá trị username → user ID
 *   updated_at  DATETIME     → đổi tên thành modified_at (không đổi kiểu, không đổi giá trị)
 *   updated_by  VARCHAR(255) → đổi tên thành modified_by, đổi kiểu INT(11), giá trị username → user ID
 *
 * Quy tắc chuyển đổi giá trị:
 *   - username tìm thấy trong #__users → gán user ID tương ứng
 *   - username không tìm thấy (user đã bị xóa) → gán FALLBACK_USER_ID (= 0)
 *   - NULL → giữ nguyên NULL
 *
 * Yêu cầu: Joomla 5.0+, PHP 8.1+, MySQL 8.0+
 *
 * Cách hoạt động:
 *   1. Tự động detect bảng có prefix 'eqa_' qua INFORMATION_SCHEMA
 *      (chỉ bảng thực sự còn cột cần xử lý → idempotent)
 *   2. Build bảng tra cứu username → user ID một lần cho toàn bộ
 *   3. Với mỗi bảng, thực hiện theo thứ tự:
 *      a. Xử lý created_by: fill user ID → MODIFY COLUMN → ADD INDEX
 *      b. Xử lý updated_by → modified_by: fill user ID vào cột tạm
 *         → RENAME updated_at → modified_at
 *         → DROP updated_by + RENAME cột tạm → modified_by (một lệnh ALTER)
 *         → ADD INDEX
 *
 * An toàn:
 *   - Idempotent: kiểm tra sự tồn tại của từng cột trước khi xử lý
 *   - Lỗi tại một bảng không dừng toàn bộ; ghi log và tiếp tục
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Log\Log;

class Com_EqaInstallerScript extends InstallerScript
{
    // =========================================================================
    // Cấu hình
    // =========================================================================

    /** Joomla minimum version */
    protected $minimumJoomla = '5.0';

    /** PHP minimum version */
    protected $minimumPhp = '8.1';

    /**
     * Prefix của component (phần sau Joomla table prefix).
     * Script tìm bảng có tên: {joomla_prefix}{COMPONENT_PREFIX}*
     * Ví dụ: jos_eqa_questions, jos_eqa_categories, ...
     */
    private const COMPONENT_PREFIX = 'eqa_';

    /**
     * User ID gán cho username không tồn tại trong #__users
     * (user đã bị xóa hoặc dữ liệu không hợp lệ).
     * NULL vẫn giữ nguyên NULL — chỉ áp dụng cho giá trị không NULL, không rỗng.
     * 0 = "unknown / guest" — convention của Joomla.
     */
    private const FALLBACK_USER_ID = 0;

    /**
     * Các thư mục gốc của component cần xóa sạch trước khi Joomla copy file mới.
     * Đường dẫn tính từ JPATH_ROOT, dùng dấu '/'.
     *
     * Thư mục media/com_eqa được quản lý riêng bởi Joomla media installer,
     * KHÔNG liệt kê ở đây để tránh xóa nhầm asset đang được dùng.
     */
    private const COMPONENT_DIRS = [
        'administrator/components/com_eqa',
        'components/com_eqa',
    ];
	
    // =========================================================================
    // Joomla Installer Hooks
    // =========================================================================

    /**
     * Chạy TRƯỚC khi Joomla copy file mới lên server.
     *
     * Xóa sạch toàn bộ thư mục component hiện có để đảm bảo:
     * - Không còn file cũ bị đổi tên/xóa/di chuyển tồn tại song song
     * - Version mới là bản sạch hoàn toàn sau khi Joomla copy lại
     *
     * Thư mục không tồn tại → bỏ qua (an toàn cho cài lần đầu).
     */
    public function preflight($type, $parent): bool
    {
        if ($type === 'update') {
            $this->clearComponentDirs();
        }

        return true;
    }

    /**
     * Chạy sau khi Joomla copy file mới lên server (lệnh Update).
     */
    public function update($parent): bool
    {
            return $this->runMigration200();
    }

    /**
     * Xóa sạch nội dung bên trong từng thư mục trong COMPONENT_DIRS.
     *
     * Lưu ý: Chỉ xóa NỘI DUNG bên trong (file + subfolder), KHÔNG xóa
     * thư mục gốc. Điều này để Joomla installer có thể copy file mới vào
     * đúng vị trí mà không cần tạo lại thư mục.
     *
     * Thư mục không tồn tại → bỏ qua.
     * Lỗi xóa một item → ghi log, tiếp tục xử lý item tiếp theo.
     */
    private function clearComponentDirs(): void
    {
        $root    = rtrim(JPATH_ROOT, DIRECTORY_SEPARATOR);
        $deleted = 0;
        $failed  = 0;

        foreach (self::COMPONENT_DIRS as $relativeDir) {
            $fullPath = $root . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

            if (!is_dir($fullPath)) {
                Log::add(
                    "com_eqa: Thư mục không tồn tại, bỏ qua: {$relativeDir}",
                    Log::INFO,
                    'com_eqa'
                );
                continue;
            }

            $this->logInfo("Đang xóa nội dung thư mục: {$relativeDir}");

            [$dirDeleted, $dirFailed] = $this->clearDirContents($fullPath);
            $deleted += $dirDeleted;
            $failed  += $dirFailed;

            $this->logInfo(
                "Thư mục {$relativeDir}: đã xóa {$dirDeleted} item"
                . ($dirFailed > 0 ? ", thất bại {$dirFailed} item" : '')
                . '.'
            );
        }

        // Báo cáo tổng kết
        if ($failed === 0) {
            $this->logInfo("Dọn dẹp thư mục cũ hoàn tất: đã xóa {$deleted} item.");
        } else {
            $this->logWarning(
                "Dọn dẹp thư mục cũ: {$deleted} item xóa thành công, "
                . "{$failed} item thất bại. Kiểm tra quyền truy cập file hệ thống."
            );
        }
    }

    /**
     * Xóa toàn bộ nội dung bên trong một thư mục (đệ quy), giữ lại thư mục gốc.
     *
     * Duyệt theo thứ tự CHILD_FIRST: xóa file/subfolder con trước,
     * sau đó xóa subfolder cha — đảm bảo thứ tự đúng khi xóa cây thư mục.
     *
     * @return array{int, int} [$deletedCount, $failedCount]
     */
    private function clearDirContents(string $dirPath): array
    {
        $deleted = 0;
        $failed  = 0;

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $itemPath = $item->getRealPath();

            if ($item->isDir()) {
                $ok = @rmdir($itemPath);
            } else {
                $ok = @unlink($itemPath);
            }

            if ($ok) {
                $deleted++;
            } else {
                $failed++;
                Log::add(
                    "com_eqa: Không thể xóa: {$itemPath}",
                    Log::WARNING,
                    'com_eqa'
                );
            }
        }

        return [$deleted, $failed];
    }

    // =========================================================================
    // Migration 2.0.0
    // =========================================================================

    private function runMigration200(): bool
    {
        $db = Factory::getDbo();

        try {
            // --- Bước 1: Tìm tất cả bảng cần xử lý ---
            $tables = $this->detectComponentTables($db);

            if (empty($tables)) {
                $this->logWarning('Migration 2.0.0: Không tìm thấy bảng nào cần migrate.');
                return true;
            }

            $this->logInfo(
                'Migration 2.0.0: Tìm thấy ' . count($tables) . ' bảng cần xử lý: '
                . implode(', ', $tables)
            );

            // --- Bước 2: Build bảng tra cứu username → user ID (một lần cho toàn bộ) ---
            $userMap = $this->buildUserMap($db, $tables);

            $this->logInfo(
                'Migration 2.0.0: Tìm thấy ' . count($userMap) . ' user(s) cần mapping.'
            );

            // --- Bước 3: Migrate từng bảng ---
            $errors = [];

            foreach ($tables as $tableName) {
                try {
                    $this->migrateTable($db, $tableName, $userMap);
                } catch (\Throwable $e) {
                    $msg = "Lỗi tại bảng `{$tableName}`: " . $e->getMessage();
                    $errors[] = $msg;
                    Log::add("com_eqa: {$msg}", Log::ERROR, 'com_eqa');
                }
            }

            // --- Bước 4: Báo cáo kết quả ---
            if (!empty($errors)) {
                foreach ($errors as $err) {
                    $this->logError($err);
                }
                $this->logWarning(
                    'Migration 2.0.0: Hoàn tất với ' . count($errors) . ' lỗi. '
                    . 'Kiểm tra Joomla error log để biết chi tiết.'
                );
                // Trả về true để installer không rollback các bảng đã migrate thành công.
                return true;
            }

            $this->logInfo('Migration 2.0.0: Hoàn tất thành công!');

        } catch (\Throwable $e) {
            $msg = 'Migration 2.0.0 thất bại nghiêm trọng: ' . $e->getMessage();
            Log::add("com_eqa: {$msg}", Log::ERROR, 'com_eqa');
            $this->logError($msg);
            return false;
        }

        return true;
    }

    // =========================================================================
    // Detect bảng qua INFORMATION_SCHEMA
    // =========================================================================

    /**
     * Tìm tất cả bảng của component còn ít nhất một trong các cột sau
     * chưa được migrate:
     *   - created_by kiểu VARCHAR  (cần đổi kiểu → INT)
     *   - updated_at kiểu DATETIME (cần đổi tên → modified_at)
     *   - updated_by kiểu VARCHAR  (cần đổi tên + kiểu → modified_by INT)
     *
     * Nếu tất cả các cột đều đã được xử lý → bảng không xuất hiện → idempotent.
     *
     * @return string[] Mảng tên bảng đầy đủ (có Joomla prefix)
     */
    private function detectComponentTables(\Joomla\Database\DatabaseInterface $db): array
    {
        $dbName      = $db->setQuery('SELECT DATABASE()')->loadResult();
        $tablePrefix = $db->getPrefix() . self::COMPONENT_PREFIX;
        $qDbName     = $db->quote($dbName);
        $qTableLike  = $db->quote($tablePrefix . '%');

        // Detect bảng còn cột created_by hoặc updated_by kiểu VARCHAR
        // (cột đã là INT hoặc đã đổi tên sẽ không xuất hiện)
        // HOẶC còn cột updated_at (chưa đổi tên thành modified_at)
        $sql = "
            SELECT DISTINCT t.TABLE_NAME
            FROM information_schema.TABLES t
            INNER JOIN information_schema.COLUMNS c
                ON c.TABLE_SCHEMA = t.TABLE_SCHEMA
               AND c.TABLE_NAME   = t.TABLE_NAME
               AND (
                     (c.COLUMN_NAME IN ('created_by', 'updated_by')
                      AND c.DATA_TYPE IN ('char', 'varchar', 'tinytext', 'text'))
                     OR
                     c.COLUMN_NAME = 'updated_at'
               )
            WHERE t.TABLE_SCHEMA = {$qDbName}
              AND t.TABLE_NAME   LIKE {$qTableLike}
            ORDER BY t.TABLE_NAME
        ";

        return $db->setQuery($sql)->loadColumn() ?? [];
    }

    // =========================================================================
    // Build bảng tra cứu username → user ID
    // =========================================================================

    /**
     * Quét created_by và updated_by (nếu còn là VARCHAR) trong tất cả bảng,
     * thu thập mọi username distinct (bỏ qua NULL và rỗng),
     * rồi tra cứu một lần duy nhất trong #__users.
     *
     * @param  string[] $tables
     * @return array<string, int> Map: username => user ID
     */
    private function buildUserMap(\Joomla\Database\DatabaseInterface $db, array $tables): array
    {
        $allUsernames = [];

        foreach ($tables as $tableName) {
            $cols = $this->getExistingVarcharCols($db, $tableName, ['created_by', 'updated_by']);

            foreach ($cols as $col) {
                $usernames = $db->setQuery(
                    "SELECT DISTINCT `{$col}`
                     FROM `{$tableName}`
                     WHERE `{$col}` IS NOT NULL
                       AND `{$col}` != ''"
                )->loadColumn();

                foreach ($usernames as $username) {
                    $allUsernames[$username] = true;
                }
            }
        }

        if (empty($allUsernames)) {
            return [];
        }

        $usersTable  = $db->replacePrefix('#__users');
        $quotedNames = array_map([$db, 'quote'], array_keys($allUsernames));
        $inList      = implode(', ', $quotedNames);

        $rows = $db->setQuery(
            "SELECT `username`, `id`
             FROM `{$usersTable}`
             WHERE `username` IN ({$inList})"
        )->loadAssocList('username', 'id');

        return $rows ?? [];
    }

    // =========================================================================
    // Migrate một bảng
    // =========================================================================

    /**
     * Thực hiện toàn bộ migration cho một bảng theo thứ tự:
     *
     *   1. created_by  → giữ tên, đổi kiểu VARCHAR→INT, username→ID, NULL→NULL
     *   2. updated_at  → đổi tên thành modified_at (RENAME COLUMN)
     *   3. updated_by  → đổi tên thành modified_by, đổi kiểu VARCHAR→INT, username→ID, NULL→NULL
     *
     * Mỗi bước đều kiểm tra cột có tồn tại trước khi thực hiện (idempotent).
     */
    private function migrateTable(
        \Joomla\Database\DatabaseInterface $db,
        string $tableName,
        array $userMap
    ): void {
        $existingCols = $this->getExistingColumnNames($db, $tableName);

        // --- 1. created_by: đổi kiểu VARCHAR → INT, giữ nguyên tên ---
        if (in_array('created_by', $existingCols, true)
            && $this->isVarcharCol($db, $tableName, 'created_by')
        ) {
            $this->convertUsernameColToInt($db, $tableName, 'created_by', 'created_by', $userMap);
        }

        // --- 2. updated_at: chỉ đổi tên → modified_at ---
        if (in_array('updated_at', $existingCols, true)
            && !in_array('modified_at', $existingCols, true)
        ) {
            $db->setQuery(
                "ALTER TABLE `{$tableName}` RENAME COLUMN `updated_at` TO `modified_at`"
            )->execute();
        }

        // --- 3. updated_by: đổi tên → modified_by, đổi kiểu VARCHAR → INT ---
        if (in_array('updated_by', $existingCols, true)
            && $this->isVarcharCol($db, $tableName, 'updated_by')
        ) {
            $this->convertUsernameColToInt($db, $tableName, 'updated_by', 'modified_by', $userMap);
        }

        Log::add("com_eqa: Migrate bảng `{$tableName}` thành công.", Log::INFO, 'com_eqa');
    }

    /**
     * Chuyển đổi một cột username VARCHAR sang cột user ID INT.
     * Hỗ trợ cả trường hợp đổi tên cột lẫn giữ nguyên tên.
     *
     * Chiến lược (tránh mất dữ liệu, NULL-safe):
     *   Bước 1 — Thêm cột INT tạm ($newCol_mig_tmp) DEFAULT NULL
     *   Bước 2 — Điền user ID vào cột tạm:
     *              - username tìm thấy  → user ID tương ứng
     *              - username không tìm thấy (không NULL, không rỗng) → FALLBACK_USER_ID
     *              - NULL hoặc rỗng     → giữ NULL (cột tạm đã DEFAULT NULL)
     *   Bước 3 — Một lệnh ALTER: DROP cột cũ + RENAME cột tạm → tên mới
     *   Bước 4 — ADD INDEX cho cột mới
     *
     * Dùng cột tạm thay vì MODIFY COLUMN trực tiếp để đảm bảo:
     *   - NULL được bảo toàn hoàn toàn (không bị ép thành 0)
     *   - Tên cột mới ($newCol) có thể khác tên cũ ($oldCol)
     *
     * @param array<string, int> $userMap Map username → user ID
     */
    private function convertUsernameColToInt(
        \Joomla\Database\DatabaseInterface $db,
        string $tableName,
        string $oldCol,
        string $newCol,
        array $userMap
    ): void {
        $tmpCol       = $newCol . '_mig_tmp';
        $existingCols = $this->getExistingColumnNames($db, $tableName);

        // --- Bước 1: Thêm cột INT tạm DEFAULT NULL (idempotent) ---
        if (!in_array($tmpCol, $existingCols, true)) {
            $db->setQuery(
                "ALTER TABLE `{$tableName}`
                 ADD COLUMN `{$tmpCol}` INT(11) DEFAULT NULL"
            )->execute();
        }

        // --- Bước 2a: Điền user ID cho username tìm thấy trong #__users ---
        if (!empty($userMap)) {
            // Nhóm username theo user ID để giảm số câu UPDATE (WHERE IN)
            $idToUsernames = [];
            foreach ($userMap as $username => $userId) {
                $idToUsernames[(int) $userId][] = $username;
            }

            foreach ($idToUsernames as $userId => $usernames) {
                $quotedNames = array_map([$db, 'quote'], $usernames);
                $inList      = implode(', ', $quotedNames);

                $db->setQuery(
                    "UPDATE `{$tableName}`
                     SET `{$tmpCol}` = {$userId}
                     WHERE `{$oldCol}` IN ({$inList})"
                )->execute();
            }
        }

        // --- Bước 2b: Gán FALLBACK_USER_ID cho username không tìm thấy ---
        // Điều kiện: oldCol không NULL và không rỗng, nhưng tmpCol vẫn còn NULL
        // (tức là không khớp bất kỳ username nào đã biết ở bước 2a)
        $db->setQuery(
            "UPDATE `{$tableName}`
             SET `{$tmpCol}` = " . self::FALLBACK_USER_ID . "
             WHERE `{$tmpCol}` IS NULL
               AND `{$oldCol}` IS NOT NULL
               AND `{$oldCol}` != ''"
        )->execute();

        // Lưu ý: Các dòng có oldCol IS NULL hoặc oldCol = '' → tmpCol vẫn NULL → đúng yêu cầu.

        // --- Bước 3: DROP cột cũ + RENAME cột tạm → tên mới (một lệnh ALTER) ---
        $db->setQuery(
            "ALTER TABLE `{$tableName}`
             DROP COLUMN `{$oldCol}`,
             RENAME COLUMN `{$tmpCol}` TO `{$newCol}`"
        )->execute();

        // --- Bước 4: Thêm index cho cột mới ---
        $this->addIndexIfNotExists($db, $tableName, $newCol);
    }

    // =========================================================================
    // Index
    // =========================================================================

    /**
     * Thêm index cho cột nếu chưa có index nào trên cột đó.
     */
    private function addIndexIfNotExists(
        \Joomla\Database\DatabaseInterface $db,
        string $tableName,
        string $colName
    ): void {
        $dbName = $db->setQuery('SELECT DATABASE()')->loadResult();

        $exists = (int) $db->setQuery(
            "SELECT COUNT(*)
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = " . $db->quote($dbName) . "
               AND TABLE_NAME   = " . $db->quote($tableName) . "
               AND COLUMN_NAME  = " . $db->quote($colName)
        )->loadResult();

        if ($exists === 0) {
            $indexName = 'idx_' . $colName;
            $db->setQuery(
                "ALTER TABLE `{$tableName}` ADD INDEX `{$indexName}` (`{$colName}`)"
            )->execute();
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Lấy danh sách tên tất cả các cột hiện có của bảng.
     *
     * @return string[]
     */
    private function getExistingColumnNames(\Joomla\Database\DatabaseInterface $db, string $tableName): array
    {
        $columns = $db->setQuery("SHOW COLUMNS FROM `{$tableName}`")->loadAssocList('Field');
        return array_keys($columns ?? []);
    }

    /**
     * Lọc ra các cột trong $candidates thực sự tồn tại trong bảng với kiểu VARCHAR.
     *
     * @param  string[] $candidates Danh sách tên cột cần kiểm tra
     * @return string[]
     */
    private function getExistingVarcharCols(
        \Joomla\Database\DatabaseInterface $db,
        string $tableName,
        array $candidates
    ): array {
        $dbName   = $db->setQuery('SELECT DATABASE()')->loadResult();
        $colNames = array_map([$db, 'quote'], $candidates);
        $inList   = implode(', ', $colNames);

        $rows = $db->setQuery(
            "SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = " . $db->quote($dbName) . "
               AND TABLE_NAME   = " . $db->quote($tableName) . "
               AND COLUMN_NAME  IN ({$inList})
               AND DATA_TYPE    IN ('char', 'varchar', 'tinytext', 'text')"
        )->loadColumn();

        return $rows ?? [];
    }

    /**
     * Kiểm tra một cột cụ thể có kiểu VARCHAR (hay tương đương) không.
     */
    private function isVarcharCol(
        \Joomla\Database\DatabaseInterface $db,
        string $tableName,
        string $colName
    ): bool {
        return !empty($this->getExistingVarcharCols($db, $tableName, [$colName]));
    }

    /**
     * Đọc version đang cài của component từ #__extensions.manifest_cache.
     * Trả về '0.0.0' nếu không tìm thấy (cài lần đầu).
     */
    private function getInstalledVersion(): string
    {
        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->select('`manifest_cache`')
            ->from('`#__extensions`')
            ->where('`element` = ' . $db->quote('com_eqa'))
            ->where('`type`    = ' . $db->quote('component'));

        $cache = $db->setQuery($query)->loadResult();
        $data  = json_decode($cache ?? '{}', true);

        return $data['version'] ?? '0.0.0';
    }

    // =========================================================================
    // Logging helpers
    // =========================================================================

    private function logInfo(string $msg): void
    {
        Factory::getApplication()->enqueueMessage("com_eqa: {$msg}", 'info');
        Log::add("com_eqa: {$msg}", Log::INFO, 'com_eqa');
    }

    private function logWarning(string $msg): void
    {
        Factory::getApplication()->enqueueMessage("com_eqa: {$msg}", 'warning');
        Log::add("com_eqa: {$msg}", Log::WARNING, 'com_eqa');
    }

    private function logError(string $msg): void
    {
        Factory::getApplication()->enqueueMessage("com_eqa: {$msg}", 'error');
        Log::add("com_eqa: {$msg}", Log::ERROR, 'com_eqa');
    }
}
