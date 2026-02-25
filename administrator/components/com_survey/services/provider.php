<?php
defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Kma\Component\Survey\Administrator\Extension\SurveyComponent;
use Kma\Component\Survey\Site\Service\Router;
return new class implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->registerServiceProvider(new MVCFactory('\\Kma\\Component\\Survey'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Kma\\Component\\Survey'));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $component = new SurveyComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                return $component;
            }
        );

        $container->set(
            'com_survey.router',
            function (Container $container)
            {
                $app  = $container->get(SiteApplication::class);
                $menu = $container->get('menu');
                return new Router($app, $menu);
            }
        );
    }
};