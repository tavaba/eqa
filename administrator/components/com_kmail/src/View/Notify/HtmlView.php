<?php

/**
 * @package     Kma\Component\Kmail\Administrator\View\Notify
 * @since       1.0.0
 */

namespace Kma\Component\Kmail\Administrator\View\Notify;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Kma\Component\Kmail\Administrator\Service\ConfigService;
use Kma\Library\Kma\Enum\MailContextType;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\Service\MailService;

/**
 * HtmlView cho luồng gửi thông báo email (view Notify).
 *
 * Hiện chỉ có layout 'selecttemplate' — hiển thị danh sách template
 * phù hợp với context_type để người dùng chọn.
 *
 * Được kích hoạt từ NotifyController::notify() khi component gọi
 * redirect về com_kmail sau khi MailService::notify() trả về
 * MailCampaignResult::NeedSelectTemplate.
 *
 * @since 1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Dữ liệu cho layout selecttemplate.
     * Gán bởi prepareDataForLayoutSelecttemplate().
     *
     * @var object|null
     */
    public ?object $layoutData = null;

    /**
     * @inheritDoc
     * @since 1.0.0
     */
    public function display($tpl = null): void
    {
        $layout = $this->getLayout();

        try {
            match ($layout) {
                'selecttemplate' => $this->prepareDataForLayoutSelecttemplate(),
                default          => throw new Exception('Layout không hợp lệ: ' . $layout),
            };
        }
        catch (Exception $e) {
            $this->setLayout('error');
            $this->errorMessage = $e->getMessage();
        }

        parent::display($tpl);
    }

    /**
     * Chuẩn bị dữ liệu cho layout selecttemplate.
     *
     * Đọc tham số từ request, query danh sách template phù hợp,
     * gán vào $this->layoutData để template PHP sử dụng.
     *
     * Request params (GET):
     *   - context_type (int)    : giá trị MailContextType enum
     *   - context_id   (int)    : ID đối tượng ngữ cảnh
     *   - notify_url   (string) : URL base64 của task notify tại component gốc
     *   - return       (string) : URL base64 để redirect về sau khi hoàn tất
     *
     * @return void
     * @throws Exception
     * @since  1.0.0
     */
    private function prepareDataForLayoutSelecttemplate(): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', 'com_kmail')) {
            throw new Exception('Bạn không có quyền thực hiện chức năng này.', 403);
        }

        $input       = Factory::getApplication()->input;
        $contextType = $input->getInt('context_type');
        $contextId   = $input->getInt('context_id');
        $notifyUrlB64 = $input->getString('notify_url', '');
        $returnB64    = $input->getString('return', '');

        if ($contextType <= 0) {
            throw new Exception('Tham số context_type không hợp lệ.');
        }

        // Lấy nhãn context type từ enum
        $contextTypeEnum  = MailContextType::tryFrom($contextType);
        $contextTypeLabel = $contextTypeEnum?->getLabel() ?? ('Ngữ cảnh #' . $contextType);

        // Lấy danh sách template phù hợp
        /** @var MailService $mailService */
        $mailService = ComponentHelper::getMailService();
        $templates   = $mailService->getTemplatesByContextType($contextType);

        if (empty($templates)) {
            throw new Exception(
                'Không có template email nào phù hợp với ngữ cảnh "' . $contextTypeLabel . '". '
                . 'Vui lòng tạo template trước.'
            );
        }

        // Gán dữ liệu cho template PHP
        $this->layoutData                   = new \stdClass();
        $this->layoutData->templates        = $templates;
        $this->layoutData->contextType      = $contextType;
        $this->layoutData->contextId        = $contextId;
        $this->layoutData->contextTypeLabel = $contextTypeLabel;
        $this->layoutData->notifyUrlB64     = $notifyUrlB64;
        $this->layoutData->returnB64        = $returnB64;

        // Toolbar
        ToolbarHelper::title('Chọn mẫu email thông báo');
        ToolbarHelper::appendGoHome();
    }
}
