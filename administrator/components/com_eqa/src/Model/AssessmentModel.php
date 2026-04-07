<?php

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Component\Eqa\Administrator\Base\AdminModel;
use stdClass;

/**
 * Model cho một kỳ sát hạch (item model).
 *
 * Xử lý load/save bản ghi trong bảng `#__eqa_assessments`.
 * Tên form XML tương ứng: `administrator/forms/assessment.xml`
 * Tên Table class: `AssessmentTable` → bảng `#__eqa_assessments`.
 *
 * Quy tắc xử lý thời gian:
 *   - Trường DATE (`start_date`, `end_date`): không có timezone, lưu và hiển thị nguyên.
 *   - Trường DATETIME (`registration_start`, `registration_end`):
 *       + Người dùng nhập theo giờ hệ thống (OS timezone).
 *       + Lưu vào DB dưới dạng UTC.
 *       + Khi load ra form để hiển thị/chỉnh sửa: convert UTC → local (OS timezone).
 *
 * @since 2.0.5
 */
class AssessmentModel extends AdminModel
{
	/** @var string[] Các trường DATETIME cần convert UTC ↔ local. */
	private const DATETIME_FIELDS = ['registration_start', 'registration_end'];

	/**
	 * @inheritDoc
	 * @since 2.0.5
	 */
	public function getTable($name = 'Assessment', $prefix = 'Administrator', $options = [])
	{
		return parent::getTable($name, $prefix, $options);
	}

	// =========================================================================
	// Load — convert UTC → local khi hiển thị lên edit form
	// =========================================================================

	/**
	 * Load một kỳ sát hạch.
	 *
	 * Override để convert các trường DATETIME từ UTC (DB) → local time (OS timezone)
	 * trước khi bind vào form, đảm bảo người dùng thấy đúng giờ địa phương.
	 *
	 * @param  int|null  $pk
	 * @return stdClass|bool
	 * @since  2.0.5
	 */
	public function getItem($pk = null): stdClass|bool
	{
		$item = parent::getItem($pk);

		if ($item === false || empty($item->id)) {
			return $item; // Bản ghi mới hoặc lỗi — không cần convert
		}

		foreach (self::DATETIME_FIELDS as $field) {
			if (!empty($item->$field)) {
				// Giá trị trong DB là UTC → chuyển sang local time để hiển thị trên form
				$item->$field = DatetimeHelper::convertToLocalTime($item->$field);
			}
		}

		return $item;
	}

	// =========================================================================
	// Save — convert local → UTC khi ghi xuống DB
	// =========================================================================

	/**
	 * Lưu một kỳ sát hạch.
	 *
	 * Override để convert các trường DATETIME từ local time (người dùng nhập,
	 * theo OS timezone) → UTC trước khi ghi xuống DB.
	 *
	 * @param  array  $data  Dữ liệu từ form (jform).
	 * @return bool
	 * @since  2.0.5
	 */
	public function save($data): bool
	{
		foreach (self::DATETIME_FIELDS as $field) {
			if (!empty($data[$field])) {
				// Người dùng nhập theo giờ hệ thống → chuyển sang UTC để lưu DB
				$data[$field] = DatetimeHelper::convertToUtc($data[$field]);
			}
		}

		return parent::save($data);
	}
}
