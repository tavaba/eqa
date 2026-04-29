<?php
namespace Kma\Component\Kmail\Administrator\View\Campaigns;
defined('_JEXEC') or die();

use Joomla\CMS\Router\Route;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\View\MailCampaignsHtmlView;

class HtmlView extends MailCampaignsHtmlView
{
    protected function addToolbarForLayoutLog(): void
    {
        ToolbarHelper::title($this->getViewTitle() . ' — Chi tiết gửi');
        ToolbarHelper::appendGoHome();

        $backUrl = Route::_(
            'index.php?option=com_kmail&view=campaigns',
            false
        );
        ToolbarHelper::appendLink('core.manage', $backUrl, 'Chiến dịch', 'arrow-up-2');
    }
}
