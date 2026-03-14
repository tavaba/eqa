<?php

namespace Kma\Component\Eqa\Site\Controller;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Site\Model\AssessmentPortalModel;

/**
 * Controller front-end xử lý đăng ký và hủy đăng ký thi sát hạch.
 *
 * @since 2.0.5
 */
class AssessmentPortalController extends BaseController
{
    /**
     * URL redirect về trang portal sau mỗi tác vụ.
     */
    private function getPortalUrl(): string
    {
        return Route::_('index.php?option=com_eqa&view=assessmentportal', false);
    }

    // =========================================================================
    // Task: register
    // =========================================================================

    /**
     * Xử lý yêu cầu đăng ký tham dự một kỳ sát hạch.
     *
     * POST params: assessment_id (int)
     *
     * @since 2.0.5
     */
    public function register(): void
    {
        $this->checkToken();
        $redirectUrl = $this->getPortalUrl();
        $this->setRedirect($redirectUrl);

        try {
            $learnerCode = GeneralHelper::getSignedInLearnerCode();
            if (empty($learnerCode)) {
                throw new Exception('Bạn cần đăng nhập bằng tài khoản HVSV để thực hiện chức năng này.');
            }

            $assessmentId = $this->input->post->getInt('assessment_id');
            if ($assessmentId <= 0) {
                throw new Exception('Kỳ sát hạch không hợp lệ.');
            }

            /** @var AssessmentPortalModel $model */
            $model = $this->getModel('AssessmentPortal');
            $model->register($assessmentId, $learnerCode);

            $this->setMessage('Đăng ký thành công! Vui lòng thực hiện nộp phí theo hướng dẫn và kiểm tra lại trạng thái sau 1–2 ngày làm việc.', 'success');

        } catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }
    }

    // =========================================================================
    // Task: cancel
    // =========================================================================

    /**
     * Xử lý yêu cầu hủy đăng ký thi sát hạch.
     *
     * POST params: assessment_id (int)
     *
     * Lưu ý: việc hiển thị confirmation message và cảnh báo về tiền đã nộp
     * được thực hiện ở phía client (modal Bootstrap trong template).
     * Controller chỉ thực thi hủy sau khi người học đã xác nhận.
     *
     * @since 2.0.5
     */
    public function cancel(): void
    {
        $this->checkToken();
        $redirectUrl = $this->getPortalUrl();
        $this->setRedirect($redirectUrl);

        try {
            $learnerCode = GeneralHelper::getSignedInLearnerCode();
            if (empty($learnerCode)) {
                throw new Exception('Bạn cần đăng nhập bằng tài khoản HVSV để thực hiện chức năng này.');
            }

            $assessmentId = $this->input->post->getInt('assessment_id');
            if ($assessmentId <= 0) {
                throw new Exception('Kỳ sát hạch không hợp lệ.');
            }

            /** @var AssessmentPortalModel $model */
            $model = $this->getModel('AssessmentPortal');
            $model->cancel($assessmentId, $learnerCode);

            $this->setMessage('Đã hủy đăng ký thành công.', 'success');

        } catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }
    }
}
