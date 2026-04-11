<?php

/**
 * @package     Kma\Component\Eqa\Administrator\View\Mailcampaigns
 * @since       2.0.9
 */

namespace Kma\Component\Eqa\Administrator\View\Mailcampaigns;

defined('_JEXEC') or die();

use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Enum\MailContextType;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Library\Kma\View\MailCampaignsHtmlView;

/**
 * View danh sách chiến dịch email của com_eqa.
 *
 * Kế thừa toàn bộ logic từ MailCampaignsHtmlView (lib_kma).
 * Override:
 *   - getViewName()            : tên view trong URL
 *   - getContextTypeLabel()    : nhãn context_type dùng MailContextType của com_eqa
 *   - getViewTitle()           : tiêu đề toolbar
 *   - addToolbarForLayoutDefault(): dùng ToolbarHelper của com_eqa
 *   - addToolbarForLayoutLog() : dùng ToolbarHelper của com_eqa
 *
 * URL truy cập:
 *   Danh sách   : index.php?option=com_eqa&view=mailcampaigns
 *   Delivery log: index.php?option=com_eqa&view=mailcampaigns&layout=log&campaign_id=X
 *
 * @since 2.0.9
 */
class HtmlView extends MailCampaignsHtmlView
{
    // =========================================================================
    // Abstract overrides — bắt buộc
    // =========================================================================

    /**
     * Trả về nhãn hiển thị của context_type dùng enum MailContextType của com_eqa.
     *
     * lib_kma không biết enum này — com_eqa cung cấp thông qua method này.
     *
     * @param  int  $contextType
     *
     * @return string
     * @since  2.0.9
     */
    protected function getContextTypeLabel(int $contextType): string
    {
        return MailContextType::tryFrom($contextType)?->getLabel() ?? '?';
    }

    // =========================================================================
    // Overridable — tuỳ biến tiêu đề và toolbar cho com_eqa
    // =========================================================================

    /**
     * Toolbar cho layout log — dùng ToolbarHelper của com_eqa.
     *
     * @since 2.0.9
     */
    protected function addToolbarForLayoutLog(): void
    {
        ToolbarHelper::title($this->getViewTitle() . ' — Chi tiết gửi');
        ToolbarHelper::appendGoHome();

        $backUrl = Route::_(
            'index.php?option=com_eqa&view=mailcampaigns',
            false
        );
        ToolbarHelper::appendLink('core.manage', $backUrl, 'Danh sách', 'arrow-up-2');
    }
}
