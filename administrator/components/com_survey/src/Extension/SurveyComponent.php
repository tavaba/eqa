<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_survey
 *
 * @copyright   Copyright (C) 2023 Academy of Crptography Techniques. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Kma\Component\Survey\Administrator\Extension;
defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\CMS\HTML\Registry;
use Kma\Component\Survey\Administrator\Service\HTML\AdministratorService;
use Kma\Library\Kma\Service\EnglishService;
use Kma\Library\Kma\Service\LogService;
use Psr\Container\ContainerInterface;
use Joomla\CMS\Component\Router\RouterServiceInterface;

/**
 * Component class for com_eqa
 *
 * @since  1.0.0
 */
class SurveyComponent extends MVCComponent implements BootableExtensionInterface, CategoryServiceInterface, RouterServiceInterface
{
	use CategoryServiceTrait;
	use HTMLRegistryAwareTrait;
	use RouterServiceTrait;

	/**
	 * Service để ghi log, được inject qua DI
	 * @since 1.0.2
	 */
	private LogService $logService;

	/**
	 * Service để tự động xác định dạng số ít, số nhiều trong tiếng Anh
	 * @since 1.0.2
	 */
	private EnglishService $englishService;

	public function boot(ContainerInterface $container)
	{
		$this->getRegistry()->register('surveyadministrator', new AdministratorService);
	}

	/**
	 * Nhận ConfigService từ DIC (được gọi trong provider.php).
	 *
	 * @param   LogService  $logService
	 * @since 1.0.2
	 */
	public function setLogService(LogService $logService): void
	{
		$this->logService = $logService;
	}

	/**
	 * Trả về LogService để các class trong component sử dụng
	 * @return LogService
	 *
	 * @since 1.0.2
	 */
	public function getLogService(): LogService
	{
		return $this->logService;
	}

	/**
	 * Phương thức để tiếp nhận EnglishService từ DIC
	 * @param   EnglishService  $englishService
	 * @since 2.0.6
	 */
	public function setEnglishService(EnglishService $englishService): void
	{
		$this->englishService = $englishService;
	}

	/**
	 * Phương thức để các class trong MVC của component lấy EnglishService
	 * @return EnglishService
	 * @since 2.0.6
	 */
	public function getEnglishService(): EnglishService
	{
		return $this->englishService;
	}

}