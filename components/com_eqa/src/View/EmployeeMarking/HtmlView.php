<?php
namespace Kma\Component\Eqa\Site\View\EmployeeMarking;   //Must end with the View Name
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Kma\Component\Eqa\Administrator\Base\ItemHtmlView;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Site\Model\EmployeeMarkingModel;
use Kma\Library\Kma\Helper\ComponentHelper;

/**
 * View front-end: chi tiết kết quả chấm thi của một môn thi.
 * Hiển thị đầy đủ danh sách thí sinh của môn thi (đã ráp phách với bài thi viết),
 * highlight các bài do chính cán bộ đang đăng nhập chấm, kèm bảng các bài
 * chấm phúc khảo của cán bộ (nếu có).
 *
 * Chỉ hiển thị khi môn thi ở trạng thái 'Đã có đủ điểm thi' trở lên
 * và cán bộ có tham gia chấm môn thi này.
 *
 * @since 2.1.5
 */
class HtmlView extends ItemHtmlView
{
	protected ?object $exam = null;
	protected ?object $employee = null;
	protected array $examinees = [];
	protected array $regradings = [];
	protected ?string $errorMessage = null;

	protected function prepareDataForLayoutDefault(): void
	{
		try
		{
			$app = Factory::getApplication();

			/**
			 * @var EmployeeMarkingModel $model
			 */
			$model = ComponentHelper::createModel('EmployeeMarking');

			// Xác định môn thi
			$examId = $app->input->getInt('exam_id');
			if (empty($examId))
				throw new Exception('Không xác định được môn thi');

			// Xác định cán bộ từ phiên đăng nhập (admin/superuser không có đặc quyền)
			$employeeId = GeneralHelper::getSignedInEmployeeId();
			if (empty($employeeId))
				throw new Exception('Không xác định được cán bộ. Hãy đăng nhập bằng tài khoản có email trùng với email cán bộ trong hệ thống.');

			// Kiểm tra điều kiện xem chi tiết
			$error = null;
			if (!$model->canViewDetail($examId, $employeeId, $error))
				throw new Exception($error ?? 'Bạn không có quyền xem thông tin này');

			// Nạp dữ liệu
			$this->exam = DatabaseHelper::getExamInfo($examId);
			$employeeInfos = DatabaseHelper::getEmployeeInfos([$employeeId], false);
			$this->employee = $employeeInfos[$employeeId] ?? null;
			$this->examinees = $model->getExaminees($examId, $employeeId);
			$this->regradings = $model->getRegradings($examId, $employeeId);
		}
		catch (Exception $e)
		{
			$this->errorMessage = $e->getMessage();
		}
	}

	protected function addToolbarForLayoutDefault(): void
	{
		ToolbarHelper::title('Chi tiết kết quả chấm thi');
		if ($this->errorMessage)
		{
			ToolbarHelper::back('');
			ToolbarHelper::render();
		}
	}
}
