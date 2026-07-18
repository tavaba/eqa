<?php
namespace Kma\Component\Eqa\Site\View\EmployeeMonitorings;   //Must end with the View Name
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Site\Model\EmployeeMonitoringsModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

/**
 * View front-end: cán bộ xem lại lịch sử coi thi, coi thi kiêm chấm thi
 * và giám sát của bản thân, theo cấu trúc master-detail:
 *  - Danh sách chính (có phân trang): thống kê số lượt theo từng kỳ thi;
 *    tên kỳ thi bấm được để chọn kỳ thi cần xem chi tiết;
 *  - Bảng phụ: chi tiết các lượt của kỳ thi đang chọn.
 *
 * @since 2.1.5
 */
class HtmlView extends ItemsHtmlView
{
	protected ?string $listModelName = 'EmployeeMonitorings';
	protected ?object $employee = null;
	protected $selectedExamseason = null;
	protected array $details = [];
	protected ?string $errorMessage = null;

	protected function configureItemFieldsForLayoutDefault(): void
	{
		$option = new ListLayoutItemFields();
		$option->sequence = ListLayoutItemFields::defaultFieldSequence();
		$option->customFieldset1 = [];

		$f = new ListLayoutItemFieldOption('examseasonLink', 'Kỳ thi');
		$f->printRaw = true;    //Được chuẩn bị dưới dạng HTML link chọn kỳ thi
		$option->customFieldset1[] = $f;

		$option->customFieldset1[] = new ListLayoutItemFieldOption('monitorCount', 'Số lượt coi thi', false, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('monitorExaminerCount', 'Số lượt coi thi kiêm chấm thi', false, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('supervisorCount', 'Số lượt giám sát', false, false, 'text-center');
		$this->itemFields = $option;
	}

	protected function prepareDataForLayoutDefault(): void
	{
		try
		{
			/**
			 * Tạo model từ backend
			 * @var EmployeeMonitoringsModel $model
			 */
			$model = $this->getModel();

			// Xác định cán bộ từ phiên đăng nhập (không nhận employeeId từ request,
			// admin/superuser không có đặc quyền)
			$employeeId = GeneralHelper::getSignedInEmployeeId();
			if (empty($employeeId))
				throw new Exception('Không xác định được cán bộ. Hãy đăng nhập bằng tài khoản có email trùng với email cán bộ trong hệ thống.');
			$model->setState('filter.employee_id', $employeeId);

			// Kiểm tra quyền
			if (!$model->canViewList())
				throw new Exception('Bạn không có quyền xem thông tin này');

			// Gọi phương thức lớp cha (load danh sách thống kê, filter form, pagination)
			parent::prepareDataForLayoutDefault();

			// Thông tin cán bộ
			$employeeInfos = DatabaseHelper::getEmployeeInfos([$employeeId], false);
			$this->employee = $employeeInfos[$employeeId] ?? null;

			// Kỳ thi đang chọn và chi tiết các lượt của kỳ thi đó
			$selectedExamseasonId = $model->getSelectedExamseasonId();
			if (!empty($selectedExamseasonId))
			{
				$this->selectedExamseason = DatabaseHelper::getExamseasonInfo($selectedExamseasonId);
				$this->details = $model->getDetails($employeeId, $selectedExamseasonId);

				// Tiền xử lý chi tiết: thời gian Local Time (DB lưu UTC), placeholder
				foreach ($this->details as $detail)
				{
					$detail->startText = !empty($detail->start)
						? HTMLHelper::_('date', $detail->start, 'd/m/Y H:i')
						: '';
					if (empty($detail->roomCode))
						$detail->roomCode = '—';
					if (empty($detail->examroomName))
						$detail->examroomName = '(Giám sát chung)';


					// Cột 'Môn thi': 1 môn hiển thị tên; >=2 môn đánh số thứ tự
					if (empty($detail->examNames))
					{
						$detail->examsHtml = '—';
					}
					elseif (count($detail->examNames) === 1)
					{
						$detail->examsHtml = htmlspecialchars($detail->examNames[0]);
					}
					else
					{
						$parts = [];
						foreach ($detail->examNames as $i => $examName)
							$parts[] = ($i + 1) . '. ' . htmlspecialchars($examName);
						$detail->examsHtml = implode('<br>', $parts);
					}
				}
			}

			// Tiền xử lý danh sách thống kê: tên kỳ thi là link chọn kỳ thi;
			// kỳ thi đang chọn được đánh dấu
			if (!empty($this->layoutData) && !empty($this->layoutData->items))
			{
				foreach ($this->layoutData->items as &$item)
				{
					$nameSafe = htmlspecialchars($item->examseasonName);
					if (!empty($selectedExamseasonId) && (int) $item->examseasonId === $selectedExamseasonId)
					{
						$item->examseasonLink = '<b>' . $nameSafe . '</b>'
							. ' <span class="badge bg-primary">đang xem</span>';
					}
					else
					{
						$item->examseasonLink = '<a href="#" title="Xem chi tiết kỳ thi này"'
							. ' onclick="return eqaSelectExamseason(' . (int) $item->examseasonId . ');">'
							. $nameSafe . '</a>';
					}
				}
				unset($item);
			}
		}
		catch (Exception $e)
		{
			$this->errorMessage = $e->getMessage();
		}
	}

	protected function addToolbarForLayoutDefault(): void
	{
		ToolbarHelper::title('Lịch sử coi thi, giám sát của bản thân');
		if ($this->errorMessage)
		{
			ToolbarHelper::back('');
			ToolbarHelper::render();
		}
	}
}
