<?php
namespace Kma\Component\Kmail\Administrator\Controller;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Controller\AdminController;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Service\MailService;
use Joomla\CMS\Factory;

/**
 * AdminController cho view Campaigns của com_kmail.
 *
 * Xử lý các action trên danh sách campaign:
 *   - cancelCampaign : hủy một campaign đang ở trạng thái có thể hủy
 *
 * Các task display (default, log) được xử lý tự động bởi Joomla
 * thông qua HtmlView — không cần khai báo riêng.
 *
 * @since 1.0.0
 */
class CampaignsController extends AdminController
{
    private const LIST_URL = 'index.php?option=com_kmail&view=campaigns';

    /**
     * Hủy một campaign đang ở trạng thái có thể hủy (Pending).
     *
     * Đây là hành động nghiệp vụ có side effect (cập nhật DB),
     * khác hoàn toàn với task 'cancel' tiêu chuẩn của Joomla
     * FormController (vốn chỉ redirect về list mà không thay đổi dữ liệu).
     *
     * Request params (GET hoặc POST):
     *   - cid[] (int[]) : danh sách campaign_id cần hủy
     *                     (chỉ cho phép 1 campaign mỗi lần)
     *
     * @return void
     * @since  1.0.0
     */
    public function cancelCampaign(): void
    {
        $this->setRedirect(Route::_(self::LIST_URL, false));

        try {
	        $this->checkToken();
            if (!$this->app->getIdentity()->authorise('core.manage', 'com_kmail')) {
                throw new Exception('Bạn không có quyền thực hiện chức năng này.');
            }

            $cid = array_values(array_filter(
                (array) $this->input->get('cid', [], 'int')
            ));

            if (empty($cid)) {
                throw new Exception('Chưa chọn chiến dịch nào.');
            }

            if (count($cid) > 1) {
                throw new Exception('Mỗi lần chỉ được hủy 1 chiến dịch.');
            }

            $campaignId = (int) $cid[0];

            /** @var MailService $mailService */
            $mailService = ComponentHelper::getMailService();
            $mailService->cancelCampaign($campaignId);

            $this->setMessage(sprintf('Đã hủy chiến dịch #%d thành công.', $campaignId));
        }
        catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }
    }
}
