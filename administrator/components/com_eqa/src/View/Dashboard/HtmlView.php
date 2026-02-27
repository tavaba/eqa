<?php
namespace Kma\Component\Eqa\Administrator\View\Dashboard; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Kma\Library\Kma\Helper\ComponentHelper;

/**
 * View class for a list of Eqa.
 *
 * @since  __BUMP_VERSION__
 */
class HtmlView extends BaseHtmlView{
    private $canDo;
    public function display($tpl = null)
    {
        $this->addToolbar();
        parent::display($tpl);
    }
    protected function addToolbar():void
    {
        ToolbarHelper::title(Text::_('COM_EQA_MANAGER_DASHBOARD_TITLE'));
		$user = Factory::getApplication()->getIdentity();
		$componentName = ComponentHelper::getName();
        if($user->authorise('core.options', $componentName))
            ToolbarHelper::preferences($componentName);
    }
}
