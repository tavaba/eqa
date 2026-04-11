<?php
defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Kma\Component\Survey\Administrator\Extension\SurveyComponent;
use Kma\Component\Survey\Site\Service\Router;
use Kma\Library\Kma\Service\EnglishService;
use Kma\Library\Kma\Service\LogService;

return new class implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->registerServiceProvider(new MVCFactory('\\Kma\\Component\\Survey'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Kma\\Component\\Survey'));
	    $container->registerServiceProvider(new RouterFactory('\\Kma\\Component\\Survey'));

	    // ── Đăng ký LogService vào DIC ────────────────────────────────────
	    // DIC sẽ tạo instance một lần duy nhất (shared = true theo mặc định)
	    // và tái sử dụng trong suốt vòng đời của request.
	    $container->set(
		    LogService::class,
		    function (Container $container){
			    $db = $container->get(DatabaseInterface::class);
			    $tableName = '#__survey_logs';
			    return new LogService($db, $tableName);
		    }
	    );

	    // ── Đăng ký English vào DIC ────────────────────────────────────
	    // DIC sẽ tạo instance một lần duy nhất (shared = true theo mặc định)
	    // và tái sử dụng trong suốt vòng đời của request.
	    $container->set(
		    EnglishService::class,
		    function (Container $container){
			    $map = [
			    ];
			    return new EnglishService($map);
		    }
	    );


	    $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $component = new SurveyComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );
	            $component->setRouterFactory($container->get(RouterFactoryInterface::class));            $component->setRegistry($container->get(Registry::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
	            $component->setLogService($container->get(LogService::class));
	            $component->setEnglishService($container->get(EnglishService::class));
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