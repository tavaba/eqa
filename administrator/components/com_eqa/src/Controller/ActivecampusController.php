<?php

namespace Kma\Component\Eqa\Administrator\Controller;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Kma\Component\Eqa\Administrator\Service\CampusService;
use Kma\Library\Kma\Helper\ComponentHelper;

/**
 * Controller xử lý việc chuyển 'cơ sở đào tạo đang làm việc' (active campus).
 *
 * Task: index.php?option=com_eqa&task=activecampus.set
 *
 * Đây là controller độc lập (không gắn với một view cụ thể) vì dropdown chuyển
 * cơ sở xuất hiện trên nhiều view khác nhau.
 *
 * @since 2.1.6
 */
class ActivecampusController extends BaseController
{
    /**
     * Đặt cơ sở đào tạo đang làm việc rồi quay lại trang trước đó.
     *
     * Request params:
     *   - campus_id (int)    : id cơ sở đào tạo cần chuyển sang
     *   - return    (string) : URL quay lại, đã mã hóa base64
     *
     * @return  void
     * @throws  Exception
     * @since   2.1.6
     */
    public function set(): void
    {
        $returnUrl = $this->resolveReturnUrl();
        $this->setRedirect($returnUrl);

        try {
            $this->checkToken();

            $campusId = $this->input->getInt('campus_id', 0);

            if ($campusId <= 0) {
                throw new Exception('Chưa chọn cơ sở đào tạo.');
            }

            /** @var CampusService $campusService */
            $campusService = ComponentHelper::getComponent()->getCampusService();

            if (!$campusService->setActiveCampusId($campusId)) {
                throw new Exception('Bạn không có quyền làm việc với cơ sở đào tạo này.');
            }

            $this->setMessage(sprintf(
                'Đang làm việc với: %s.',
                $campusService->getCampusName($campusId)
            ));
        } catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }
    }

    /**
     * Xác định URL quay lại sau khi chuyển cơ sở đào tạo.
     *
     * Chỉ chấp nhận URL nội bộ để tránh open redirect.
     *
     * @return  string
     * @since   2.1.6
     */
    private function resolveReturnUrl(): string
    {
        $default = Route::_('index.php?option=com_eqa&view=dashboard', false);
        $encoded = $this->input->getBase64('return', '');

        if (empty($encoded)) {
            return $default;
        }

        $url = base64_decode($encoded);

        if (empty($url) || !Uri::isInternal($url)) {
            return $default;
        }

        return Route::_($url, false);
    }
}
