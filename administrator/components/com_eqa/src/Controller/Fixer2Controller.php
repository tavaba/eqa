<?php
/**
 * @package     Kma\Component\Eqa\Administrator\Controller
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Kma\Component\Eqa\Administrator\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

class Fixer2Controller extends BaseController
{

	/**
	 * fixMigration206()
	 *
	 * Kiểm tra và sửa chữa CSDL để đảm bảo tất cả thay đổi của migration v2.0.6
	 * đã được áp dụng đầy đủ và đúng — bất kể migration đó trước đây đã chạy
	 * thành công, chạy dở, hay chưa chạy bao giờ.
	 *
	 * Hoàn toàn idempotent: mỗi bước đều kiểm tra trạng thái thực tế của CSDL
	 * trước khi thực thi, nên có thể gọi nhiều lần mà không gây hại.
	 *
	 * Các nhiệm vụ (mirror theo runMigration206):
	 *   1. Sửa check_out → checked_out trên 14 bảng gốc
	 *   2. Sửa requested_at → created_at, DROP requested_by trên #__eqa_regradings
	 *   3. Sửa requested_at → created_at, DROP requested_by trên #__eqa_gradecorrections
	 *   4. ADD updated_at / updated_by vào #__eqa_exam_learner nếu thiếu
	 *   5. Thêm UNSIGNED cho tất cả cột số nguyên (DROP FK → MODIFY → ADD FK lại)
	 *   6. Bổ sung surrogate key `id` cho 3 junction table nếu thiếu
	 *
	 * Dán hàm này và tất cả helper bên dưới vào controller của bạn.
	 * Nếu controller đã có logInfo / logWarning / logError thì bỏ phần cuối file.
	 *
	 * Gọi từ controller:
	 *   $this->fixMigration206();
	 */

// =============================================================================
// HÀM CHÍNH
// =============================================================================

	/**
	 * Kiểm tra và sửa chữa CSDL để đạt đúng trạng thái sau migration v2.0.6.
	 * Idempotent — an toàn khi gọi nhiều lần.
	 */
	public function fix(): void
	{
		$db     = Factory::getDbo();
		$dbName = $db->setQuery('SELECT DATABASE()')->loadResult();

		$this->logInfo('fixMigration206: Bắt đầu kiểm tra và sửa chữa...');

		try
		{
			// Nhiệm vụ 1: check_out → checked_out trên 14 bảng gốc
			$this->fix206CheckoutColumns($db);

			// Nhiệm vụ 2 & 3: requested_at → created_at trên regradings và gradecorrections
			$this->fix206RequestedAt($db, $db->replacePrefix('#__eqa_regradings'), 'status');
			$this->fix206RequestedAt($db, $db->replacePrefix('#__eqa_gradecorrections'), 'status');

			// Nhiệm vụ 4: updated_at / updated_by trên #__eqa_exam_learner
			$this->fix206ExamLearnerCols($db);

			// Nhiệm vụ 5: Thêm UNSIGNED + khôi phục FK
			$this->fix206UnsignedCols($db, $dbName);

			// Nhiệm vụ 6: Surrogate key `id` cho 3 junction table
			$this->fix206SurrogateKey($db, $dbName, '#__eqa_cohort_learner');
			$this->fix206SurrogateKey($db, $dbName, '#__eqa_exam_learner');
			$this->fix206SurrogateKey($db, $dbName, '#__eqa_papers');

			$this->logInfo('fixMigration206: Hoàn tất.');

		}
		catch (\Throwable $e)
		{
			$this->logError('fixMigration206 thất bại: ' . $e->getMessage());
		}
	}

// =============================================================================
// HELPERS
// =============================================================================

	/**
	 * Nhiệm vụ 1: Đảm bảo `checked_out` / `checked_out_time` đúng tên
	 * trên 14 bảng gốc.
	 */
	private function fix206CheckoutColumns(\Joomla\Database\DatabaseInterface $db): void
	{
		$tables = [
			'#__eqa_buildings', '#__eqa_rooms', '#__eqa_units',
			'#__eqa_employees', '#__eqa_specialities', '#__eqa_programs',
			'#__eqa_courses', '#__eqa_groups', '#__eqa_learners',
			'#__eqa_subjects', '#__eqa_classes', '#__eqa_examseasons',
			'#__eqa_examsessions', '#__eqa_exams',
		];

		foreach ($tables as $jTable)
		{
			$table = $db->replacePrefix($jTable);
			$cols  = $this->fix206GetColumns($db, $table);

			if (in_array('checked_out', $cols, true))
			{
				$this->logInfo("fix206: `{$table}`.`checked_out` đã đúng, bỏ qua.");
				continue;
			}

			if (in_array('check_out', $cols, true))
			{
				$colInfo = $db->setQuery(
					"SELECT COLUMN_TYPE, IS_NULLABLE
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = " . $db->quote($table) . "
                   AND COLUMN_NAME  = 'check_out'"
				)->loadObject();

				$colType = $colInfo ? strtoupper($colInfo->COLUMN_TYPE) : 'INT';
				$null    = ($colInfo && $colInfo->IS_NULLABLE === 'NO') ? 'NOT NULL' : 'NULL';

				$db->setQuery(
					"ALTER TABLE `{$table}`
                 CHANGE `check_out`      `checked_out`      {$colType} {$null} DEFAULT NULL,
                 CHANGE `check_out_time` `checked_out_time` DATETIME   NULL    DEFAULT NULL"
				)->execute();
				$this->logInfo("fix206: Đã đổi tên `check_out` → `checked_out` trong `{$table}`.");
				continue;
			}

			// Không có cả hai → ADD
			$afterCol = in_array('modified_by', $cols, true) ? 'modified_by'
				: (in_array('created_by', $cols, true) ? 'created_by' : null);
			$after    = $afterCol !== null ? "AFTER `{$afterCol}`" : '';

			$db->setQuery(
				"ALTER TABLE `{$table}`
             ADD COLUMN `checked_out`      INT      NULL DEFAULT NULL {$after},
             ADD COLUMN `checked_out_time` DATETIME NULL DEFAULT NULL AFTER `checked_out`"
			)->execute();
			$this->logInfo("fix206: Đã ADD `checked_out` / `checked_out_time` vào `{$table}`.");
		}
	}

	/**
	 * Nhiệm vụ 2 & 3: Sửa tên cột requested_at → created_at và DROP requested_by.
	 *
	 * @param   string  $table     Tên bảng thực (đã replacePrefix)
	 * @param   string  $afterCol  Cột đứng trước created_at khi cần ADD
	 */
	private function fix206RequestedAt(
		\Joomla\Database\DatabaseInterface $db,
		string                             $table,
		string                             $afterCol
	): void
	{
		$cols = $this->fix206GetColumns($db, $table);

		$hasRequestedAt = in_array('requested_at', $cols, true);
		$hasCreatedAt   = in_array('created_at', $cols, true);
		$hasRequestedBy = in_array('requested_by', $cols, true);

		if ($hasRequestedAt && !$hasCreatedAt)
		{
			$db->setQuery(
				"ALTER TABLE `{$table}`
             CHANGE `requested_at` `created_at` DATETIME NULL DEFAULT NULL"
			)->execute();
			$this->logInfo("fix206: Đã đổi tên `requested_at` → `created_at` trong `{$table}`.");

		}
		elseif ($hasRequestedAt && $hasCreatedAt)
		{
			$db->setQuery("ALTER TABLE `{$table}` DROP COLUMN `requested_at`")->execute();
			$this->logInfo("fix206: Đã DROP `requested_at` dư thừa trong `{$table}`.");

		}
		elseif (!$hasCreatedAt)
		{
			$db->setQuery(
				"ALTER TABLE `{$table}`
             ADD COLUMN `created_at` DATETIME NULL DEFAULT NULL AFTER `{$afterCol}`"
			)->execute();
			$this->logInfo("fix206: Đã ADD `created_at` vào `{$table}`.");

		}
		else
		{
			$this->logInfo("fix206: `{$table}`.`created_at` đã đúng, bỏ qua.");
		}

		if ($hasRequestedBy)
		{
			$db->setQuery("ALTER TABLE `{$table}` DROP COLUMN `requested_by`")->execute();
			$this->logInfo("fix206: Đã DROP `requested_by` khỏi `{$table}`.");
		}
	}

	/**
	 * Nhiệm vụ 4: Bổ sung `updated_at` / `updated_by` vào #__eqa_exam_learner.
	 */
	private function fix206ExamLearnerCols(\Joomla\Database\DatabaseInterface $db): void
	{
		$table = $db->replacePrefix('#__eqa_exam_learner');
		$cols  = $this->fix206GetColumns($db, $table);

		if (!in_array('updated_at', $cols, true))
		{
			$afterCol = in_array('modified_by', $cols, true) ? 'modified_by'
				: (in_array('modified_at', $cols, true) ? 'modified_at' : null);
			$after    = $afterCol !== null ? "AFTER `{$afterCol}`" : '';
			$db->setQuery(
				"ALTER TABLE `{$table}`
             ADD COLUMN `updated_at` DATETIME NULL
                 COMMENT 'Dấu thời gian cập nhật (kênh ngoài)' {$after}"
			)->execute();
			$this->logInfo("fix206: Đã ADD `updated_at` vào `{$table}`.");
		}
		else
		{
			$this->logInfo("fix206: `{$table}`.`updated_at` đã tồn tại, bỏ qua.");
		}

		$cols = $this->fix206GetColumns($db, $table); // re-fetch

		if (!in_array('updated_by', $cols, true))
		{
			$after = in_array('updated_at', $cols, true) ? "AFTER `updated_at`" : '';
			$db->setQuery(
				"ALTER TABLE `{$table}`
             ADD COLUMN `updated_by` INT UNSIGNED NULL
                 COMMENT 'Người cập nhật (kênh ngoài)' {$after}"
			)->execute();
			$this->logInfo("fix206: Đã ADD `updated_by` vào `{$table}`.");
		}
		else
		{
			$this->logInfo("fix206: `{$table}`.`updated_by` đã tồn tại, bỏ qua.");
		}
	}

	/**
	 * Nhiệm vụ 5: Thêm UNSIGNED cho tất cả cột số nguyên và khôi phục FK.
	 *
	 * Tại sao hardcode danh sách FK thay vì đọc từ INFORMATION_SCHEMA:
	 * Mục đích của hàm fix này là khôi phục FK đã mất. Nếu FK đã mất thì
	 * INFORMATION_SCHEMA không có → query trả về rỗng → không ADD lại được gì.
	 * Danh sách hardcode đảm bảo luôn biết đủ FK cần có, bất kể trạng thái DB.
	 *
	 * @throws \Throwable nếu ADD FK thất bại (lỗi nghiêm trọng, không được bỏ qua)
	 */
	private function fix206UnsignedCols(
		\Joomla\Database\DatabaseInterface $db,
		string                             $dbName
	): void
	{
		// ------------------------------------------------------------------
		// Danh sách FK chuẩn theo install.mysql.utf8.sql
		// [constraintName, table (Joomla-prefixed), column, refTable, refColumn, onDelete]
		// ------------------------------------------------------------------
		$canonicalFks = [
			['fk_eqa_rooms_building', '#__eqa_rooms', 'building_id', '#__eqa_buildings', 'id', 'RESTRICT'],
			['fk_eqa_employees_unit', '#__eqa_employees', 'unit_id', '#__eqa_units', 'id', 'RESTRICT'],
			['fk_eqa_programs_spec', '#__eqa_programs', 'spec_id', '#__eqa_specialities', 'id', 'RESTRICT'],
			['fk_eqa_courses_prog', '#__eqa_courses', 'prog_id', '#__eqa_programs', 'id', 'RESTRICT'],
			['fk_eqa_groups_course', '#__eqa_groups', 'course_id', '#__eqa_courses', 'id', 'RESTRICT'],
			['fk_eqa_groups_hoomroom', '#__eqa_groups', 'homeroom_id', '#__eqa_employees', 'id', 'RESTRICT'],
			['fk_eqa_groups_adviser', '#__eqa_groups', 'adviser_id', '#__eqa_employees', 'id', 'RESTRICT'],
			['fk_eqa_learners_group', '#__eqa_learners', 'group_id', '#__eqa_groups', 'id', 'RESTRICT'],
			['fk_eqa_cohort_learner_cohort', '#__eqa_cohort_learner', 'cohort_id', '#__eqa_cohorts', 'id', 'CASCADE'],
			['fk_eqa_cohort_learner_learner', '#__eqa_cohort_learner', 'learner_id', '#__eqa_learners', 'id', 'RESTRICT'],
			['fk_eqa_subjects_unit', '#__eqa_subjects', 'unit_id', '#__eqa_units', 'id', 'RESTRICT'],
			['fk_eqa_classes_subject', '#__eqa_classes', 'subject_id', '#__eqa_subjects', 'id', 'RESTRICT'],
			['fk_eqa_classes_lecturer', '#__eqa_classes', 'lecturer_id', '#__eqa_employees', 'id', 'RESTRICT'],
			['fk_eqa_stimulations_subject', '#__eqa_stimulations', 'subject_id', '#__eqa_subjects', 'id', 'RESTRICT'],
			['fk_eqa_stimulations_learner', '#__eqa_stimulations', 'learner_id', '#__eqa_learners', 'id', 'RESTRICT'],
			['fk_eqa_class_learner_class', '#__eqa_class_learner', 'class_id', '#__eqa_classes', 'id', 'CASCADE'],
			['fk_eqa_class_learner_learner', '#__eqa_class_learner', 'learner_id', '#__eqa_learners', 'id', 'RESTRICT'],
			['fk_eqa_examsessions_examseason', '#__eqa_examsessions', 'examseason_id', '#__eqa_examseasons', 'id', 'RESTRICT'],
			['fk_eqa_examsessions_assessment', '#__eqa_examsessions', 'assessment_id', '#__eqa_assessments', 'id', 'RESTRICT'],
			['fk_eqa_exams_subject', '#__eqa_exams', 'subject_id', '#__eqa_subjects', 'id', 'RESTRICT'],
			['fk_eqa_exams_examseason', '#__eqa_exams', 'examseason_id', '#__eqa_examseasons', 'id', 'RESTRICT'],
			['fk_eqa_examrooms_room', '#__eqa_examrooms', 'room_id', '#__eqa_rooms', 'id', 'RESTRICT'],
			['fk_eqa_examrooms_examsession', '#__eqa_examrooms', 'examsession_id', '#__eqa_examsessions', 'id', 'RESTRICT'],
			['fk_eqa_exam_learner_exam', '#__eqa_exam_learner', 'exam_id', '#__eqa_exams', 'id', 'RESTRICT'],
			['fk_eqa_exam_learner_learner', '#__eqa_exam_learner', 'learner_id', '#__eqa_learners', 'id', 'RESTRICT'],
			['fk_eqa_exam_learner_class', '#__eqa_exam_learner', 'class_id', '#__eqa_classes', 'id', 'RESTRICT'],
			['fk_eqa_exam_learner_stimulation', '#__eqa_exam_learner', 'stimulation_id', '#__eqa_stimulations', 'id', 'RESTRICT'],
			['fk_eqa_exam_learner_examroom', '#__eqa_exam_learner', 'examroom_id', '#__eqa_examrooms', 'id', 'RESTRICT'],
			['fk_eqa_packages_examiner1', '#__eqa_packages', 'examiner1_id', '#__eqa_employees', 'id', 'RESTRICT'],
			['fk_eqa_packages_examiner2', '#__eqa_packages', 'examiner2_id', '#__eqa_employees', 'id', 'RESTRICT'],
			['fk_eqa_papers_exam', '#__eqa_papers', 'exam_id', '#__eqa_exams', 'id', 'RESTRICT'],
			['fk_eqa_papers_learner', '#__eqa_papers', 'learner_id', '#__eqa_learners', 'id', 'RESTRICT'],
			['fk_eqa_papers_package', '#__eqa_papers', 'package_id', '#__eqa_packages', 'id', 'RESTRICT'],
			['fk_eqa_regradings_exam', '#__eqa_regradings', 'exam_id', '#__eqa_exams', 'id', 'RESTRICT'],
			['fk_eqa_regradings_examiner1', '#__eqa_regradings', 'examiner1_id', '#__eqa_employees', 'id', 'RESTRICT'],
			['fk_eqa_regradings_examiner2', '#__eqa_regradings', 'examiner2_id', '#__eqa_employees', 'id', 'RESTRICT'],
			['fk_eqa_regradings_learner', '#__eqa_regradings', 'learner_id', '#__eqa_learners', 'id', 'RESTRICT'],
			['fk_eqa_gradecorrections_exam', '#__eqa_gradecorrections', 'exam_id', '#__eqa_exams', 'id', 'RESTRICT'],
			['fk_eqa_gradecorrections_reviewer', '#__eqa_gradecorrections', 'reviewer_id', '#__eqa_employees', 'id', 'RESTRICT'],
			['fk_eqa_gradecorrections_learner', '#__eqa_gradecorrections', 'learner_id', '#__eqa_learners', 'id', 'RESTRICT'],
			['fk_eqa_mmproductions_exam', '#__eqa_mmproductions', 'exam_id', '#__eqa_exams', 'id', 'RESTRICT'],
			['fk_eqa_mmproductions_examiner', '#__eqa_mmproductions', 'examiner_id', '#__eqa_employees', 'id', 'RESTRICT'],
			['fk_eqa_conducts_learner', '#__eqa_conducts', 'learner_id', '#__eqa_learners', 'id', 'RESTRICT'],
			['fk_eqa_assessment_learner_examroom', '#__eqa_assessment_learner', 'examroom_id', '#__eqa_examrooms', 'id', 'RESTRICT'],
			['fk_eqa_assessment_learner_assessment', '#__eqa_assessment_learner', 'assessment_id', '#__eqa_assessments', 'id', 'RESTRICT'],
			['fk_eqa_assessment_learner_learner', '#__eqa_assessment_learner', 'learner_id', '#__eqa_learners', 'id', 'RESTRICT'],
		];

		// Resolve tên bảng thực (thay #__ → prefix thực) một lần
		$resolvedFks = array_map(static function ($fk) use ($db) {
			return [
				'name'      => $fk[0],
				'table'     => $db->replacePrefix($fk[1]),
				'column'    => $fk[2],
				'refTable'  => $db->replacePrefix($fk[3]),
				'refColumn' => $fk[4],
				'onDelete'  => $fk[5],
			];
		}, $canonicalFks);

		// ------------------------------------------------------------------
		// Kiểm tra các cột cần MODIFY sang UNSIGNED
		// Dùng ESCAPE '!' để ký tự '_' trong pattern không bị hiểu là wildcard
		// ------------------------------------------------------------------
		$prefix  = $db->getPrefix();
		$likePat = $prefix . 'eqa!_%';

		$intCols = $db->setQuery(
			"SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE,
                COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = " . $db->quote($dbName) . "
           AND TABLE_NAME   LIKE " . $db->quote($likePat) . " ESCAPE '!'
           AND DATA_TYPE    IN ('int','tinyint','smallint','mediumint','bigint')
           AND COLUMN_TYPE  NOT LIKE '%unsigned%'
         ORDER BY TABLE_NAME, ORDINAL_POSITION"
		)->loadAssocList();

		if (empty($intCols))
		{
			$this->logInfo('fix206: Tất cả cột số nguyên đã có UNSIGNED.');
		}
		else
		{
			$this->logInfo(
				'fix206: Tìm thấy ' . count($intCols) . ' cột số nguyên chưa có UNSIGNED, bắt đầu xử lý...'
			);

			// ---------------------------------------------------------------
			// DROP tất cả FK hiện có (dùng INFORMATION_SCHEMA để lấy FK còn tồn tại)
			// ---------------------------------------------------------------
			$existingFks = $db->setQuery(
				"SELECT kcu.TABLE_NAME, kcu.CONSTRAINT_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
             JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                  ON  rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
                  AND rc.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
             WHERE kcu.TABLE_SCHEMA              = " . $db->quote($dbName) . "
               AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
               AND (
                   kcu.TABLE_NAME            LIKE " . $db->quote($likePat) . " ESCAPE '!'
                OR kcu.REFERENCED_TABLE_NAME LIKE " . $db->quote($likePat) . " ESCAPE '!'
               )
             ORDER BY kcu.TABLE_NAME, kcu.CONSTRAINT_NAME"
			)->loadAssocList();

			$fksByTable = [];
			foreach ($existingFks as $fk)
			{
				$fksByTable[$fk['TABLE_NAME']][] = $fk['CONSTRAINT_NAME'];
			}

			$droppedFk = 0;
			foreach ($fksByTable as $tbl => $names)
			{
				$drops = implode(', ',
					array_map(fn($n) => "DROP FOREIGN KEY `{$n}`", array_unique($names))
				);
				try
				{
					$db->setQuery("ALTER TABLE `{$tbl}` {$drops}")->execute();
					$droppedFk += count(array_unique($names));
				}
				catch (\Throwable $e)
				{
					$this->logWarning(
						"fix206: Không DROP được FK trên `{$tbl}`: " . $e->getMessage()
					);
				}
			}

			$this->logInfo("fix206: Đã DROP {$droppedFk} FK hiện có.");

			// ---------------------------------------------------------------
			// MODIFY cột → UNSIGNED
			// ---------------------------------------------------------------
			$unsignedDone    = 0;
			$unsignedSkipped = 0;

			foreach ($intCols as $col)
			{
				$tbl     = $col['TABLE_NAME'];
				$colName = $col['COLUMN_NAME'];
				$colType = strtoupper($col['COLUMN_TYPE']) . ' UNSIGNED';
				$notNull = ($col['IS_NULLABLE'] === 'NO');
				$default = $col['COLUMN_DEFAULT'];
				$extra   = strtolower((string) $col['EXTRA']);
				$comment = $col['COLUMN_COMMENT'];

				$def = "`{$colName}` {$colType}";
				if (strpos($extra, 'auto_increment') !== false)
				{
					$def .= ' AUTO_INCREMENT';
				}
				$def .= $notNull ? ' NOT NULL' : ' NULL';
				if ($default !== null)
				{
					$def .= is_numeric($default)
						? " DEFAULT {$default}"
						: " DEFAULT " . $db->quote($default);
				}
				elseif (!$notNull)
				{
					$def .= ' DEFAULT NULL';
				}
				if ($comment !== '')
				{
					$def .= ' COMMENT ' . $db->quote($comment);
				}

				try
				{
					$db->setQuery("ALTER TABLE `{$tbl}` MODIFY COLUMN {$def}")->execute();
					$unsignedDone++;
				}
				catch (\Throwable $e)
				{
					$unsignedSkipped++;
					$this->logWarning(
						"fix206: Bỏ qua UNSIGNED cho `{$tbl}`.`{$colName}`: " . $e->getMessage()
					);
				}
			}

			$this->logInfo(
				"fix206: Đã thêm UNSIGNED cho {$unsignedDone} cột"
				. ($unsignedSkipped > 0 ? ", bỏ qua {$unsignedSkipped} cột (xem warning log)." : '.')
			);
		}

		// ------------------------------------------------------------------
		// ADD lại / khôi phục FK theo danh sách chuẩn (hardcode từ SQL file)
		// Với mỗi FK:
		//   - Tên đúng đã tồn tại   → bỏ qua
		//   - Tên sai đã tồn tại    → DROP + ADD với tên chuẩn (1 lệnh ALTER)
		//   - Chưa tồn tại          → ADD mới (throw nếu thất bại)
		// ------------------------------------------------------------------
		$addedFk   = 0;
		$renamedFk = 0;
		$existedFk = 0;

		foreach ($resolvedFks as $fk)
		{
			// Kiểm tra FK đã tồn tại với đúng tên chuẩn chưa
			$existsByName = (int) $db->setQuery(
				"SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA    = " . $db->quote($dbName) . "
               AND TABLE_NAME      = " . $db->quote($fk['table']) . "
               AND CONSTRAINT_NAME = " . $db->quote($fk['name']) . "
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
			)->loadResult();

			if ($existsByName > 0)
			{
				$existedFk++;
				continue;
			}

			// Tìm FK khác tên nhưng cùng (bảng, cột, bảng tham chiếu)
			$oldFkName = $db->setQuery(
				"SELECT kcu.CONSTRAINT_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
             JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                  ON  rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
                  AND rc.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
             WHERE kcu.TABLE_SCHEMA          = " . $db->quote($dbName) . "
               AND kcu.TABLE_NAME            = " . $db->quote($fk['table']) . "
               AND kcu.COLUMN_NAME           = " . $db->quote($fk['column']) . "
               AND kcu.REFERENCED_TABLE_NAME = " . $db->quote($fk['refTable']) . "
             LIMIT 1"
			)->loadResult();

			if ($oldFkName !== null)
			{
				// Tên sai → DROP + ADD với tên chuẩn trong 1 lệnh ALTER
				$db->setQuery(
					"ALTER TABLE `{$fk['table']}`
                 DROP FOREIGN KEY `{$oldFkName}`,
                 ADD CONSTRAINT `{$fk['name']}`
                     FOREIGN KEY (`{$fk['column']}`)
                     REFERENCES `{$fk['refTable']}` (`{$fk['refColumn']}`)
                     ON DELETE {$fk['onDelete']}"
				)->execute();
				$this->logInfo(
					"fix206: Đã đổi tên FK `{$oldFkName}` → `{$fk['name']}` "
					. "trên `{$fk['table']}`.`{$fk['column']}`."
				);
				$renamedFk++;
			}
			else
			{
				// Chưa tồn tại → ADD mới; throw nếu thất bại (không được bỏ qua)
				$db->setQuery(
					"ALTER TABLE `{$fk['table']}`
                 ADD CONSTRAINT `{$fk['name']}`
                     FOREIGN KEY (`{$fk['column']}`)
                     REFERENCES `{$fk['refTable']}` (`{$fk['refColumn']}`)
                     ON DELETE {$fk['onDelete']}"
				)->execute();
				$this->logInfo(
					"fix206: Đã ADD FK `{$fk['name']}` trên `{$fk['table']}`.`{$fk['column']}`."
				);
				$addedFk++;
			}
		}

		$this->logInfo(
			"fix206: FK — Đã có đúng tên: {$existedFk}, Thêm mới: {$addedFk}, Đổi tên: {$renamedFk}."
		);
	}

	/**
	 * Nhiệm vụ 6: Bổ sung surrogate key `id INT UNSIGNED AUTO_INCREMENT`
	 * vào một junction table nếu chưa có.
	 *
	 * @param   string  $jTable  Tên bảng Joomla-prefixed, ví dụ '#__eqa_papers'
	 */
	private function fix206SurrogateKey(
		\Joomla\Database\DatabaseInterface $db,
		string                             $dbName,
		string                             $jTable
	): void
	{
		$table = $db->replacePrefix($jTable);

		$idExists = (int) $db->setQuery(
			"SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = " . $db->quote($dbName) . "
           AND TABLE_NAME   = " . $db->quote($table) . "
           AND COLUMN_NAME  = 'id'"
		)->loadResult();

		if ($idExists > 0)
		{
			$this->logInfo("fix206: `{$table}`.`id` đã tồn tại, bỏ qua.");

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

		if (!empty($pkCols))
		{
			$pkColList = '`' . implode('`, `', $pkCols) . '`';
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
				"fix206: Đã thêm surrogate key `id` vào `{$table}` "
				. "(composite PK ({$pkColList}) → UNIQUE `{$uqName}`)."
			);
		}
		else
		{
			$db->setQuery(
				"ALTER TABLE `{$table}`
             ADD COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
             ADD PRIMARY KEY (`id`)"
			)->execute();

			$this->logInfo("fix206: Đã thêm surrogate key `id` vào `{$table}`.");
		}
	}

	/**
	 * Trả về danh sách tên tất cả cột của bảng.
	 *
	 * @return string[]
	 */
	private function fix206GetColumns(\Joomla\Database\DatabaseInterface $db, string $table): array
	{
		$columns = $db->setQuery("SHOW COLUMNS FROM `{$table}`")->loadAssocList('Field');

		return array_keys($columns ?? []);
	}

// =============================================================================
// LOGGING HELPERS — chỉ cần dán nếu controller chưa có sẵn
// =============================================================================

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
	}}