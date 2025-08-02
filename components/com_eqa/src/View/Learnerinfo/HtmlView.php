<?php
namespace Kma\Component\Eqa\Site\View\Learnerinfo;   //Must end with the View Name
defined('_JEXEC') or die();

use JFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseView;
use Joomla\CMS\User\User;

class HtmlView extends BaseView
{
	protected User $user;
	public function display($tpl=null)
	{
		$this->user = Factory::getApplication()->getIdentity();
		parent::display();
	}
}