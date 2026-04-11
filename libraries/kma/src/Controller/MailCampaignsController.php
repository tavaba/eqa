<?php
namespace Kma\Library\Kma\Controller;
defined('_JEXEC') or die();

/**
 * @package     Kma.Library.Kma
 * @subpackage  Controller
 *
 * @copyright   (C) 2025 KMA
 * @license     GNU General Public License version 2 or later
 *
 * @since       1.0.3
 */

use Exception;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Model\MailCampaignsModel;

/**
 * Base AdminController cho tính năng gửi email thông báo (mail campaigns).
 *
 * Chứa toàn bộ logic chung:
 *   - notify()            : kích hoạt từ nút trong view ngữ cảnh, điều phối Luồng A/B
 *   - create()            : tạo campaign sau khi người dùng chọn template (Luồng B)
 *   - cancelCampaign()    : hủy nghiệp vụ campaign đang Pending (khác với task 'cancel'
 *                           tiêu chuẩn của Joomla FormController vốn chỉ redirect về list)
 *   - getTemplatesJson()  : AJAX — trả JSON danh sách template theo context_type
 *
 * Lớp con BẮT BUỘC override:
 *   - getListUrl(): string
 *       URL danh sách campaign của component.
 *       Ví dụ: 'index.php?option=com_eqa&view=mailcampaigns'
 *   - getSelectTemplateUrl(int $contextType, int $contextId, string $encodedReturn): string
 *       URL layout chọn template (Luồng B).
 *   - validateContext(int $contextType, int $contextId): void
 *       Validate context_type và context_id theo enum của component.
 *       Ném Exception nếu không hợp lệ.
 *   - checkSendMailPermission(): void
 *       Kiểm tra quyền gửi thông báo theo cơ chế phân quyền của component.
 *       Ném Exception nếu không có quyền.
 *
 * Ví dụ lớp con (com_eqa):
 * -----------------------------------------------------------------------
 *   class MailCampaignsController extends \Kma\Library\Kma\Controller\MailCampaignsController
 *   {
 *       protected function getListUrl(): string
 *       {
 *           return 'index.php?option=com_eqa&view=mailcampaigns';
 *       }
 *
 *       protected function getSelectTemplateUrl(int $contextType, int $contextId, string $encodedReturn): string
 *       {
 *           return 'index.php?option=com_eqa&view=mailcampaigns&layout=selecttemplate'
 *               . '&context_type=' . $contextType
 *               . '&context_id='   . $contextId
 *               . '&return='       . $encodedReturn;
 *       }
 *
 *       protected function validateContext(int $contextType, int $contextId): void
 *       {
 *           if (MailContextType::tryFrom($contextType) === null) {
 *               throw new Exception('Ngữ cảnh không hợp lệ.');
 *           }
 *           if ($contextId <= 0 && $contextType !== MailContextType::Manual->value) {
 *               throw new Exception('Không xác định được đối tượng ngữ cảnh.');
 *           }
 *       }
 *
 *       protected function checkSendMailPermission(): void
 *       {
 *           if (!$this->app->getIdentity()->authorise('sendmail', $this->option)) {
 *               throw new Exception('Bạn không có quyền gửi thông báo email.');
 *           }
 *       }
 *   }
 * -----------------------------------------------------------------------
 *
 * @since 1.0.3
 */
abstract class MailCampaignsController extends AdminController
{
    // =========================================================================
    // Abstract — bắt buộc override ở lớp con
    // =========================================================================

    /**
     * URL danh sách campaign của component.
     * Dùng làm fallback redirect khi không có return URL.
     *
     * @return string  Ví dụ: 'index.php?option=com_eqa&view=mailcampaigns'
     * @since  1.0.3
     */
    abstract protected function getListUrl(): string;

    /**
     * URL layout chọn template (Luồng B — nhiều template phù hợp).
     *
     * @param  int     $contextType    Giá trị int của context type enum
     * @param  int     $contextId      ID đối tượng ngữ cảnh
     * @param  string  $encodedReturn  Return URL đã base64_encode
     *
     * @return string
     * @since  1.0.3
     */
    abstract protected function getSelectTemplateUrl(
        int    $contextType,
        int    $contextId,
        string $encodedReturn
    ): string;

    /**
     * Validate context_type và context_id theo enum của component.
     * Ném Exception nếu không hợp lệ.
     *
     * @param  int  $contextType
     * @param  int  $contextId
     *
     * @return void
     * @throws Exception
     * @since  1.0.3
     */
    abstract protected function validateContext(int $contextType, int $contextId): void;

    /**
     * Kiểm tra quyền gửi thông báo theo cơ chế phân quyền của component.
     * Ném Exception nếu không có quyền.
     *
     * @return void
     * @throws Exception
     * @since  1.0.3
     */
    abstract protected function checkSendMailPermission(): void;

    // =========================================================================
    // Task: notify — kích hoạt từ nút trong view ngữ cảnh
    // =========================================================================

    /**
     * Kích hoạt gửi thông báo từ một view ngữ cảnh.
     *
     * Request params (GET hoặc POST):
     *   - context_type (int)    : giá trị context type enum
     *   - context_id   (int)    : ID đối tượng ngữ cảnh
     *   - return_url   (string) : URL plain-text để redirect về sau khi xong
     *
     * Luồng A (= 1 template): tạo campaign + queue ngay → redirect về return_url.
     * Luồng B (> 1 template): redirect sang layout selecttemplate.
     * Không có template (= 0): báo lỗi → redirect về return_url.
     *
     * @return void
     * @since  1.0.3
     */
    public function notify(): void
    {
        $this->checkToken();

        $contextType = $this->input->getInt('context_type');
        $contextId   = $this->input->getInt('context_id');
        $returnUrl   = $this->resolveReturnUrl();

        $this->setRedirect(Route::_($returnUrl, false));

        try {
            $this->checkSendMailPermission();
            $this->validateContext($contextType, $contextId);

            $model     = $this->getMailCampaignModel();
            $templates = $model->getTemplatesByContextType($contextType);

            if (empty($templates)) {
                throw new Exception(
                    'Không có template email nào phù hợp với ngữ cảnh này. '
                    . 'Vui lòng tạo template trước khi gửi thông báo.'
                );
            }

            if (count($templates) === 1) {
                // Luồng A: 1 template → tạo campaign + queue ngay
                $template   = $templates[0];
                $campaignId = $model->createCampaign(
                    (int) $template->id,
                    $contextType,
                    $contextId
                );

                $this->setMessage(sprintf(
                    'Đã xếp hàng gửi email thông báo (<b>%s</b>). '
                    . 'Email sẽ được gửi đi trong thời gian ngắn.',
                    htmlspecialchars($template->title)
                ));
            }
            else {
                // Luồng B: nhiều template → redirect sang layout selecttemplate
                $selectUrl = Route::_(
                    $this->getSelectTemplateUrl(
                        $contextType,
                        $contextId,
                        base64_encode($returnUrl)
                    ),
                    false
                );
                $this->setRedirect($selectUrl);
            }
        }
        catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }
    }

    // =========================================================================
    // Task: create — xác nhận tạo campaign (Luồng B)
    // =========================================================================

    /**
     * Tạo campaign sau khi người dùng chọn template trong layout selecttemplate.
     *
     * Request params (POST):
     *   - template_id      (int)         : ID template đã chọn
     *   - context_type     (int)         : giá trị context type enum
     *   - context_id       (int)         : ID đối tượng ngữ cảnh
     *   - recipient_filter (string|null) : JSON filter bổ sung (tùy chọn)
     *   - return           (string)      : URL base64-encoded để redirect về
     *
     * @return void
     * @since  1.0.3
     */
    public function create(): void
    {
        $this->checkToken();

        $returnUrl = $this->resolveReturnUrl();
        $this->setRedirect(Route::_($returnUrl, false));

        try {
            $this->checkSendMailPermission();

            $templateId      = $this->input->post->getInt('template_id');
            $contextType     = $this->input->post->getInt('context_type');
            $contextId       = $this->input->post->getInt('context_id');
            $recipientFilter = $this->input->post->getString('recipient_filter') ?: null;

            if ($templateId <= 0) {
                throw new Exception('Chưa chọn template email.');
            }

            $this->validateContext($contextType, $contextId);

            $model      = $this->getMailCampaignModel();
            $campaignId = $model->createCampaign(
                $templateId,
                $contextType,
                $contextId,
                $recipientFilter
            );

            $this->setMessage(sprintf(
                'Đã xếp hàng gửi email thông báo (campaign #%d). '
                . 'Email sẽ được gửi đi trong thời gian ngắn.',
                $campaignId
            ));
        }
        catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }
    }

    // =========================================================================
    // Task: cancelCampaign — hủy nghiệp vụ campaign đang Pending
    // =========================================================================

    /**
     * Hủy vĩnh viễn một campaign đang ở trạng thái Pending.
     *
     * Đây là hành động NGHIỆP VỤ có side effect (ghi DB), khác hoàn toàn với
     * task 'cancel' tiêu chuẩn của Joomla FormController (vốn chỉ redirect về
     * list mà không thay đổi dữ liệu).
     *
     * Request params (GET hoặc POST):
     *   - campaign_id (int) : ID campaign cần hủy
     *
     * @return void
     * @since  1.0.3
     */
    public function cancelCampaign(): void
    {
        $this->checkToken();

        $this->setRedirect(Route::_($this->getListUrl(), false));

        try {
            $this->checkSendMailPermission();

            $campaignId = $this->input->getInt('campaign_id');
            if ($campaignId <= 0) {
                throw new Exception('Không xác định được campaign_id.');
            }

            $model = $this->getMailCampaignModel();
            $model->cancelCampaign($campaignId);

            $this->setMessage(sprintf('Đã hủy campaign #%d thành công.', $campaignId));
        }
        catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }
    }

    // =========================================================================
    // Task: getTemplatesJson — AJAX, trả JSON danh sách template
    // =========================================================================

    /**
     * Trả về danh sách template email phù hợp dưới dạng JSON.
     *
     * Được gọi bởi modal chọn template (Luồng B) khi cần load động.
     *
     * Request params (GET):
     *   - context_type (int) : giá trị context type enum
     *
     * Response (Joomla JsonResponse):
     *   Thành công : {success: true,  data: [{id, title, subject, body}, ...]}
     *   Thất bại   : {success: false, message: "..."}
     *
     * @return void
     * @since  1.0.3
     */
    public function getTemplatesJson(): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');

        try {
            $this->checkSendMailPermission();

            $contextType = $this->input->getInt('context_type');
            if ($contextType <= 0) {
                throw new Exception('context_type không hợp lệ.');
            }

            $model     = $this->getMailCampaignModel();
            $templates = $model->getTemplatesByContextType($contextType);

            echo new JsonResponse($templates);
        }
        catch (Exception $e) {
            echo new JsonResponse(null, $e->getMessage(), true);
        }

        $this->app->close();
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Lấy MailCampaignModel qua ComponentHelper.
     *
     * @return MailCampaignsModel
     * @since  1.0.3
     */
    private function getMailCampaignModel(): MailCampaignsModel
    {
        /** @var MailCampaignsModel $model */
        $model = ComponentHelper::createModel('MailCampaigns');

        return $model;
    }

    /**
     * Lấy return URL từ request theo thứ tự ưu tiên:
     *   1. Tham số 'return' (base64-encoded) — dùng trong Luồng B
     *   2. Tham số 'return_url' (plain text) — dùng trong Luồng A
     *   3. Fallback: getListUrl()
     *
     * Chỉ chấp nhận URL bắt đầu bằng 'index.php' để tránh open redirect.
     *
     * @return string  URL đã decode, chưa qua Route::_()
     * @since  1.0.3
     */
    private function resolveReturnUrl(): string
    {
        // 1. Tham số 'return' (base64)
        $returnB64 = $this->input->getBase64('return', '');
        if ($returnB64 !== '') {
            $decoded = base64_decode($returnB64);
            if ($decoded !== false && str_starts_with($decoded, 'index.php')) {
                return $decoded;
            }
        }

        // 2. Tham số 'return_url' (plain)
        $returnUrl = $this->input->getString('return_url', '');
        if ($returnUrl !== '' && str_starts_with($returnUrl, 'index.php')) {
            return $returnUrl;
        }

        // 3. Fallback
        return $this->getListUrl();
    }
}
