<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();

/**
 * @package     Kma\Component\Eqa\Administrator\Controller
 * @since       2.0.9
 */

use Exception;
use Kma\Component\Eqa\Administrator\Enum\MailContextType;
use Kma\Library\Kma\Controller\MailCampaignsController as BaseMailCampaignsController;

/**
 * Controller quản lý chiến dịch email của com_eqa.
 *
 * Kế thừa toàn bộ logic từ BaseMailCampaignsController (lib_kma).
 * Chỉ override 4 abstract method đặc thù của com_eqa:
 *   - getListUrl()             : URL view mailcampaigns
 *   - getSelectTemplateUrl()   : URL layout selecttemplate
 *   - validateContext()        : dùng MailContextType enum của com_eqa
 *   - checkSendMailPermission(): dùng quyền 'sendmail' của com_eqa
 *
 * Tasks được kế thừa từ base (không cần khai báo lại):
 *   - mailcampaigns.notify
 *   - mailcampaigns.create
 *   - mailcampaigns.cancelCampaign
 *   - mailcampaigns.getTemplatesJson
 *
 * @since 2.0.9
 */
class MailCampaignsController extends BaseMailCampaignsController
{
    // =========================================================================
    // Abstract overrides — bắt buộc
    // =========================================================================

    /**
     * @return string
     * @since  2.0.9
     */
    protected function getListUrl(): string
    {
        return 'index.php?option=com_eqa&view=mailcampaigns';
    }

    /**
     * @param  int     $contextType
     * @param  int     $contextId
     * @param  string  $encodedReturn
     *
     * @return string
     * @since  2.0.9
     */
    protected function getSelectTemplateUrl(
        int    $contextType,
        int    $contextId,
        string $encodedReturn
    ): string {
        return 'index.php?option=com_eqa&view=mailcampaigns&layout=selecttemplate'
            . '&context_type=' . $contextType
            . '&context_id='   . $contextId
            . '&return='       . $encodedReturn;
    }

    /**
     * Validate context_type và context_id theo MailContextType enum của com_eqa.
     *
     * @param  int  $contextType
     * @param  int  $contextId
     *
     * @return void
     * @throws Exception
     * @since  2.0.9
     */
    protected function validateContext(int $contextType, int $contextId): void
    {
        $type = MailContextType::tryFrom($contextType);

        if ($type === null) {
            throw new Exception(
                'Ngữ cảnh gửi email không hợp lệ (context_type = ' . $contextType . ').'
            );
        }

        // Manual context không cần context_id (danh sách lưu trong recipient_filter)
        if ($contextId <= 0 && $type !== MailContextType::Manual) {
            throw new Exception(
                'Không xác định được đối tượng ngữ cảnh (context_id).'
            );
        }
    }

    /**
     * Kiểm tra quyền 'sendmail' trên com_eqa.
     *
     * @return void
     * @throws Exception
     * @since  2.0.9
     */
    protected function checkSendMailPermission(): void
    {
        if (!$this->app->getIdentity()->authorise('sendmail', $this->option)) {
            throw new Exception('Bạn không có quyền gửi thông báo email.');
        }
    }
}
