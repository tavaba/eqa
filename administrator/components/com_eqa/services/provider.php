<?php

defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\CategoryFactory;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Kma\Component\Eqa\Administrator\Extension\EqaComponent;
use Kma\Component\Eqa\Site\Service\Router;
use Joomla\CMS\Component\Router\RouterInterface;

return new class implements ServiceProviderInterface
{
	public function register(Container $container): void
    {
		$container->registerServiceProvider(new CategoryFactory('\\Kma\\Component\\Eqa'));
		$container->registerServiceProvider(new MVCFactory('\\Kma\\Component\\Eqa'));
		$container->registerServiceProvider(new ComponentDispatcherFactory('\\Kma\\Component\\Eqa'));
	    $container->registerServiceProvider(new RouterFactory('\\Kma\\Component\\Eqa'));

	    $container->set(
		    RouterInterface::class,
		    function (Container $container) {
			    return new Router(
				    $container->get(SiteApplication::class),
				    $container->get(AbstractMenu::class)
			    );
		    }
	    );

		$container->set(
			ComponentInterface::class,
			function (Container $container) {
				$component = new EqaComponent($container->get(ComponentDispatcherFactoryInterface::class));
				$component->setRegistry($container->get(Registry::class));
				$component->setMVCFactory($container->get(MVCFactoryInterface::class));
				$component->setRouterFactory($container->get(RouterFactoryInterface::class));
				return $component;
			}
		);

	}
};
