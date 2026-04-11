<?php
namespace Kma\Component\Survey\Site\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;

class Router extends RouterView
{
	/**
	 * Constructor
	 *
	 * @param   CMSApplication  $app   The application object
	 * @param   AbstractMenu    $menu  The menu object to work with
	 * @since   1.0.0
	 */
	public function __construct(SiteApplication $app, AbstractMenu $menu)
	{

		parent::__construct($app, $menu);

		// 1. Đăng ký View: surveys (danh sách)
		$surveys = new RouterViewConfiguration('surveys');
		$this->registerView($surveys);

		// 2. Đăng ký View: survey (chi tiết/form)
		$survey = new RouterViewConfiguration('survey');
		$survey->setKey('id'); // Joomla sẽ tự hiểu id này lấy từ URL
		$this->registerView($survey);

		// Attach the routing rules
		$this->attachRule(new MenuRules($this));
		$this->attachRule(new StandardRules($this));
		$this->attachRule(new NomenuRules($this));
	}
}