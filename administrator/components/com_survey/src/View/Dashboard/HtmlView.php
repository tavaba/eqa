<?php
namespace Kma\Component\Survey\Administrator\View\Dashboard;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Kma\Library\Kma\Helper\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        //Add preferences button on to toolbar
        $user = Factory::getApplication()->getIdentity();
        if($user->authorise('core.admin','com_survey'))
            ToolbarHelper::preferences('com_survey');
        parent::display();
    }
}