<?php
namespace Kma\Component\Eqa\Administrator\Controller;
require_once JPATH_ROOT.'/vendor/autoload.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Database\ParameterType;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Extension\EqaComponent;
use Kma\Library\Kma\Controller\FormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Service\LogService;

defined('_JEXEC') or die();
class FixerController extends FormController
{
	public function fix()
	{
		$this->runMigration206();
	}

	// =========================================================================
	// Cấu hình
	// =========================================================================

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



	// =========================================================================
	// Migration 2.0.6
	// =========================================================================

	/**
	 * Thực hiện migration từ v2.0.5 lên v2.0.6.
	 *
	 * Lưu ý: sql/updates/mysql/2.0.6.sql được giữ trống có chủ ý.
	 * Tất cả thay đổi schema được thực hiện tại đây vì cần kiểm tra
	 * trạng thái thực tế của CSDL trước mỗi bước (idempotent).
	 *
	 * Nhiệm vụ:
	 *   1. Sửa tên cột checkout trên 14 bảng gốc:
	 *      - Nếu có `check_out` (tên sai, thêm bởi v1.0.3) → RENAME → `checked_out`
	 *      - Nếu có `checked_out` (đã đúng) → bỏ qua
	 *      - Nếu không có cả hai → ADD `checked_out` + `checked_out_time`
	 *   2. Sửa #__eqa_regradings:
	 *      - Nếu có `requested_at` → RENAME → `created_at`
	 *      - Nếu không có `created_at` và không có `requested_at` → ADD `created_at`
	 *      - DROP `requested_by` nếu còn tồn tại
	 *   3. Sửa #__eqa_gradecorrections: tương tự nhiệm vụ 2
	 *   4. Bổ sung `updated_at` / `updated_by` vào #__eqa_exam_learner nếu chưa có
	 *   5. Thêm UNSIGNED cho tất cả cột kiểu số nguyên chưa có UNSIGNED
	 *   6. Bổ sung surrogate key `id` cho 3 junction table chưa có:
	 *      #__eqa_cohort_learner, #__eqa_exam_learner, #__eqa_papers
	 *
	 * @return bool
	 */
	private function runMigration206(): bool
	{
		$db     = Factory::getDbo();
		$dbName = $db->setQuery('SELECT DATABASE()')->loadResult();

		try {
			$this->logInfo('Migration 2.0.6: Bắt đầu...');

			// =================================================================
			// Nhiệm vụ 1: Sửa check_out → checked_out trên 14 bảng gốc
			// =================================================================
			$checkoutTables = [
				'#__eqa_buildings', '#__eqa_rooms', '#__eqa_units',
				'#__eqa_employees', '#__eqa_specialities', '#__eqa_programs',
				'#__eqa_courses', '#__eqa_groups', '#__eqa_learners',
				'#__eqa_subjects', '#__eqa_classes', '#__eqa_examseasons',
				'#__eqa_examsessions', '#__eqa_exams',
			];

			foreach ($checkoutTables as $jTable) {
				$table = $db->replacePrefix($jTable);
				$cols  = $this->getExistingColumnNames($db, $table);

				if (in_array('checked_out', $cols, true)) {
					// Đã đúng, bỏ qua
					$this->logInfo("Migration 2.0.6: `{$table}`.`checked_out` đã tồn tại, bỏ qua.");

				} elseif (in_array('check_out', $cols, true)) {
					// Tên sai, đổi tên
					$db->setQuery(
						"ALTER TABLE `{$table}`
						 CHANGE `check_out`      `checked_out`      INT          DEFAULT NULL,
						 CHANGE `check_out_time` `checked_out_time` DATETIME     DEFAULT NULL"
					)->execute();
					$this->logInfo("Migration 2.0.6: Đã đổi tên `check_out` → `checked_out` trong `{$table}`.");

				} else {
					// Không có cột nào, thêm mới
					$db->setQuery(
						"ALTER TABLE `{$table}`
						 ADD COLUMN `checked_out`      INT      DEFAULT NULL     AFTER `modified_by`,
						 ADD COLUMN `checked_out_time` DATETIME DEFAULT NULL     AFTER `checked_out`"
					)->execute();
					$this->logInfo("Migration 2.0.6: Đã ADD `checked_out` vào `{$table}`.");
				}
			}

			// =================================================================
			// Nhiệm vụ 2: Sửa #__eqa_regradings
			// =================================================================
			$regradTable = $db->replacePrefix('#__eqa_regradings');
			$this->fixRequestedAtColumn($db, $dbName, $regradTable, 'status');

			// =================================================================
			// Nhiệm vụ 3: Sửa #__eqa_gradecorrections
			// =================================================================
			$gradecorrTable = $db->replacePrefix('#__eqa_gradecorrections');
			$this->fixRequestedAtColumn($db, $dbName, $gradecorrTable, 'status');

			// =================================================================
			// Nhiệm vụ 4: Bổ sung updated_at / updated_by vào #__eqa_exam_learner
			// =================================================================
			$examLearnerTable = $db->replacePrefix('#__eqa_exam_learner');
			$elCols = $this->getExistingColumnNames($db, $examLearnerTable);

			if (!in_array('updated_at', $elCols, true)) {
				$db->setQuery(
					"ALTER TABLE `{$examLearnerTable}`
					 ADD COLUMN `updated_at` DATETIME NULL COMMENT 'Dấu thời gian cập nhật (kênh ngoài)' AFTER `modified_by`"
				)->execute();
				$this->logInfo("Migration 2.0.6: Đã ADD `updated_at` vào `{$examLearnerTable}`.");
			}
			if (!in_array('updated_by', $elCols, true)) {
				$db->setQuery(
					"ALTER TABLE `{$examLearnerTable}`
					 ADD COLUMN `updated_by` INT NULL COMMENT 'Người cập nhật (kênh ngoài)' AFTER `updated_at`"
				)->execute();
				$this->logInfo("Migration 2.0.6: Đã ADD `updated_by` vào `{$examLearnerTable}`.");
			}

			// =================================================================
			// Nhiệm vụ 5: Thêm UNSIGNED cho tất cả cột số nguyên
			//
			// SET FOREIGN_KEY_CHECKS = 0 không đủ với ALTER TABLE MODIFY COLUMN
			// trên MySQL/MariaDB — engine vẫn validate kiểu FK khi MODIFY.
			// Giải pháp: DROP tất cả FK của component, MODIFY tất cả cột,
			// rồi ADD lại tất cả FK.
			// =================================================================
			$this->logInfo('Migration 2.0.6: Thêm UNSIGNED cho các cột số nguyên...');

			$prefix      = $db->getPrefix();
			$tablePrefix = $prefix . 'eqa\_%';

			// --- 5a. Thu thập tất cả FK của component ---
			$allFks = $db->setQuery(
				"SELECT kcu.TABLE_NAME, kcu.CONSTRAINT_NAME,
				        kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME,
				        kcu.REFERENCED_COLUMN_NAME,
				        rc.DELETE_RULE, rc.UPDATE_RULE
				 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
				 JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
				      ON  rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
				      AND rc.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
				 WHERE kcu.TABLE_SCHEMA            = " . $db->quote($dbName) . "
				   AND kcu.TABLE_NAME          LIKE " . $db->quote($tablePrefix) . "
				   AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
				 ORDER BY kcu.TABLE_NAME, kcu.CONSTRAINT_NAME"
			)->loadAssocList();

			// --- 5b. DROP tất cả FK ---
			// Nhóm theo bảng để gộp thành 1 ALTER TABLE / bảng
			$fksByTable = [];
			foreach ($allFks as $fk) {
				$fksByTable[$fk['TABLE_NAME']][] = $fk['CONSTRAINT_NAME'];
			}

			foreach ($fksByTable as $tbl => $constraintNames) {
				$drops = implode(', ',
					array_map(fn($n) => "DROP FOREIGN KEY `{$n}`", array_unique($constraintNames))
				);
				try {
					$db->setQuery("ALTER TABLE `{$tbl}` {$drops}")->execute();
				} catch (\Throwable $e) {
					$this->logWarning("Migration 2.0.6: Không DROP được FK trên `{$tbl}`: " . $e->getMessage());
				}
			}

			$this->logInfo('Migration 2.0.6: Đã DROP ' . count($allFks) . ' foreign key(s).');

			// --- 5c. MODIFY tất cả cột INT* sang UNSIGNED ---
			$intCols = $db->setQuery(
				"SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE,
				        COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT
				 FROM INFORMATION_SCHEMA.COLUMNS
				 WHERE TABLE_SCHEMA = " . $db->quote($dbName) . "
				   AND TABLE_NAME LIKE " . $db->quote($tablePrefix) . "
				   AND DATA_TYPE IN ('int','tinyint','smallint','mediumint','bigint')
				   AND COLUMN_TYPE NOT LIKE '%unsigned%'
				 ORDER BY TABLE_NAME, ORDINAL_POSITION"
			)->loadAssocList();

			$unsignedCount   = 0;
			$unsignedSkipped = 0;

			foreach ($intCols as $col) {
				$tbl     = $col['TABLE_NAME'];
				$colName = $col['COLUMN_NAME'];
				$colType = strtoupper($col['COLUMN_TYPE']) . ' UNSIGNED';
				$notNull = ($col['IS_NULLABLE'] === 'NO');
				$default = $col['COLUMN_DEFAULT'];
				$extra   = strtolower((string) $col['EXTRA']);
				$comment = $col['COLUMN_COMMENT'];

				$def = "`{$colName}` {$colType}";
				if (strpos($extra, 'auto_increment') !== false) {
					$def .= ' AUTO_INCREMENT';
				}
				$def .= $notNull ? ' NOT NULL' : ' NULL';
				if ($default !== null) {
					$def .= is_numeric($default)
						? " DEFAULT {$default}"
						: " DEFAULT " . $db->quote($default);
				} elseif (!$notNull) {
					$def .= ' DEFAULT NULL';
				}
				if ($comment !== '') {
					$def .= ' COMMENT ' . $db->quote($comment);
				}

				try {
					$db->setQuery("ALTER TABLE `{$tbl}` MODIFY COLUMN {$def}")->execute();
					$unsignedCount++;
				} catch (\Throwable $e) {
					$unsignedSkipped++;
					$this->logWarning(
						"Migration 2.0.6: Bỏ qua UNSIGNED cho `{$tbl}`.`{$colName}`: "
						. $e->getMessage()
					);
				}
			}

			$this->logInfo(
				"Migration 2.0.6: Đã thêm UNSIGNED cho {$unsignedCount} cột"
				. ($unsignedSkipped > 0 ? ", bỏ qua {$unsignedSkipped} cột." : ".")
			);

			// --- 5d. ADD lại tất cả FK ---
			$addedFk   = 0;
			$skippedFk = 0;
			foreach ($allFks as $fk) {
				try {
					$db->setQuery(
						"ALTER TABLE `{$fk['TABLE_NAME']}`
						 ADD CONSTRAINT `{$fk['CONSTRAINT_NAME']}`
						     FOREIGN KEY (`{$fk['COLUMN_NAME']}`)
						     REFERENCES `{$fk['REFERENCED_TABLE_NAME']}` (`{$fk['REFERENCED_COLUMN_NAME']}`)
						     ON DELETE {$fk['DELETE_RULE']}
						     ON UPDATE {$fk['UPDATE_RULE']}"
					)->execute();
					$addedFk++;
				} catch (\Throwable $e) {
					$skippedFk++;
					$this->logWarning(
						"Migration 2.0.6: Không ADD lại được FK `{$fk['CONSTRAINT_NAME']}` "
						. "trên `{$fk['TABLE_NAME']}`: " . $e->getMessage()
					);
				}
			}

			$this->logInfo(
				"Migration 2.0.6: Đã ADD lại {$addedFk} FK"
				. ($skippedFk > 0 ? ", bỏ qua {$skippedFk} FK (xem warning log)." : ".")
			);

			// =================================================================
			// Nhiệm vụ 6: Surrogate key cho junction tables
			// =================================================================
			$this->addSurrogateKey206($db, $dbName, '#__eqa_cohort_learner');
			$this->addSurrogateKey206($db, $dbName, '#__eqa_exam_learner');
			$this->addSurrogateKey206($db, $dbName, '#__eqa_papers');

			$this->logInfo('Migration 2.0.6: Hoàn tất thành công!');
			return true;

		} catch (\Throwable $e) {
			$msg = 'Migration 2.0.6 thất bại: ' . $e->getMessage();
			Log::add('com_eqa: ' . $msg, Log::ERROR, 'com_eqa');
			$this->logError($msg);
			return false;
		}
	}

	/**
	 * Sửa tên cột requested_at → created_at và xóa requested_by cho một bảng.
	 *
	 * Logic:
	 *   - Nếu có `requested_at` và KHÔNG có `created_at` → RENAME
	 *   - Nếu có cả hai (trường hợp chạy migration bị gián đoạn) → DROP requested_at
	 *   - Nếu không có `created_at` và không có `requested_at` → ADD created_at
	 *   - DROP `requested_by` nếu còn tồn tại
	 *
	 * @param  \Joomla\Database\DatabaseInterface $db
	 * @param  string $dbName    Tên database thực
	 * @param  string $table     Tên bảng thực (đã replace prefix)
	 * @param  string $afterCol  Cột đứng trước created_at khi ADD (nếu cần)
	 */
	private function fixRequestedAtColumn(
		\Joomla\Database\DatabaseInterface $db,
		string $dbName,
		string $table,
		string $afterCol
	): void
	{
		$cols = $this->getExistingColumnNames($db, $table);

		$hasRequestedAt = in_array('requested_at', $cols, true);
		$hasCreatedAt   = in_array('created_at',   $cols, true);
		$hasRequestedBy = in_array('requested_by', $cols, true);

		if ($hasRequestedAt && !$hasCreatedAt) {
			$db->setQuery(
				"ALTER TABLE `{$table}` CHANGE `requested_at` `created_at` DATETIME NULL DEFAULT NULL"
			)->execute();
			$this->logInfo("Migration 2.0.6: Đã đổi tên `requested_at` → `created_at` trong `{$table}`.");

		} elseif ($hasRequestedAt && $hasCreatedAt) {
			// Cả hai cùng tồn tại — xóa cái dư
			$db->setQuery("ALTER TABLE `{$table}` DROP COLUMN `requested_at`")->execute();
			$this->logInfo("Migration 2.0.6: Đã DROP `requested_at` dư thừa trong `{$table}`.");

		} elseif (!$hasCreatedAt) {
			// Không có cả hai → ADD
			$db->setQuery(
				"ALTER TABLE `{$table}`
				 ADD COLUMN `created_at` DATETIME NULL DEFAULT NULL AFTER `{$afterCol}`"
			)->execute();
			$this->logInfo("Migration 2.0.6: Đã ADD `created_at` vào `{$table}`.");

		} else {
			$this->logInfo("Migration 2.0.6: `{$table}`.`created_at` đã tồn tại, bỏ qua.");
		}

		if ($hasRequestedBy) {
			$db->setQuery("ALTER TABLE `{$table}` DROP COLUMN `requested_by`")->execute();
			$this->logInfo("Migration 2.0.6: Đã DROP `requested_by` khỏi `{$table}`.");
		}
	}

	/**
	 * Bổ sung surrogate key `id INT UNSIGNED AUTO_INCREMENT` vào junction table.
	 *
	 * Thuật toán:
	 *   1. Nếu cột `id` đã tồn tại → bỏ qua (idempotent).
	 *   2. Đọc danh sách cột trong PRIMARY KEY hiện tại.
	 *   3. Nếu có composite PK:
	 *      → DROP PRIMARY KEY, ADD id FIRST, ADD PK (id), ADD UNIQUE (cột cũ)
	 *   4. Nếu không có PK (chỉ UNIQUE):
	 *      → ADD id FIRST, ADD PRIMARY KEY (id)
	 *
	 * @param  \Joomla\Database\DatabaseInterface $db
	 * @param  string $dbName   Tên database thực
	 * @param  string $jTable   Tên bảng Joomla-prefixed (ví dụ: '#__eqa_papers')
	 */
	private function addSurrogateKey206(
		\Joomla\Database\DatabaseInterface $db,
		string $dbName,
		string $jTable
	): void
	{
		$table = $db->replacePrefix($jTable);

		// Idempotent: kiểm tra cột `id`
		$idExists = (int) $db->setQuery(
			"SELECT COUNT(*)
			 FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = " . $db->quote($dbName) . "
			   AND TABLE_NAME   = " . $db->quote($table) . "
			   AND COLUMN_NAME  = 'id'"
		)->loadResult();

		if ($idExists > 0) {
			$this->logInfo("Migration 2.0.6: `{$table}`.`id` đã tồn tại, bỏ qua.");
			return;
		}

		// Đọc các cột trong PRIMARY KEY hiện tại
		$pkCols = $db->setQuery(
			"SELECT COLUMN_NAME
			 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
			 WHERE TABLE_SCHEMA    = " . $db->quote($dbName) . "
			   AND TABLE_NAME      = " . $db->quote($table) . "
			   AND CONSTRAINT_NAME = 'PRIMARY'
			 ORDER BY ORDINAL_POSITION"
		)->loadColumn();

		if (!empty($pkCols)) {
			// Có composite PK → DROP PK, ADD id, tái tạo UNIQUE
			$pkColList = '`' . implode('`, `', $pkCols) . '`';
			// Tên UNIQUE: lấy phần sau prefix (ví dụ jos_eqa_cohort_learner → eqa_cohort_learner)
			$shortName = preg_replace('/^[^_]+_/', '', $table);
			$uqName    = 'uq_' . $shortName . '_natural';

			$db->setQuery(
				"ALTER TABLE `{$table}`
				 DROP PRIMARY KEY,
				 ADD COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
				 ADD PRIMARY KEY (`id`),
				 ADD UNIQUE KEY `{$uqName}` ({$pkColList})"
			)->execute();

			$this->logInfo(
				"Migration 2.0.6: Đã thêm surrogate key `id` vào `{$table}` "
				. "(composite PK ({$pkColList}) → UNIQUE `{$uqName}`)."
			);
		} else {
			// Không có PK → chỉ ADD id
			$db->setQuery(
				"ALTER TABLE `{$table}`
				 ADD COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
				 ADD PRIMARY KEY (`id`)"
			)->execute();

			$this->logInfo(
				"Migration 2.0.6: Đã thêm surrogate key `id` vào `{$table}`."
			);
		}
	}

	// =========================================================================
	// Logging helpers
	// =========================================================================

	private function logInfo(string $msg): void
	{
		Factory::getApplication()->enqueueMessage("com_eqa: {$msg}", 'info');
	}

	private function logWarning(string $msg): void
	{
		Factory::getApplication()->enqueueMessage("com_eqa: {$msg}", 'warning');
	}

	private function logError(string $msg): void
	{
		Factory::getApplication()->enqueueMessage("com_eqa: {$msg}", 'error');
	}
}
