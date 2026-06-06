<?php

namespace Kma\Library\Kma\Enum;

defined('_JEXEC') or die();

/**
 * Giới tính của một cá nhân.
 *
 * Giá trị int được lưu vào cột `gender` (TINYINT) trong các bảng liên quan
 * (người học, người lao động, người được khảo sát...).
 *
 * @since 1.x
 */
enum Gender: int
{
	use EnumHelper;

	case Male   = 1;   // Nam
	case Female = 2;   // Nữ

	/**
	 * Trả về nhãn hiển thị tiếng Việt.
	 *
	 * @return string
	 */
	public function getLabel(): string
	{
		return match ($this) {
			self::Male   => 'Nam',
			self::Female => 'Nữ',
		};
	}
}