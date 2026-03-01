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
}
