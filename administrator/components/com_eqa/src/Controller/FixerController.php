<?php
namespace Kma\Component\Eqa\Administrator\Controller;
require_once JPATH_ROOT.'/vendor/autoload.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Enum\ExamType;
use Kma\Component\Eqa\Administrator\Extension\EqaComponent;
use Kma\Library\Kma\Controller\FormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Service\LogService;
use Joomla\CMS\User\User;

defined('_JEXEC') or die();
class FixerController extends FormController
{
	/**
	 * Mật khẩu mặc định cấp cho tài khoản employee mới (chỉ dùng ở môi trường dev).
	 */
	private const DEV_PASSWORD = '123ABCdef';

	/**
	 * ID của user group "Registered" trong Joomla.
	 */
	private const DEFAULT_GROUP_ID = 2;

	public function fix(): void
	{
		$this->initEmployeeAccounts();
	}
	/**
	 * Sinh code/email cho #__eqa_employees và tạo tài khoản #__users tương ứng nếu chưa có.
	 *
	 * Chạy qua URL: index.php?option=com_eqa&task=fixer.initEmployeeAccounts
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function initEmployeeAccounts(): void
	{
		// Safety guard: chỉ nên chạy khi Debug đang bật (môi trường dev).
		if (!JDEBUG) {
			$this->app->enqueueMessage(
				'initEmployeeAccounts() chỉ nên chạy trên môi trường dev (Site Debug đang tắt).',
				'warning'
			);
		}

		$db = DatabaseHelper::getDatabaseDriver();

		try {
			$filledRows = $this->generateEmployeeCodesAndEmails($db);
			$result     = $this->createMissingUserAccounts($db);

			$this->app->enqueueMessage(
				sprintf(
					'Đã sinh code/email cho %d employee; tạo mới %d tài khoản, bỏ qua %d (đã tồn tại username hoặc email).',
					$filledRows,
					$result['created'],
					$result['skipped']
				),
				'message'
			);

			foreach ($result['errors'] as $errorMessage) {
				$this->app->enqueueMessage($errorMessage, 'error');
			}
		} catch (\Throwable $e) {
			$this->app->enqueueMessage('Lỗi khi khởi tạo tài khoản employee: ' . $e->getMessage(), 'error');
		}

		$this->setRedirect('index.php?option=com_eqa');
	}

	/**
	 * Điền code/email cho các employee đang để trống (không ghi đè giá trị đã có).
	 *
	 * code  = "HVM{id}"  với {id} là 4 chữ số (LPAD).
	 * email = "hvm{id}@actvn.edu.vn".
	 *
	 * @param   DatabaseDriver  $db  Database driver.
	 *
	 * @return  int  Số dòng bị ảnh hưởng.
	 *
	 * @since   1.0.0
	 */
	private function generateEmployeeCodesAndEmails($db): int
	{
		$id    = $db->quoteName('id');
		$code  = $db->quoteName('code');
		$email = $db->quoteName('email');
		$empty = $db->quote('');

		// Các biểu thức sinh giá trị (toàn literal do ta kiểm soát → an toàn injection).
		$codeExpr  = 'CONCAT(' . $db->quote('HVM') . ', LPAD(' . $id . ', 4, ' . $db->quote('0') . '))';
		$emailExpr = 'CONCAT(' . $db->quote('hvm') . ', LPAD(' . $id . ', 4, ' . $db->quote('0') . '), '
			. $db->quote('@actvn.edu.vn') . ')';

		$query = $db->getQuery(true)
			->update($db->quoteName('#__eqa_employees'))
			->set($code . ' = IF(' . $code . ' IS NULL OR ' . $code . ' = ' . $empty . ', ' . $codeExpr . ', ' . $code . ')')
			->set($email . ' = IF(' . $email . ' IS NULL OR ' . $email . ' = ' . $empty . ', ' . $emailExpr . ', ' . $email . ')')
			->where('(' . $code . ' IS NULL OR ' . $code . ' = ' . $empty . ') OR ('
				. $email . ' IS NULL OR ' . $email . ' = ' . $empty . ')');

		$db->setQuery($query)->execute();

		return (int) $db->getAffectedRows();
	}

	/**
	 * Tạo tài khoản #__users cho employee chưa có tài khoản.
	 *
	 * Chỉ tạo khi cả username (= code) lẫn email đều chưa tồn tại trong #__users.
	 * Tài khoản được tạo ở trạng thái đã kích hoạt (block = 0) và gán vào group Registered.
	 *
	 * @param   DatabaseDriver  $db  Database driver.
	 *
	 * @return  array{created:int, skipped:int, errors:string[]}
	 *
	 * @since   1.0.0
	 */
	private function createMissingUserAccounts(DatabaseDriver $db): array
	{
		// 1. Lấy các employee đã có đủ code + email.
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'code', 'email', 'lastname', 'firstname']))
			->from($db->quoteName('#__eqa_employees'))
			->where($db->quoteName('code') . ' IS NOT NULL')
			->where($db->quoteName('code') . ' <> ' . $db->quote(''))
			->where($db->quoteName('email') . ' IS NOT NULL')
			->where($db->quoteName('email') . ' <> ' . $db->quote(''));

		$employees = $db->setQuery($query)->loadObjectList();

		if (empty($employees)) {
			return ['created' => 0, 'skipped' => 0, 'errors' => []];
		}

		// 2. Nạp toàn bộ username/email hiện có, chuẩn hoá lower-case.
		//    So sánh trong PHP giúp tránh lỗi "Illegal mix of collations" và mô phỏng
		//    đúng đặc tính case-insensitive của unique index trên #__users.
		$usernameSet = array_flip(array_map(
			'strtolower',
			$db->setQuery(
				$db->getQuery(true)->select($db->quoteName('username'))->from($db->quoteName('#__users'))
			)->loadColumn()
		));

		$emailSet = array_flip(array_map(
			'strtolower',
			$db->setQuery(
				$db->getQuery(true)->select($db->quoteName('email'))->from($db->quoteName('#__users'))
			)->loadColumn()
		));

		$created = 0;
		$skipped = 0;
		$errors  = [];

		foreach ($employees as $employee) {
			$usernameKey = strtolower($employee->code);
			$emailKey    = strtolower($employee->email);

			// Chỉ tạo khi cả username lẫn email đều chưa tồn tại.
			if (isset($usernameSet[$usernameKey]) || isset($emailSet[$emailKey])) {
				$skipped++;
				continue;
			}

			// Tên hiển thị = CONCAT_WS(' ', lastname, firstname); fallback về code nếu cả hai rỗng.
			$nameParts = array_filter(
				[$employee->lastname, $employee->firstname],
				static fn ($part): bool => $part !== null && trim((string) $part) !== ''
			);
			$displayName = trim(implode(' ', $nameParts)) ?: $employee->code;

			$user = new User();

			// QUAN TRỌNG: bind(&$array) nhận tham số theo tham chiếu,
			// nên phải truyền một BIẾN, không được truyền array literal.
			$data = [
				'name'      => $displayName,
				'username'  => $employee->code,
				'email'     => $employee->email,
				'password'  => self::DEV_PASSWORD,
				'password2' => self::DEV_PASSWORD,
				'block'     => 0,
				'groups'    => [self::DEFAULT_GROUP_ID],
			];

			if (!$user->bind($data) || !$user->save()) {
				$errors[] = sprintf(
					'Employee #%d (%s): %s',
					$employee->id,
					$employee->code,
					$user->getError() ?: 'bind/save thất bại'
				);
				continue;
			}

			// "Giữ chỗ" key vừa dùng để tránh trùng trong cùng batch.
			$usernameSet[$usernameKey] = true;
			$emailSet[$emailKey]       = true;
			$created++;
		}

		return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
	}
}
