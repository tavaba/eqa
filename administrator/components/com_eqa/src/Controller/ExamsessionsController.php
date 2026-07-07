<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();

require_once JPATH_ROOT.'/vendor/autoload.php';

use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Enum\TestType;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use Kma\Library\Kma\Controller\AdminController;
use Kma\Library\Kma\Helper\DatetimeHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use stdClass;
class ExamsessionsController extends AdminController {
	/**
	 * Xuất thông tin các phòng thi của các ca thi được chọn ra file Excel.
	 * Mỗi ca thi được ghi vào một sheet riêng, tên sheet là tên ca thi.
	 *
	 * @return  void
	 * @since   2.1.4
	 */
	public function exportExamrooms(): void
	{
		//Check token
		$this->checkToken();

		//Set redirect về list view cho mọi trường hợp lỗi
		$this->setRedirect(Route::_('index.php?option=com_eqa&view=examsessions', false));

		//Check permission
		if (!$this->app->getIdentity()->authorise('core.manage', $this->option)) {
			$this->setMessage('Bạn không có quyền truy cập chức năng này', 'error');
			return;
		}

		//Lấy danh sách ca thi được chọn
		$examsessionIds = (array) $this->input->post->get('cid', [], 'int');
		$examsessionIds = array_filter(array_map('intval', $examsessionIds));
		if (empty($examsessionIds)) {
			$this->setMessage('Hãy chọn ít nhất một ca thi', 'error');
			return;
		}

		//Load các ca thi, sắp xếp theo thời gian bắt đầu tăng dần
		$db = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'name', 'start']))
			->from($db->quoteName('#__eqa_examsessions'))
			->where($db->quoteName('id') . ' IN (' . implode(',', $examsessionIds) . ')')
			->order($db->quoteName('start'));
		$db->setQuery($query);
		$examsessions = $db->loadObjectList();
		if (empty($examsessions)) {
			$this->setMessage('Không tìm thấy thông tin ca thi', 'error');
			return;
		}

		//Build spreadsheet: mỗi ca thi một sheet
		$spreadsheet = new Spreadsheet();
		$spreadsheet->removeSheetByIndex(0);
		$usedSheetTitles = [];
		foreach ($examsessions as $examsession) {
			//Chuyển thời gian bắt đầu từ UTC sang local time
			$examsession->start = DatetimeHelper::convertToLocalTime($examsession->start);

			//Chuẩn bị dữ liệu các phòng thi của ca thi
			$examroomRows = [];
			$examrooms = DatabaseHelper::getExamsessionExamrooms($examsession->id);
			foreach ($examrooms as $examroom) {
				$info = DatabaseHelper::getExamroomInfo($examroom->id);
				if (empty($info)) {
					continue;
				}

				$examroomRow = new stdClass();
				$examroomRow->name = $info->name;
				if ($info->isAssessmentRoom) {
					//Phòng thi sát hạch: cột 'Môn thi' hiển thị tên kỳ sát hạch
					$examroomRow->exams = !empty($info->assessmentTitle) ? [$info->assessmentTitle] : [];
				} else {
					$examroomRow->exams = DatabaseHelper::getExamCodesAndNames($info->examIds ?? []);
				}
				$examroomRow->testtype = isset($info->testtype) && $info->testtype !== null
					? TestType::from((int) $info->testtype)->getLabel()
					: '';
				$examroomRow->examineeCount = (int) ($info->examineeCount ?? 0);
				$examroomRows[] = $examroomRow;
			}

			//Tên sheet = tên ca thi; nếu trùng thì gắn thêm id để phân biệt
			$sheetTitle = IOHelper::sanitizeSheetTitle($examsession->name, 25);
			if (in_array($sheetTitle, $usedSheetTitles, true)) {
				$sheetTitle = IOHelper::sanitizeSheetTitle($sheetTitle . ' (' . $examsession->id . ')');
			}
			$usedSheetTitles[] = $sheetTitle;

			$sheet = $spreadsheet->createSheet();
			$sheet->setTitle($sheetTitle);
			IOHelper::writeExamsessionExamrooms($sheet, $examsession, $examroomRows);
		}

		//Force download of the Excel file
		if (count($examsessions) === 1) {
			$fileName = 'Phòng thi. ' . $examsessions[0]->name . '.xlsx';
		} else {
			$fileName = 'Danh sách phòng thi (' . count($examsessions) . ' ca thi).xlsx';
		}
		IOHelper::sendHttpXlsx($spreadsheet, $fileName);
		exit();
	}
}
