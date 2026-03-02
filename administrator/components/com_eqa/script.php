<?php
/**
 * @package     Com_Eqa
 * @subpackage  Installation Script
 *
 * Thứ tự thực thi của Joomla là: preflight() → copy file → update() → chạy SQL → postflight()
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
 * Migration v2.0.1
 * ----------------
 * Các thay đổi schema so với v2.0.0:
 *
 *   #__eqa_subjects : Thêm `allowed_rooms` TEXT NULL — danh sách phòng được phép tổ chức thi
 *   #__eqa_exams    : Xóa `statistic`
 *                     Thêm `code` VARCHAR(50) NOT NULL — mã môn thi (duy nhất trong kỳ thi)
 *                     Thêm `allowed_rooms` TEXT NULL — ghi đè allowed_rooms của subject
 *                     Thêm UNIQUE INDEX (examseason_id, code)
 *
 * Migration v2.0.2
 * ----------------
 * Các thay đổi schema so với v2.0.1:
 *
 *   #__eqa_secondattempts : Xóa cột `payment_required` BOOLEAN
 *                           Thêm cột `payment_amount` DOUBLE NOT NULL DEFAULT 0
 *
 * Quy tắc chuyển đổi dữ liệu:
 *   - payment_required = 0 (FALSE) → payment_amount = 0.0
 *   - payment_required = 1 (TRUE)  → payment_amount = calculateFee(feeMode, feeRate, credits)
 *     trong đó credits lấy từ #__eqa_subjects qua JOIN #__eqa_exams
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
     * Version đang cài trước khi update, được đọc trong preflight() khi
     * manifest_cache vẫn còn chứa version cũ.
     *
     * postflight() dùng giá trị này để quyết định migration nào cần chạy,
     * vì lúc postflight() thực thi Joomla đã ghi version mới vào manifest_cache
     * khiến getInstalledVersion() luôn trả về version mới.
     */
    private string $previousVersion = '0.0.0';

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
            // Đọc version cũ TRƯỚC KHI Joomla copy file mới.
            // Đây là thời điểm duy nhất manifest_cache còn chứa version cũ.
            // postflight() sẽ dùng $this->previousVersion để quyết định
            // migration nào cần chạy.
            $this->previousVersion = $this->getInstalledVersion();
            $this->logInfo("Phiên bản hiện tại (trước khi update): {$this->previousVersion}");

            $this->clearComponentDirs();
        }

        return true;
    }

    /**
     * Chạy sau khi Joomla copy file mới lên server NHƯNG TRƯỚC KHI chạy SQL update.
     *
     * Chỉ dùng cho các migration không phụ thuộc vào SQL update (ví dụ: migration 2.0.0
     * thực hiện bằng PHP thuần, không cần file .sql chạy trước).
     *
     * LƯU Ý: Các migration cần SQL chạy trước (ví dụ: tạo cột mới rồi mới populate dữ liệu)
     * phải được đặt trong postflight(), không phải ở đây.
     */
    public function update($parent): bool
    {
        // $this->previousVersion đã được đọc trong preflight() → dùng lại ở đây.
        // update() chạy trước khi SQL thực thi nên getInstalledVersion() cũng
        // còn đúng tại thời điểm này, nhưng dùng previousVersion cho nhất quán.
        if (version_compare($this->previousVersion, '2.0.0', '<')) {
            if (!$this->runMigration200()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Chạy SAU KHI Joomla đã thực thi xong toàn bộ file SQL update.
     *
     * Đây là nơi đúng để thực hiện các migration cần dữ liệu/cấu trúc từ SQL update:
     * ví dụ populate dữ liệu vào cột vừa được tạo bởi file .sql.
     *
     * @param  string $type   Loại action: 'install' | 'update' | 'discover_install'
     * @param  object $parent Installer object
     * @return bool
     */
    public function postflight(string $type, $parent): bool
    {
        // Chỉ chạy khi là update (không chạy khi install lần đầu)
        if ($type !== 'update') {
            return true;
        }

        // $this->previousVersion được đọc trong preflight() khi manifest_cache
        // vẫn còn version cũ. Dùng nó để gọi đúng migration cần thiết.
        //
        // Lưu ý: Không dùng getInstalledVersion() ở đây vì khi postflight() chạy,
        // Joomla đã ghi version MỚI vào manifest_cache → luôn trả về version mới.
        if (version_compare($this->previousVersion, '2.0.1', '<')) {
            if (!$this->runMigration201()) {
                return false;
            }
        }

        if (version_compare($this->previousVersion, '2.0.2', '<')) {
            if (!$this->runMigration202()) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // Dọn dẹp thư mục cũ
    // =========================================================================

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
    // Migration 2.0.1
    // =========================================================================

    /**
     * Thực hiện migration từ v2.0.0 lên v2.0.1.
     *
     * Các thay đổi schema (SQL thuần đã chạy qua sql/updates/mysql/2.0.1.sql):
     *   - #__eqa_subjects : đã có cột `allowed_rooms` TEXT NULL
     *   - #__eqa_exams    : đã xóa `statistic`, đã có cột `code` VARCHAR(50) DEFAULT '',
     *                       đã có cột `allowed_rooms` TEXT NULL
     *
     * Nhiệm vụ của method này (không thể làm bằng SQL thuần trong schema update):
     *   1. Populate cột `code` của #__eqa_exams từ mã môn học tương ứng.
     *   2. Xử lý các môn thi mồ côi (subject_id không hợp lệ) → code = 'EXAM_{id}'.
     *   3. Giải quyết xung đột unique (examseason_id, code) bằng cách thêm hậu tố _2, _3, ...
     *   4. Enforce NOT NULL (MODIFY COLUMN bỏ DEFAULT '').
     *   5. Thêm UNIQUE INDEX (examseason_id, code).
     *
     * Idempotent: Kiểm tra trạng thái cột/constraint trước khi thực hiện từng bước.
     *
     * @return bool true nếu thành công (kể cả khi có cảnh báo), false nếu lỗi nghiêm trọng.
     */
    private function runMigration201(): bool
    {
        $db = Factory::getDbo();

        try {
            $this->logInfo('Migration 2.0.1: Bắt đầu...');

            $examsTable    = $db->replacePrefix('#__eqa_exams');
            $subjectsTable = $db->replacePrefix('#__eqa_subjects');

            // =================================================================
            // Bước 1: Kiểm tra cột `code` có tồn tại không
            // =================================================================
            $existingCols = $this->getExistingColumnNames($db, $examsTable);

            if (!in_array('code', $existingCols, true)) {
                $this->logError(
                    'Migration 2.0.1: Cột `code` không tồn tại trong bảng `'
                    . $examsTable . '`. '
                    . 'Hãy đảm bảo file sql/updates/mysql/2.0.1.sql đã được thực thi.'
                );
                return false;
            }

            // =================================================================
            // Bước 2: Populate cột `code` cho các bản ghi còn rỗng
            // =================================================================
            $this->logInfo('Migration 2.0.1: Đang populate cột `code` cho #__eqa_exams...');

            $rows = $db->setQuery(
                "SELECT e.id            AS exam_id,
                        e.examseason_id,
                        s.code          AS subject_code
                 FROM `{$examsTable}` e
                 LEFT JOIN `{$subjectsTable}` s ON s.id = e.subject_id
                 WHERE e.code = ''
                 ORDER BY e.examseason_id, e.id"
            )->loadObjectList();

            if (empty($rows)) {
                $this->logInfo('Migration 2.0.1: Không có bản ghi nào cần populate `code`.');
            } else {
                $this->logInfo(
                    'Migration 2.0.1: Tìm thấy ' . count($rows) . ' môn thi cần populate `code`.'
                );

                // Xây dựng bảng tra cứu các code đã tồn tại theo examseason_id
                // (bao gồm cả các bản ghi đã có code, để tránh trùng lặp)
                $existingCodes = $this->loadExistingExamCodes($db, $examsTable);

                $countPopulated    = 0;
                $countOrphaned     = 0;
                $countDeduplicated = 0;

                foreach ($rows as $row) {
                    $examId      = (int) $row->exam_id;
                    $seasonId    = (int) $row->examseason_id;
                    $subjectCode = $row->subject_code; // NULL nếu subject_id không hợp lệ

                    // Xác định code gốc
                    if (empty($subjectCode)) {
                        // Môn thi mồ côi: subject_id không còn hợp lệ trong DB
                        $baseCode = 'EXAM_' . $examId;
                        $countOrphaned++;
                        $this->logWarning(
                            "Migration 2.0.1: Môn thi id={$examId} không tìm thấy môn học "
                            . "tương ứng. Đặt code='{$baseCode}'."
                        );
                    } else {
                        $baseCode = $subjectCode;
                    }

                    // Giải quyết xung đột unique (examseason_id, code)
                    $finalCode = $this->resolveUniqueCode($baseCode, $seasonId, $existingCodes);

                    if ($finalCode !== $baseCode) {
                        $countDeduplicated++;
                        $this->logWarning(
                            "Migration 2.0.1: Xung đột code '{$baseCode}' trong kỳ thi "
                            . "id={$seasonId}. Môn thi id={$examId} được đổi thành '{$finalCode}'."
                        );
                    }

                    // Ghi vào database
                    $db->setQuery(
                        "UPDATE `{$examsTable}`
                         SET `code` = " . $db->quote($finalCode) . "
                         WHERE `id` = {$examId}"
                    )->execute();

                    // Đánh dấu code này đã được dùng trong bảng tra cứu nội bộ
                    $existingCodes[$seasonId][$finalCode] = true;
                    $countPopulated++;
                }

                $this->logInfo(
                    "Migration 2.0.1: Đã populate {$countPopulated} bản ghi"
                    . ($countOrphaned > 0 ? ", {$countOrphaned} môn thi mồ côi" : '')
                    . ($countDeduplicated > 0 ? ", {$countDeduplicated} code bị đổi tên do trùng lặp" : '')
                    . '.'
                );
            }

            // =================================================================
            // Bước 3: Enforce NOT NULL cho cột `code`
            // Kiểm tra chắc chắn không còn bản ghi nào rỗng/NULL trước khi ALTER
            // =================================================================
            $this->logInfo('Migration 2.0.1: Kiểm tra và enforce NOT NULL cho cột `code`...');

            $emptyCount = (int) $db->setQuery(
                "SELECT COUNT(*)
                 FROM `{$examsTable}`
                 WHERE `code` = '' OR `code` IS NULL"
            )->loadResult();

            if ($emptyCount > 0) {
                $this->logError(
                    "Migration 2.0.1: Vẫn còn {$emptyCount} bản ghi có code rỗng/NULL. "
                    . 'Không thể enforce NOT NULL. Kiểm tra dữ liệu và chạy lại.'
                );
                return false;
            }

            // Kiểm tra cột hiện tại: nếu đã là NOT NULL và không có DEFAULT thì bỏ qua
            $colInfo = $db->setQuery(
                "SELECT COLUMN_DEFAULT, IS_NULLABLE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = " . $db->quote($examsTable) . "
                   AND COLUMN_NAME  = 'code'"
            )->loadObject();

            $needsModify = ($colInfo === null)
                || ($colInfo->IS_NULLABLE === 'YES')
                || ($colInfo->COLUMN_DEFAULT !== null);

            if ($needsModify) {
                $db->setQuery(
                    "ALTER TABLE `{$examsTable}`
                     MODIFY COLUMN `code` VARCHAR(50) NOT NULL
                         COMMENT 'Mã môn thi (copy từ mã môn học); bắt buộc; duy nhất trong một kỳ thi'"
                )->execute();
                $this->logInfo('Migration 2.0.1: Đã enforce NOT NULL cho cột `code`.');
            } else {
                $this->logInfo('Migration 2.0.1: Cột `code` đã là NOT NULL không DEFAULT, bỏ qua MODIFY.');
            }

            // =================================================================
            // Bước 4: Thêm UNIQUE INDEX (examseason_id, code)
            // =================================================================
            $this->logInfo('Migration 2.0.1: Kiểm tra và thêm UNIQUE INDEX (examseason_id, code)...');

            $constraintExists = $this->uniqueConstraintExists(
                $db,
                $examsTable,
                ['examseason_id', 'code']
            );

            if (!$constraintExists) {
                $db->setQuery(
                    "ALTER TABLE `{$examsTable}`
                     ADD UNIQUE INDEX `uq_eqa_exams_season_code` (`examseason_id`, `code`)"
                )->execute();
                $this->logInfo('Migration 2.0.1: Đã thêm UNIQUE INDEX `uq_eqa_exams_season_code`.');
            } else {
                $this->logInfo('Migration 2.0.1: UNIQUE INDEX đã tồn tại, bỏ qua.');
            }

            $this->logInfo('Migration 2.0.1: Hoàn tất thành công!');
            return true;

        } catch (\Throwable $e) {
            $msg = 'Migration 2.0.1 thất bại nghiêm trọng: ' . $e->getMessage();
            Log::add("com_eqa: {$msg}", Log::ERROR, 'com_eqa');
            $this->logError($msg);
            return false;
        }
    }

    /**
     * Tải toàn bộ các code đã tồn tại trong #__eqa_exams,
     * nhóm theo examseason_id, để hỗ trợ kiểm tra xung đột khi populate.
     *
     * Chỉ lấy các bản ghi có code không rỗng (đã được populate từ trước).
     *
     * @param  string $examsTable Tên bảng đầy đủ (đã replace prefix)
     * @return array<int, array<string, true>> Map: seasonId → [code => true, ...]
     */
    private function loadExistingExamCodes(
        \Joomla\Database\DatabaseInterface $db,
        string $examsTable
    ): array {
        $rows = $db->setQuery(
            "SELECT `examseason_id`, `code`
             FROM `{$examsTable}`
             WHERE `code` != '' AND `code` IS NOT NULL"
        )->loadObjectList();

        $map = [];
        foreach ($rows as $row) {
            $seasonId              = (int) $row->examseason_id;
            $map[$seasonId][$row->code] = true;
        }

        return $map;
    }

    /**
     * Giải quyết xung đột code trong phạm vi một kỳ thi (examseason_id).
     *
     * Thuật toán: Thử $baseCode trước; nếu đã tồn tại thì thử {baseCode}_2,
     * {baseCode}_3, ... cho đến khi tìm được code chưa xuất hiện.
     * Giới hạn an toàn tại suffix 9999 để tránh vòng lặp vô hạn.
     *
     * @param  string                          $baseCode      Code gốc (từ mã môn học hoặc fallback)
     * @param  int                             $seasonId      ID kỳ thi
     * @param  array<int, array<string, true>> $existingCodes Bảng tra cứu hiện tại (chỉ đọc)
     * @return string Code cuối cùng (đảm bảo chưa tồn tại trong $existingCodes)
     */
    private function resolveUniqueCode(
        string $baseCode,
        int $seasonId,
        array $existingCodes
    ): string {
        $candidate = $baseCode;
        $suffix    = 2;

        while (isset($existingCodes[$seasonId][$candidate])) {
            if ($suffix > 9999) {
                // Fallback cực kỳ hiếm gặp: thêm chuỗi ngẫu nhiên
                $candidate = $baseCode . '_' . substr(uniqid('', false), -6);
                break;
            }

            $candidate = $baseCode . '_' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Kiểm tra xem đã tồn tại UNIQUE index bao phủ chính xác tập cột $columns chưa.
     *
     * So sánh theo tập hợp (không phân biệt thứ tự cột trong index).
     *
     * @param  string   $tableName Tên bảng đầy đủ (đã replace prefix)
     * @param  string[] $columns   Danh sách tên cột cần kiểm tra
     * @return bool
     */
    private function uniqueConstraintExists(
        \Joomla\Database\DatabaseInterface $db,
        string $tableName,
        array $columns
    ): bool {
        $dbName = $db->setQuery('SELECT DATABASE()')->loadResult();

        $rows = $db->setQuery(
            "SELECT INDEX_NAME, COLUMN_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = " . $db->quote($dbName) . "
               AND TABLE_NAME   = " . $db->quote($tableName) . "
               AND NON_UNIQUE   = 0
               AND INDEX_NAME  != 'PRIMARY'"
        )->loadObjectList();

        // Nhóm các cột theo tên index
        $indexMap = [];
        foreach ($rows as $row) {
            $indexMap[$row->INDEX_NAME][] = $row->COLUMN_NAME;
        }

        // So sánh tập cột (không phân biệt thứ tự)
        $targetSet = $columns;
        sort($targetSet);

        foreach ($indexMap as $indexCols) {
            sort($indexCols);
            if ($indexCols === $targetSet) {
                return true;
            }
        }

        return false;
    }

    // =========================================================================
    // Migration 2.0.2
    // =========================================================================

    /**
     * Thực hiện migration từ v2.0.1 lên v2.0.2.
     *
     * Thay đổi schema (cột `payment_amount` đã được tạo bởi 2.0.2.sql):
     *   #__eqa_secondattempts:
     *     - Cột `payment_amount` DOUBLE NOT NULL DEFAULT 0 (đã tồn tại sau khi SQL chạy)
     *     - Cột `payment_required` BOOLEAN (vẫn còn, sẽ bị xóa ở cuối method này)
     *
     * Nhiệm vụ của method này:
     *   1. Kiểm tra idempotent: nếu `payment_required` không còn → đã migrate, bỏ qua.
     *   2. Kiểm tra `payment_amount` đã tồn tại (do SQL chạy trước postflight).
     *   3. Đọc cấu hình fee mode và fee rate từ component params.
     *   4. Đọc toàn bộ bản ghi có payment_required = 1, kèm số tín chỉ môn học.
     *   5. Tính payment_amount theo công thức:
     *        - FeeMode::Free (0)      → 0.0
     *        - FeeMode::PerExam (10)  → feeRate
     *        - FeeMode::PerCredit (20)→ feeRate * max(1, credits)
     *   6. Batch UPDATE theo nhóm credits đồng nhất (tối ưu số lần truy vấn).
     *   7. Xóa cột `payment_required`.
     *
     * Idempotent: Kiểm tra sự tồn tại của cột `payment_required` ở bước 1.
     *
     * @return bool true nếu thành công, false nếu lỗi nghiêm trọng.
     */
    private function runMigration202(): bool
    {
        $db = Factory::getDbo();

        try {
            $this->logInfo('Migration 2.0.2: Bắt đầu...');

            $saTable       = $db->replacePrefix('#__eqa_secondattempts');
            $examsTable    = $db->replacePrefix('#__eqa_exams');
            $subjectsTable = $db->replacePrefix('#__eqa_subjects');

            // =================================================================
            // Bước 1: Kiểm tra idempotent
            // Nếu `payment_required` không còn → migration đã chạy rồi, bỏ qua.
            // =================================================================
            $existingCols = $this->getExistingColumnNames($db, $saTable);

            if (!in_array('payment_required', $existingCols, true)) {
                $this->logInfo(
                    'Migration 2.0.2: Cột `payment_required` không tồn tại → '
                    . 'migration đã được thực hiện trước đó, bỏ qua.'
                );
                return true;
            }

            // =================================================================
            // Bước 2: Kiểm tra `payment_amount` đã được tạo bởi file SQL chưa
            // =================================================================
            if (!in_array('payment_amount', $existingCols, true)) {
                $this->logError(
                    'Migration 2.0.2: Cột `payment_amount` không tồn tại trong bảng `'
                    . $saTable . '`. '
                    . 'Hãy đảm bảo file sql/updates/mysql/2.0.2.sql đã được thực thi.'
                );
                return false;
            }

            // =================================================================
            // Bước 3: Đọc cấu hình fee mode và fee rate
            // =================================================================
            [$feeMode, $feeRate] = $this->loadSecondAttemptFeeConfig($db);

            $this->logInfo(
                sprintf(
                    'Migration 2.0.2: fee_mode=%d, fee_rate=%.2f',
                    $feeMode,
                    $feeRate
                )
            );

            // =================================================================
            // Bước 4: Xử lý các bản ghi có payment_required = 1
            // =================================================================

            // Trường hợp đặc biệt: FeeMode::Free (0) → tất cả payment_amount = 0,
            // không cần đọc dữ liệu chi tiết, chỉ cần đảm bảo cột = 0 (đã là DEFAULT 0).
            if ($feeMode === 0) {
                $this->logInfo(
                    'Migration 2.0.2: fee_mode = Free → tất cả payment_amount giữ nguyên = 0.'
                );
            } else {
                // Đọc các bản ghi cần tính phí, kèm credits của môn học
                $rows = $db->setQuery(
                    "SELECT sa.id,
                            COALESCE(su.credits, 0) AS credits
                     FROM `{$saTable}` sa
                     LEFT JOIN `{$examsTable}` ex ON ex.id = sa.last_exam_id
                     LEFT JOIN `{$subjectsTable}` su ON su.id = ex.subject_id
                     WHERE sa.payment_required = 1"
                )->loadObjectList();

                if (empty($rows)) {
                    $this->logInfo(
                        'Migration 2.0.2: Không có bản ghi nào có payment_required = 1.'
                    );
                } else {
                    $this->logInfo(
                        'Migration 2.0.2: Tìm thấy ' . count($rows)
                        . ' bản ghi có payment_required = 1, đang tính payment_amount...'
                    );

                    // =============================================================
                    // Bước 5: Tính payment_amount và nhóm theo giá trị đồng nhất
                    // để batch UPDATE (giảm số lần truy vấn DB)
                    // =============================================================

                    // Map: payment_amount (float, dùng string key) → [id, id, ...]
                    $amountToIds = [];

                    foreach ($rows as $row) {
                        $credits       = (int) $row->credits;
                        $paymentAmount = $this->calculateSecondAttemptFee($feeMode, $feeRate, $credits);

                        // Dùng number_format để tạo key nhất quán, tránh lỗi float precision
                        $amountKey = number_format($paymentAmount, 4, '.', '');
                        $amountToIds[$amountKey][] = (int) $row->id;
                    }

                    // =============================================================
                    // Bước 6: Batch UPDATE theo nhóm payment_amount đồng nhất
                    // =============================================================
                    $totalUpdated = 0;

                    foreach ($amountToIds as $amountKey => $ids) {
                        $amount  = (float) $amountKey;
                        $idList  = implode(', ', $ids);

                        $db->setQuery(
                            "UPDATE `{$saTable}`
                             SET `payment_amount` = {$amount}
                             WHERE `id` IN ({$idList})"
                        )->execute();

                        $totalUpdated += count($ids);
                    }

                    $this->logInfo(
                        "Migration 2.0.2: Đã cập nhật payment_amount cho {$totalUpdated} bản ghi "
                        . 'trong ' . count($amountToIds) . ' nhóm phí.'
                    );
                }
            }

            // =================================================================
            // Bước 7: Xóa cột `payment_required`
            // =================================================================
            $this->logInfo('Migration 2.0.2: Đang xóa cột `payment_required`...');

            $db->setQuery(
                "ALTER TABLE `{$saTable}` DROP COLUMN `payment_required`"
            )->execute();

            $this->logInfo('Migration 2.0.2: Đã xóa cột `payment_required`.');
            $this->logInfo('Migration 2.0.2: Hoàn tất thành công!');
            return true;

        } catch (\Throwable $e) {
            $msg = 'Migration 2.0.2 thất bại nghiêm trọng: ' . $e->getMessage();
            Log::add("com_eqa: {$msg}", Log::ERROR, 'com_eqa');
            $this->logError($msg);
            return false;
        }
    }

    /**
     * Đọc cấu hình second attempt fee từ #__extensions (component params).
     *
     * Trả về mảng 2 phần tử: [feeMode (int), feeRate (float)].
     * Giá trị mặc định: PerExam (10), 90000 VNĐ — khớp với ConfigHelper/ConfigService.
     *
     * Lưu ý: Không dùng ConfigHelper hay ConfigService trực tiếp trong script installer
     * vì các class đó phụ thuộc vào autoloader của component, chưa chắc đã sẵn sàng
     * khi postflight() chạy. Thay vào đó, đọc thẳng từ #__extensions.
     *
     * @return array{int, float} [feeMode, feeRate]
     */
    private function loadSecondAttemptFeeConfig(\Joomla\Database\DatabaseInterface $db): array
    {
        $extensionsTable = $db->replacePrefix('#__extensions');

        $paramsJson = $db->setQuery(
            "SELECT `params`
             FROM `{$extensionsTable}`
             WHERE `element` = 'com_eqa'
               AND `type`    = 'component'
             LIMIT 1"
        )->loadResult();

        $params   = json_decode($paramsJson ?? '{}', true);
        $feeMode  = (int) ($params['params']['second_attempt_fee_mode'] ?? 10); // default: PerExam
        $feeRate  = (float) ($params['params']['second_attempt_fee_rate'] ?? 90000.0);

        return [$feeMode, $feeRate];
    }

    /**
     * Tính số tiền lệ phí thi lần hai cho một bản ghi.
     *
     * Ánh xạ fee mode (int) với enum FeeMode:
     *   0  = Free      → 0.0
     *   10 = PerExam   → feeRate
     *   20 = PerCredit → feeRate * max(1, credits)
     *
     * Dùng int thay vì enum FeeMode vì script.php không load autoloader component.
     *
     * @param  int   $feeMode  Giá trị int của FeeMode enum (0 | 10 | 20).
     * @param  float $feeRate  Mức phí cơ bản (VNĐ/môn hoặc VNĐ/tín chỉ).
     * @param  int   $credits  Số tín chỉ của môn học (0 nếu không xác định).
     * @return float           Số tiền lệ phí (VNĐ).
     */
    private function calculateSecondAttemptFee(int $feeMode, float $feeRate, int $credits): float
    {
        return match ($feeMode) {
            0  => 0.0,                            // FeeMode::Free
            10 => $feeRate,                       // FeeMode::PerExam
            20 => $feeRate * max(1, $credits),    // FeeMode::PerCredit
            default => $feeRate,                  // Fallback an toàn
        };
    }

    // =========================================================================
    // Detect bảng qua INFORMATION_SCHEMA (dùng cho migration 2.0.0)
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

        $rows = $db->setQuery(
            "SELECT DISTINCT c.TABLE_NAME
             FROM information_schema.COLUMNS c
             WHERE c.TABLE_SCHEMA = {$qDbName}
               AND c.TABLE_NAME LIKE {$qTableLike}
               AND (
                   (c.COLUMN_NAME = 'created_by' AND c.DATA_TYPE IN ('char','varchar','tinytext','text'))
                   OR (c.COLUMN_NAME = 'updated_at' AND c.DATA_TYPE IN ('datetime','timestamp','date'))
                   OR (c.COLUMN_NAME = 'updated_by' AND c.DATA_TYPE IN ('char','varchar','tinytext','text'))
               )
             ORDER BY c.TABLE_NAME"
        )->loadColumn();

        return $rows ?? [];
    }

    // =========================================================================
    // Build bảng tra cứu username → user ID (dùng cho migration 2.0.0)
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
    // Migrate một bảng (dùng cho migration 2.0.0)
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
        $tmpCol = $newCol . '_mig_tmp';
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
        $db->setQuery(
            "UPDATE `{$tableName}`
             SET `{$tmpCol}` = " . self::FALLBACK_USER_ID . "
             WHERE `{$tmpCol}` IS NULL
               AND `{$oldCol}` IS NOT NULL
               AND `{$oldCol}` != ''"
        )->execute();

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
