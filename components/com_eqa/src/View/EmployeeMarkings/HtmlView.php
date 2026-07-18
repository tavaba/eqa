<?php
namespace Kma\Component\Eqa\Site\View\EmployeeMarkings;   //Must end with the View Name
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\DataObject\ExamseasonInfo;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Site\Model\EmployeeMarkingsModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

/**
 * View front-end: cán bộ xem danh sách các môn thi mình đã tham gia chấm
 * (theo kỳ thi) cùng số lượng bài đã chấm ở từng hình thức chấm.
 *
 * @since 2.1.5
 */
class HtmlView extends ItemsHtmlView
{
	protected ?string $listModelName = 'EmployeeMarkings';
	protected ?object $employee = null;
	protected ?ExamseasonInfo $examseason = null;
	protected ?string $errorMessage = null;

	protected function configureItemFieldsForLayoutDefault(): void
	{
		$option = new ListLayoutItemFields();
		$option->sequence = ListLayoutItemFields::defaultFieldSequence();
		$option->customFieldset1 = [];

		$f = new ListLayoutItemFieldOption('examNameLink', 'Môn thi');
		$f->printRaw = true;    //Được chuẩn bị dưới dạng HTML (link nếu được xem chi tiết)
		$option->customFieldset1[] = $f;

		$option->customFieldset1[] = new ListLayoutItemFieldOption('source', 'Hình thức chấm');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('count1Text', 'Số bài chấm 1', false, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('count2Text', 'Số bài chấm 2', false, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('statusLabel', 'Trạng thái môn thi');
		$this->itemFields = $option;
	}

	protected function prepareDataForLayoutDefault(): void
	{
		try
		{
			/**
			 * @var EmployeeMarkingsModel $model
			 */
			$model = $this->getModel();

			// Xác định cán bộ từ phiên đăng nhập
			$employeeId = GeneralHelper::getSignedInEmployeeId();
			if (empty($employeeId))
				throw new Exception('Không xác định được cán bộ. Hãy đăng nhập bằng tài khoản có email trùng với email cán bộ trong hệ thống.');
			$model->setState('filter.employee_id', $employeeId);

			// Kiểm tra quyền
			if (!$model->canViewList())
				throw new Exception('Bạn không có quyền xem thông tin này');

			// Gọi phương thức lớp cha
			parent::prepareDataForLayoutDefault();

			// Thông tin bổ trợ
			$employeeInfos = DatabaseHelper::getEmployeeInfos([$employeeId], false);
			$this->employee = $employeeInfos[$employeeId] ?? null;
			$examseasonId = $model->getSelectedExamseasonId();
			if($examseasonId !== null)
				$this->examseason = DatabaseHelper::getExamseasonInfo($examseasonId);

			// Tiền xử lý: link xem chi tiết + format số lượng
			if (!empty($this->layoutData) && !empty($this->layoutData->items))
			{
				foreach ($this->layoutData->items as &$item)
				{
					$examNameSafe = htmlspecialchars($item->examName);
					if ($item->canViewDetail)
					{
						$url = Route::_('index.php?option=com_eqa&view=employeeMarking&exam_id=' . (int) $item->examId);
						$item->examNameLink = '<a href="' . $url . '">' . $examNameSafe . '</a>';
					}
					else
					{
						$item->examNameLink = '<span class="text-muted"'
							. ' title="Môn thi chưa có đủ điểm thi nên chưa thể xem chi tiết">'
							. $examNameSafe . '</span>';
					}

					// mmproductions.quantity là REAL nên có thể lẻ; bỏ phần .0 thừa
					$item->count1Text = $item->count1 > 0 ? rtrim(rtrim(number_format($item->count1, 1, '.', ''), '0'), '.') : '';
					$item->count2Text = $item->count2 > 0 ? rtrim(rtrim(number_format($item->count2, 1, '.', ''), '0'), '.') : '';
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
		ToolbarHelper::title('Kết quả chấm thi của bản thân');
		if ($this->errorMessage)
		{
			ToolbarHelper::back('');
			ToolbarHelper::render();
		}
	}
}
