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
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Service\LogService;

defined('_JEXEC') or die();
class FixerController extends FormController
{
	public function fix()
	{
		if(!$this->app->getIdentity()->authorise('core.admin'))
			die('Ha ha ha');
		$this->migrateExamsessionStartToUtc();
	}
	/**
	 * Chuyển đổi giá trị trường 'start' của các bản ghi id <= 334
	 * trong bảng #__eqa_examsessions từ Local Time sang UTC.
	 *
	 * Chỉ thực thi một lần (one-time migration). Sau khi chạy xong,
	 * hãy xóa hoặc vô hiệu hóa function này.
	 *
	 */
	public static function migrateExamsessionStartToUtc(): void
	{
		$db = DatabaseHelper::getDatabaseDriver();

		// 1. Đọc tất cả bản ghi có id <= 334
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'start']))
			->from($db->quoteName('#__eqa_examsessions'))
			->where($db->quoteName('id') . ' <= 334')
			->order($db->quoteName('id') . ' ASC');
		$db->setQuery($query);
		$rows = $db->loadObjectList();

		$converted = 0;
		$skipped   = 0;
		$details   = [];

		foreach ($rows as $row) {
			$original = $row->start;

			// 2. Kiểm tra điều kiện convert (theo đúng logic isConvertible):
			//    - null hoặc rỗng            → bỏ qua
			//    - '0000-00-00 00:00:00'     → bỏ qua
			//    - không khớp YYYY-MM-DD HH:MM:SS → bỏ qua (chỉ có date, không có time)
			if (
				$original === null
				|| $original === ''
				|| $original === '0000-00-00 00:00:00'
				|| !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $original)
			) {
				$skipped++;
				$details[] = "id={$row->id}: BỎ QUA (giá trị '{$original}' không hợp lệ)";
				continue;
			}

			// 3. Convert Local Time → UTC
			$utcValue = DatetimeHelper::convertToUtc($original);

			// 4. Ghi lại vào CSDL
			$updateQuery = $db->getQuery(true)
				->update($db->quoteName('#__eqa_examsessions'))
				->set($db->quoteName('start') . ' = ' . $db->quote($utcValue))
				->where($db->quoteName('id') . ' = ' . (int) $row->id);
			$db->setQuery($updateQuery);
			$db->execute();

			$converted++;
			$details[] = "id={$row->id}: '{$original}' → '{$utcValue}'";
		}

		$res = [
			'converted' => $converted,
			'skipped'   => $skipped,
			'details'   => $details,
		];
		echo '<pre>';
		print_r($res);
		echo '</pre>';
	}
}
