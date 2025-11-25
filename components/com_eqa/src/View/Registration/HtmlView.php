<?php
namespace Kma\Component\Eqa\Site\View\Registration;   //Must end with the View Name
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\User\User;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	protected User $user;
	protected ?string $learnerCode;
	public function display($tpl = null)
	{
		$this->user = Factory::getApplication()->getIdentity();
		$this->learnerCode = GeneralHelper::getSignedInLearnerCode();
		$this->addToolbar();

		parent::display($tpl);
	}
	private function addToolbar()
	{
		ToolbarHelper::title('Đăng ký thi');
	}

}