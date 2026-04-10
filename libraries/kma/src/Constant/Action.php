<?php
/**
 * @package     Kma\Library\Kma\Constant
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Kma\Library\Kma\Constant;

/**
 * Định nghĩa hằng số để biểu thị các 'action' (hành động của người dùng),
 * trước hết là phục vụ việc ghi log. Lớp cơ sở được định nghĩa trong thư
 * viện lib_kma sử dụng giải giá trị từ 0-1000. Các lớp con cần tránh sử
 * dụng khoảng giá trị này
 * @since       1.0.3
 */
class Action
{
	// CRUD cơ bản (dùng chung, định nghĩa trong thư viện)
	const int CREATE    = 1;
	const int EDIT      = 2;
	const int DELETE    = 3;
	const int PUBLISH   = 4;
	const int UNPUBLISH = 5;
	const int ARCHIVE   = 6;
	const int TRASH     = 7;

	//Một số action thông dụng khác (dùng chung, định nghĩa trong thư viện)
	const int VIEW          = 11;
	const int LIST          = 12;
	const int UPLOAD        = 21;
	const int DOWNLOAD      = 22;
	const int IMPORT        = 23;
	const int EXPORT        = 24;
	const int SET_DEFAULT   = 24;


	//Một số batch action để sử dụng trong trường hợp
	//muốn ghi log tổng hợp thay vì ghi log riêng từng item
	//(dùng chung, định nghĩa trong thư viện)
	const int BATCH_CREATE      = 50;
	const int BATCH_DELETE      = 51;
	const int BATCH_PUBLISH     = 52;
	const int BATCH_UNPUBLISH   = 53;



	/**
	 * Returns the PascalCase label of the constant matching the given value.
	 *
	 * @param   int  $value  The constant value to look up.
	 *
	 * @return  string|null  PascalCase constant name, or null if not found.
	 */
	public static function getLabel(int $value): string|null
	{
		$reflection = new \ReflectionClass(static::class);
		$constants  = $reflection->getConstants();

		foreach ($constants as $name => $constValue) {
			if ($constValue === $value) {
				// Convert UPPER_SNAKE_CASE → PascalCase
				// e.g. ADD_LEARNER_TO_CLASS → AddLearnerToClass
				return str_replace(
					' ',
					'',
					ucwords(strtolower(str_replace('_', ' ', $name)))
				);
			}
		}

		return null;
	}

	/**
	 * Returns an associative array of all defined constants as options.
	 * Kết quả array giữ nguyên thứ tự khai báo của các constants nhờ
	 * ReflectionClass::getConstants() trả về theo thứ tự định nghĩa (PHP 7.1+)
	 * @return  array<int, string>  Array of [const_value => PascalCase label].
	 */
	public static function getOptions(): array
	{
		$reflection = new \ReflectionClass(static::class);
		$constants  = $reflection->getConstants();
		$options    = [];

		foreach ($constants as $name => $value) {
			$options[$value] = static::getLabel($value);
		}

		return $options;
	}
}