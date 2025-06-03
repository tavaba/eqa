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
		// Define the 'gradecorrections' view with 'learner' and 'supervisor' layouts
		$gradecorrections = new RouterViewConfiguration('gradecorrections');
		$gradecorrections->setKey('layout');
		$this->registerView($gradecorrections);

		// Define the 'regradings' view with 'learner' and 'supervisor' layouts
		$regradings = new RouterViewConfiguration('regradings');
		$regradings->setKey('layout');
		$this->registerView($regradings);

		// Define the 'learnerexam' view
		$learnerexam = new RouterViewConfiguration('learnerexam');
		$learnerexam->setKey('exam_id')
			->addLayout('requestcorrection');
		$this->registerView($learnerexam);

		// Define the 'learnerexams' view
		$learnerexams = new RouterViewConfiguration('learnerexams');
		$learnerexams->setKey('layout');
		$this->registerView($learnerexams);

		parent::__construct($app, $menu);

		//$this->attachRule(new StandardRules($this));
		$this->attachRule(new NomenuRules($this));
	}

}
