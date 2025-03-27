<?php
namespace Kma\Component\Eqa\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;
class Router extends RouterView
{
	public function __construct(SiteApplication $app, AbstractMenu $menu)
	{
		$this->registerView(new RouterViewConfiguration('regradings'));
		$this->registerView(new RouterViewConfiguration('gradcorrections'));
		$this->registerView(new RouterViewConfiguration('learnerexam'));
		$this->registerView(new RouterViewConfiguration('learnerexams'));

		$categories  = new RouterViewConfiguration('categories');
		$categories->setKey('id');
		$this->registerView($categories);


		$category = new RouterViewConfiguration('category');
		$category->setKey('id')->setParent($categories, 'catid')->setNestable()->addLayout('blog');
		$this->registerView($category);


		$article = new RouterViewConfiguration('article');
		$article->setKey('id')->setParent($category, 'catid');
		$this->registerView($article);

		$this->registerView(new RouterViewConfiguration('archive'));

		$form = new RouterViewConfiguration('form');
		$form->setKey('a_id');
		$this->registerView($form);

		parent::__construct($app, $menu);

		$this->attachRule(new MenuRules($this));
		$this->attachRule(new StandardRules($this));
		$this->attachRule(new NomenuRules($this));
	}

}
