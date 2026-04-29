<?php
namespace Kma\Component\Eqa\Administrator\View\Mailcampaigns;
defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Library\Kma\Enum\MailContextType;
use Kma\Library\Kma\View\MailCampaignsHtmlView;

/**
 * View Mailcampaigns của com_eqa.
 *
 * Sau khi refactor, view này chỉ còn phục vụ layout 'selecttemplate'
 * (Luồng B — người dùng chọn template khi có nhiều template phù hợp).
 * Layout 'default' (danh sách campaign) và 'log' (delivery log) đã
 * chuyển sang com_kmail.
 *
 * URL:
 *   Chọn template: index.php?option=com_eqa&view=mailcampaigns&layout=selecttemplate
 *                  &context_type=X&context_id=Y&return={base64}
 *
 * @since 2.0.8
 */
class HtmlView extends MailCampaignsHtmlView
{
    // =========================================================================
    // Abstract override — bắt buộc
    // =========================================================================

    /**
     * Trả về nhãn hiển thị của context_type dùng MailContextType (lib_kma).
     *
     * @param  int  $contextType
     * @return string
     * @since  2.0.8
     */
    protected function getContextTypeLabel(int $contextType): string
    {
        return MailContextType::tryFrom($contextType)?->getLabel() ?? '?';
    }

    // =========================================================================
    // Override toolbar cho layout selecttemplate
    // =========================================================================

    /**
     * Toolbar cho layout selecttemplate — dùng ToolbarHelper của com_eqa.
     *
     * @since 2.0.8
     */
    protected function addToolbarForLayoutSelecttemplate(): void
    {
        ToolbarHelper::title('Chọn mẫu email thông báo');
        ToolbarHelper::appendGoHome();
    }
}
