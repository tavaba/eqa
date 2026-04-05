<?php

namespace Kma\Library\Kma\Helper;

defined('_JEXEC') or die();

use Exception;
use Joomla\Database\DatabaseDriver;

/**
 * Helper sinh mã thanh toán (payment code) ngẫu nhiên, duy nhất.
 *
 * @since 2.0.7
 */
final class PaymentCodeHelper
{
	/**
	 * Ký tự được phép dùng trong payment code.
	 */
	private const string CHARSET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

	/**
	 * Độ dài payment code.
	 */
	private const int CODE_LENGTH = 8;

	/**
	 * Sinh một payment code ngẫu nhiên 8 ký tự [A-Z0-9],
	 * đảm bảo không trùng với tập codes đã tồn tại trong bảng/cột chỉ định.
	 *
	 * @param  DatabaseDriver  $db         Database driver.
	 * @param  string          $table      Tên bảng (có prefix #__), ví dụ: '#__eqa_regradings'.
	 * @param  string          $column     Tên cột payment_code, ví dụ: 'payment_code'.
	 *
	 * @return string  Payment code duy nhất 8 ký tự.
	 * @throws Exception  Nếu random_int() gặp lỗi.
	 *
	 * @since 2.0.7
	 */
	public static function generateUnique(
		DatabaseDriver $db,
		string $table,
		string $column = 'payment_code'
	): string {
		// Load tập codes đã tồn tại trong bảng
		$query = $db->getQuery(true)
			->select($db->quoteName($column))
			->from($db->quoteName($table))
			->where($db->quoteName($column) . ' IS NOT NULL');
		$db->setQuery($query);
		$existingCodes = array_flip($db->loadColumn());

		return self::generateUniqueFromSet($existingCodes);
	}

	/**
	 * Sinh payment code không trùng với một tập codes đã cho.
	 * Dùng khi cần sinh nhiều codes trong cùng một vòng lặp
	 * (tập $existingCodes được caller tự quản lý và cập nhật).
	 *
	 * @param  array<string, mixed>  $existingCodes  Map (flip) các code đã dùng.
	 *
	 * @return string
	 * @throws Exception
	 *
	 * @since 2.0.7
	 */
	public static function generateUniqueFromSet(array $existingCodes): string
	{
		$charsetLen = strlen(self::CHARSET);

		do {
			$code = '';
			for ($i = 0; $i < self::CODE_LENGTH; $i++) {
				$code .= self::CHARSET[random_int(0, $charsetLen - 1)];
			}
		} while (isset($existingCodes[$code]));

		return $code;
	}
}