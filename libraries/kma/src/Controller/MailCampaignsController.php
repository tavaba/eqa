<?php
namespace Kma\Library\Kma\Controller;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Enum\MailCampaignResult;
use Kma\Library\Kma\Enum\MailContextType;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Model\MailCampaignsModel;
use Kma\Library\Kma\Service\MailService;

/**
 * Controller quản lý chiến dịch email của component.
 * Component phải có model tên 'MailCampaigns' kế thừa
 * lớp cơ sở cùng tên trong lib_kma.
 *
 * Chỉ có một task duy nhất: notify().
 * Toàn bộ logic tạo campaign và queue do MailService::notify() đảm nhận —
 * kể cả khi template_id đã được chọn sẵn từ layout selecttemplate của com_kmail.
 *
 * Luồng khi templateId=null (lần đầu — người dùng nhấn nút "Gửi thông báo"):
 *   notify(templateId=null) → MailService → NeedSelectTemplate (luôn luôn, kể cả 1 template)
 *   → redirect com_kmail/notify/selecttemplate
 *   → người dùng xem danh sách, chọn template (hoặc hủy nếu không có template phù hợp)
 *   → user POST thẳng về: com_foo&task=mailcampaigns.notify&template_id=X
 *   → notify(templateId=X) → MailService → Queued → redirect return_url
 *
 * @since 1.0.3
 */
abstract class MailCampaignsController extends AdminController
{


	/**
	 * Kiểm tra quyền gửi email trên component.
	 *
	 * @throws Exception
	 * @since  1.0.3
	 */
	abstract protected function checkSendMailPermission(int $contextType, int $contextId): void;


	/**
     * Kích hoạt gửi thông báo email.
     *
     * Khi template_id=null (lần gọi đầu tiên từ nút "Gửi thông báo"):
     *   MailService tự xác định template phù hợp.
     *
     * Khi template_id=X (sau khi user chọn từ selecttemplate):
     *   MailService dùng template X ngay, bỏ qua bước đếm template.
     *
     * Request params (POST hoặc GET):
     *   - context_type (int)               : giá trị MailContextType enum
     *   - context_id   (int)               : ID đối tượng ngữ cảnh
     *   - recipient_filter (string|null)   : Quy tắc lọc người nhận
     *   - template_id  (int|null)          : ID template đã chọn (0 = chưa chọn)
     *   - return       (string)            : URL base64-encoded để redirect tới sau khi xong
     *
     * @return void
     * @since  1.0.3
     */
    public function notify(): void
    {
        try {
	        // Token luôn nằm trong POST body:
	        //   - Lần đầu: nút 'Gửi thông báo' submit POST
	        //   - Sau selecttemplate: form POST thẳng từ com_kmail/notify/selecttemplate
	        //     về đây kèm template_id (không qua GET redirect)
	        $this->checkToken();

	        $contextType = $this->input->getInt('context_type',0);
	        $contextId   = $this->input->getInt('context_id',0);
			$recipientFilter = $this->input->getString('recipient_filter');
	        $templateId  = $this->input->getInt('template_id') ?: null;
	        $returnB64   = $this->input->getString('return', '');
	        $returnUrl   = $this->decodeReturnUrl($returnB64);

	        $this->setRedirect(Route::_($returnUrl, false));
	        $this->validateContext($contextType, $contextId);
	        $this->checkSendMailPermission($contextType, $contextId);

            // Resolve recipients — nghiệp vụ đặc thù của model
            /** @var MailCampaignsModel $model */
            $model      = ComponentHelper::createModel('MailCampaigns');
			if(empty($model) || !$model instanceof MailCampaignsModel)
				throw  new Exception('Model "MailCampaigns" không tồn tại hoặc không phải là lớp con của \\Kma\\Library\\Kma\\Model\\MailCampaignsModel');

            $recipients = $model->resolveRecipients($contextType, $contextId, $recipientFilter);

            if (empty($recipients)) {
                throw new Exception('Không có người nhận nào phù hợp với ngữ cảnh đã chọn.');
            }

            // Resolve context label — do model của component cung cấp,
            // được lưu vào DB để com_kmail hiển thị mà không cần query nghiệp vụ.
            $contextLabel = $model->resolveContextLabel($contextType, $contextId);

            // MailService::notify() chịu trách nhiệm toàn bộ:
            //   - templateId=null : tự xác định template, tạo queue nếu đủ điều kiện
            //   - templateId=X    : dùng template X, tạo queue ngay
            /** @var MailService $mailService */
            $mailService = ComponentHelper::getMailService();
            $result      = $mailService->notify($contextType, $contextId, $recipients, $templateId, null, $contextLabel);

            match ($result) {
                MailCampaignResult::Queued =>
                    $this->setMessage('Đã xếp hàng gửi email thông báo. Email sẽ sớm được gửi đi.'),

                MailCampaignResult::NeedSelectTemplate =>
                    $this->redirectToSelectTemplate($contextType, $contextId, $returnB64),

                MailCampaignResult::NoTemplate =>
                    throw new Exception(
                        'Không có template email nào phù hợp với ngữ cảnh này. '
                        . 'Vui lòng tạo template trong com_kmail trước khi gửi thông báo.'
                    ),
            };
        }
        catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

	/**
	 * Redirect sang com_kmail/notify/selecttemplate để user chọn template.
	 *
	 * com_kmail render layout selecttemplate với form action = notify_url.
	 * User chọn template rồi form POST thẳng về task notify() của component
	 * kèm template_id — không qua NotifyController::create() của com_kmail.
	 *
	 * @param   int     $contextType  Giá trị ContextType
	 * @param   int     $contextId    Giá trị Context Id
	 * @param   string  $returnB64    Return URL (base64) để truyền tiếp qua selecttemplate
	 *
	 * @throws Exception
	 * @since  1.0.3
	 */
    protected function redirectToSelectTemplate(
        int    $contextType,
        int    $contextId,
        string $returnB64,
    ): void {
        // notify_url: URL của task notify trong component.
        // Dùng làm form action trong selecttemplate.php — form POST thẳng về đây.
        //$notifyUrl    = 'index.php?option=com_eqa&task=mailcampaigns.notify';
	    $controllerName = $this->getName();
	    $notifyUrl = "index.php?option={$this->option}&task={$controllerName}.notify";
        $notifyUrlB64 = base64_encode($notifyUrl);

        $this->setRedirect(Route::_(
            'index.php?option=com_kmail&view=templates&layout=selecttemplate'
            . '&context_type=' . $contextType
            . '&context_id='   . $contextId
            . '&notify_url='   . $notifyUrlB64
            . '&return='       . $returnB64,
            false
        ));
    }

    /**
     * Decode return URL từ base64. Chỉ chấp nhận URL bắt đầu bằng 'index.php'.
     * Fallback về trang chủ của component nếu không hợp lệ.
     *
     * @param  string  $returnB64
     * @return string
     * @since  1.0.3
     */
    protected function decodeReturnUrl(string $returnB64): string
    {
        if ($returnB64 !== '') {
            $decoded = base64_decode($returnB64);
            if ($decoded !== false && str_starts_with($decoded, 'index.php')) {
                return $decoded;
            }
        }

        return 'index.php?option='.$this->option;
    }

    /**
     * Validate context_type và context_id theo MailContextType enum (lib_kma).
     *
     * @throws Exception
     * @since  1.0.3
     */
    protected function validateContext(int $contextType, int $contextId): void
    {
        $type = MailContextType::tryFrom($contextType);

        if ($type === null) {
            throw new Exception(
                'Ngữ cảnh gửi email không hợp lệ (context_type = ' . $contextType . ').'
            );
        }

        if ($contextId <= 0 && $type !== MailContextType::Manual) {
            throw new Exception('Không xác định được đối tượng ngữ cảnh (context_id).');
        }
    }
}
