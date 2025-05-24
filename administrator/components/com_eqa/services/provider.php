<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_examsurveys
 *
 * @copyright   Copyright (C) 2023 Academy of Cryptography Technologies. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\CategoryFactory;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Kma\Component\Eqa\Administrator\Extension\EqaComponent;

return new class implements ServiceProviderInterface
{
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   __BUMP_VERSION__
	 */
	public function register(Container $container): void
    {
		$container->registerServiceProvider(new CategoryFactory('\\Kma\\Component\\Eqa'));
		$container->registerServiceProvider(new MVCFactory('\\Kma\\Component\\Eqa'));
		$container->registerServiceProvider(new ComponentDispatcherFactory('\\Kma\\Component\\Eqa'));

		$container->set(
			ComponentInterface::class,
			function (Container $container) {
				$component = new EqaComponent($container->get(ComponentDispatcherFactoryInterface::class));
				$component->setRegistry($container->get(Registry::class));
				return $component;
			}
		);


	}
};
