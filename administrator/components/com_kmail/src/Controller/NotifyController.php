<?php
namespace Kma\Component\Kmail\Administrator\Controller;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Kma\Library\Kma\Controller\AdminController;
use Kma\Library\Kma\Service\MailService;
use Joomla\CMS\Factory;

/**
 * Controller điều phối luồng gửi thông báo email.
 *
 * Được gọi từ bất kỳ component nào muốn tích hợp email thông báo.
 * Component gọi chỉ cần POST một số tham số và com_kmail lo phần còn lại.
 *
 * Tasks:
 *   - notify  : hiển thị selecttemplate khi có nhiều template phù hợp
 *   - create  : nhận template_id đã chọn, redirect về notify_url của component
 *               kèm template_id — component tự gọi MailService::notify(templateId=X)
 *
 * Tham số đầu vào (POST hoặc GET):
 *   - context_type (int)    : giá trị MailContextType enum
 *   - context_id   (int)    : ID đối tượng ngữ cảnh
 *   - notify_url   (string) : URL base64-encoded của task notify tại component gốc
 *                             Ví dụ: base64('index.php?option=com_eqa&task=mailcampaigns.notify')
 *   - return       (string) : URL base64-encoded để redirect về sau khi hoàn tất
 *
 * @since 1.0.0
 */
class NotifyController extends AdminController
{
    /**
     * Hiển thị layout selecttemplate khi có nhiều template phù hợp.
     *
     * Được gọi từ MailCampaignsController::notify() của component (qua redirect)
     * khi MailService::notify() trả về MailCampaignResult::NeedSelectTemplate.
     *
     * Không xử lý logic — chỉ forward sang HtmlView để render selecttemplate.
     *
     * @return void
     * @since  1.0.0
     */
    public function notify(): void
    {
        // Validate quyền
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_kmail')) {
            $this->setMessage('Bạn không có quyền thực hiện chức năng này.', 'error');
            $this->setRedirect(Route::_('index.php?option=com_kmail', false));
            return;
        }

        // Forward sang HtmlView/notify với layout selecttemplate
        // Toàn bộ tham số (context_type, context_id, notify_url, return)
        // được HtmlView đọc trực tiếp từ request
        $this->display(false);
    }

    /**
     * Nhận template_id đã chọn từ layout selecttemplate,
     * sau đó redirect về notify_url của component gốc kèm template_id.
     *
     * Component gọi lại task notify() của mình với template_id đã chọn.
     * MailService::notify(templateId=X) sẽ tạo campaign và queue ngay,
     * không cần bước đếm template nữa.
     *
     * Request params (POST):
     *   - template_id  (int)    : ID template đã chọn
     *   - context_type (int)    : giá trị MailContextType enum
     *   - context_id   (int)    : ID đối tượng ngữ cảnh
     *   - notify_url   (string) : URL base64 của task notify tại component
     *   - return       (string) : URL base64 để redirect về sau khi xong
     *
     * @return void
     * @since  1.0.0
     */
    public function create(): void
    {
        $this->checkToken();

        try {
            if (!$this->app->getIdentity()->authorise('core.manage', 'com_kmail')) {
                throw new Exception('Bạn không có quyền thực hiện chức năng này.');
            }

            $templateId   = $this->input->post->getInt('template_id');
            $contextType  = $this->input->post->getInt('context_type');
            $contextId    = $this->input->post->getInt('context_id');

            // Dùng getString() thay vì getBase64() vì Joomla Input::getBase64()
            // TỰ DECODE base64 rồi trả về kết quả — không trả về chuỗi base64 gốc.
            // Chúng ta cần giữ nguyên chuỗi base64 để truyền tiếp trong URL.
            $notifyUrlB64 = $this->input->post->getString('notify_url', '');
            $returnB64    = $this->input->post->getString('return', '');

            if ($templateId <= 0) {
                throw new Exception('Chưa chọn template email.');
            }

            // Decode để validate — chỉ chấp nhận URL bắt đầu bằng index.php
            $notifyUrl = $notifyUrlB64 !== '' ? base64_decode($notifyUrlB64) : '';
            if (empty($notifyUrl) || !str_starts_with($notifyUrl, 'index.php')) {
                throw new Exception('Tham số notify_url không hợp lệ.');
            }

            // Redirect về task notify của component kèm template_id đã chọn.
            // Nhúng CSRF token vào URL để notify() có thể checkToken('request').
            // Cần thiết vì đây là GET redirect, không phải POST.
            $token     = Session::getFormToken();
            $targetUrl = Route::_($notifyUrl
                . '&template_id='  . $templateId
                . '&context_type=' . $contextType
                . '&context_id='   . $contextId
                . '&return='       . urlencode($returnB64)
                . '&'              . $token . '=1',
                false
            );

            $this->setRedirect($targetUrl);
        }
        catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');

            // Fallback: quay lại selecttemplate
            $contextType  = $this->input->post->getInt('context_type');
            $contextId    = $this->input->post->getInt('context_id');
            $notifyUrlB64 = $this->input->post->getString('notify_url', '');
            $returnB64    = $this->input->post->getString('return', '');

            $this->setRedirect(Route::_(
                'index.php?option=com_kmail&view=notify&layout=selecttemplate'
                . '&context_type=' . $contextType
                . '&context_id='   . $contextId
                . '&notify_url='   . urlencode($notifyUrlB64)
                . '&return='       . urlencode($returnB64),
                false
            ));
        }
    }
}
