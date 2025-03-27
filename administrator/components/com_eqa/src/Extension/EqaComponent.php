<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_eqa
 *
 * @copyright   Copyright (C) 2023 Academy of Crptography Techniques. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Kma\Component\Eqa\Administrator\Extension;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Kma\Component\Eqa\Administrator\Service\HTML\AdministratorService;
use Psr\Container\ContainerInterface;

/**
 * Component class for com_eqa
 *
 * @since  __BUMP_VERSION__
 */
class EqaComponent extends MVCComponent implements BootableExtensionInterface, CategoryServiceInterface
{
	use CategoryServiceTrait;
	use HTMLRegistryAwareTrait;
	use RouterServiceTrait;

	public function boot(ContainerInterface $container)
	{
		$this->getRegistry()->register('eqaadministrator', new AdministratorService);
	}
}