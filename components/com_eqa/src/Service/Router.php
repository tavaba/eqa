<?php
namespace Kma\Component\Eqa\Site\Service;

use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;
class Router extends RouterView
{
	public function __construct(SiteApplication $app, AbstractMenu $menu)
	{

		parent::__construct($app, $menu);

		// Register all views
		$this->registerView(new RouterViewConfiguration('learnerexams'));
		$this->registerView(new RouterViewConfiguration('learnerregradings'));
		$this->registerView(new RouterViewConfiguration('learnergradecorrections'));
		$this->registerView(new RouterViewConfiguration('regradings'));
		$this->registerView(new RouterViewConfiguration('gradecorrections'));

		// Register the new learnerexam view with layout and parameter configuration
		$learnerexam = new RouterViewConfiguration('learnerexam');
		$learnerexam->addLayout('requestcorrection');
		//$learnerexam->setKey('id'); // This makes exam_id a required parameter
		$this->registerView($learnerexam);

		// Attach the routing rules
		$this->attachRule(new MenuRules($this));
		$this->attachRule(new StandardRules($this));
		$this->attachRule(new NomenuRules($this));
	}

}
