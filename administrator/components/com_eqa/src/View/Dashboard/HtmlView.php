<?php
namespace Kma\Component\Eqa\Administrator\View\Dashboard; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

/**
 * View class for a list of Eqa.
 *
 * @since  __BUMP_VERSION__
 */
class HtmlView extends BaseHtmlView{
    private $canDo;
    public function display($tpl = null)
    {
        $this->canDo = GeneralHelper::getActions();
        $this->addToolbar();
        parent::display($tpl);
    }
    protected function addToolbar():void
    {
        ToolbarHelper::title(Text::_('COM_EQA_MANAGER_DASHBOARD_TITLE'));
        if($this->canDo['core.options'])
            ToolbarHelper::preferences('com_eqa');
    }
}
