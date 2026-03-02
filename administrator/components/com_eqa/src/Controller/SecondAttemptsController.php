<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Controller\AdminController;
use Kma\Component\Eqa\Administrator\Model\SecondAttemptsModel;
use Kma\Library\Kma\Helper\ComponentHelper;

class SecondAttemptsController extends AdminController {
	/**
	 * Làm mới danh sách thí sinh thi lần hai.
	 *
	 * Phương thức này thực hiện việc đồng bộ bảng #__eqa_secondattempts với
	 * dữ liệu hiện tại: loại bỏ các trường hợp không còn hợp lệ và bổ sung
	 * các trường hợp mới, đồng thời bảo toàn thông tin đóng phí đã có.
	 *
	 * @return void
	 * @since 2.0.2
	 */
	public function refresh(): void
	{
		try {
			// Kiểm tra token chống CSRF
			$this->checkToken();

			// Kiểm tra quyền
			if (!$this->app->getIdentity()->authorise('core.edit', $this->option)) {
				throw new Exception('Bạn không có quyền thực hiện chức năng này');
			}

			/** @var SecondAttemptsModel $model */
			$model  = ComponentHelper::getMVCFactory()->createModel('SecondAttempts');
			$result = $model->refresh();

			$msg = sprintf(
				'Làm mới thành công! Đã xóa %d trường hợp lỗi thời, thêm mới %d trường hợp.',
				$result['removed'],
				$result['added']
			);
			$this->setMessage($msg, 'success');

		} catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
		}

		$this->setRedirect(
			Route::_('index.php?option=com_eqa&view=secondattempts', false)
		);
	}

	/**
	 * Đánh dấu "Đã nộp phí" cho các bản ghi được chọn.
	 *
	 * @return void
	 * @since 2.0.2
	 */
	public function markPaymentCompleted(): void
	{
		$this->updatePaymentStatus(true);
	}

	/**
	 * Xử lý chung cho 2 tác vụ cập nhật trạng thái thanh toán.
	 *
	 * @param  bool  $targetValue  TRUE = Đã nộp phí; FALSE = Chưa nộp phí.
	 * @return void
	 * @since 2.0.2
	 */
	private function updatePaymentStatus(bool $targetValue): void
	{
		$redirectUrl = Route::_('index.php?option=com_eqa&view=secondattempts', false);

		try {
			$this->checkToken();

			if (!$this->app->getIdentity()->authorise('core.edit', $this->option)) {
				throw new Exception('Bạn không có quyền thực hiện chức năng này');
			}

			$ids = (array) $this->input->post->get('cid', [], 'int');
			$ids = array_filter($ids);
			if (empty($ids)) {
				throw new Exception('Không có trường hợp nào được chọn');
			}

			/** @var SecondAttemptsModel $model */
			$model  = ComponentHelper::getMVCFactory()->createModel('SecondAttempts');
			$result = $targetValue
				? $model->setPaymentCompleted($ids)
				: $model->setPaymentIncomplete($ids);

			$changed = $result['changed'];
			$skipped = $result['skipped'];
			$codes   = $result['changedLearnerCodes'];

			if ($changed === 0) {
				// Không có bản ghi nào thỏa điều kiện để thay đổi
				$msg = $targetValue
					? 'Không có trường hợp nào chuyển sang "Đã nộp phí" (có thể tất cả đã ở trạng thái này hoặc không yêu cầu đóng phí).'
					: 'Không có trường hợp nào chuyển về "Chưa nộp phí" (có thể tất cả đã ở trạng thái này hoặc không yêu cầu đóng phí).';
				$this->setMessage($msg, 'warning');
			} else {
				$codeList = implode(', ', $codes);
				$msg = $targetValue
					? sprintf('Đã chuyển <b>%d</b> trường hợp sang "Đã nộp phí": %s', $changed, $codeList)
					: sprintf('Đã chuyển <b>%d</b> trường hợp về "Chưa nộp phí": %s', $changed, $codeList);

				if ($skipped > 0) {
					$msg .= sprintf('<br>Có <b>%d</b> trường hợp không thay đổi trạng thái (không đủ điều kiện).', $skipped);
				}

				$this->setMessage($msg, 'success');
			}

		} catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
		}

		$this->setRedirect($redirectUrl);
	}

	/**
	 * Thu hồi trạng thái "Đã nộp phí" — chuyển về "Chưa nộp phí".
	 *
	 * @return void
	 * @since 2.0.2
	 */
	public function markPaymentIncomplete(): void
	{
		$this->updatePaymentStatus(false);
	}
}
