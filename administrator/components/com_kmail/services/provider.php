<?php

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\CategoryFactory;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Kma\Component\Kmail\Administrator\Constant\TableName;
use Kma\Component\Kmail\Administrator\Extension\KmailComponent;
use Kma\Component\Kmail\Administrator\Service\ConfigService;
use Kma\Library\Kma\Service\EnglishService;
use Kma\Library\Kma\Service\LogService;
use Kma\Library\Kma\Service\MailService;

return new class implements ServiceProviderInterface
{
	public function register(Container $container): void
    {
		$container->registerServiceProvider(new CategoryFactory('\\Kma\\Component\\Kmail'));
		$container->registerServiceProvider(new MVCFactory('\\Kma\\Component\\Kmail'));
		$container->registerServiceProvider(new ComponentDispatcherFactory('\\Kma\\Component\\Kmail'));

	    // ── Đăng ký LogService vào DIC ────────────────────────────────────
	    // DIC sẽ tạo instance một lần duy nhất (shared = true theo mặc định)
	    // và tái sử dụng trong suốt vòng đời của request.
		$container->set(
			LogService::class,
			function (Container $container){
				$db = $container->get(DatabaseInterface::class);
				$tableName = '#__kmail_logs';
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
				    'course' => 'courses',
				    'template' => 'templates',
			    ];
			    return new EnglishService($map);
		    }
	    );
		
		// ── Đăng ký MailService vào DIC ────────────────────────────────────
	    // DIC sẽ tạo instance một lần duy nhất (shared = true theo mặc định)
	    // và tái sử dụng trong suốt vòng đời của request.
	    $container->set(
		    MailService::class,
		    function (Container $container)
		    {
				$configService = new ConfigService();
			    return new MailService
			    (
				    $container->get(DatabaseInterface::class),
				    TableName::TEMPLATES,
				    TableName::CAMPAIGNS,
				    TableName::QUEUE,
				    $configService->getBatchSize(),
				    $configService->getMaxAttempts(),
				    $configService->getRetryIntervalMinutes()
			    );
		    }
	    );

		$container->set(
			ComponentInterface::class,
			function (Container $container) {
				$component = new KmailComponent($container->get(ComponentDispatcherFactoryInterface::class));
				$component->setRegistry($container->get(Registry::class));
				$component->setMVCFactory($container->get(MVCFactoryInterface::class));
				$component->setLogService($container->get(LogService::class));
				$component->setEnglishService($container->get(EnglishService::class));
				$component->setMailService($container->get(MailService::class));
				return $component;
			}
		);

	}
};
