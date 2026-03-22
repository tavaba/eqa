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
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Kma\Component\Eqa\Administrator\Service\ConfigService;
use Kma\Component\Eqa\Administrator\Service\HTML\AdministratorService;
use Kma\Library\Kma\Service\EnglishService;
use Kma\Library\Kma\Service\LogService;
use Psr\Container\ContainerInterface;
use Joomla\CMS\Component\Router\RouterServiceInterface;

/**
 * Component class for com_eqa
 *
 * @since  __BUMP_VERSION__
 */
class EqaComponent extends MVCComponent implements BootableExtensionInterface, CategoryServiceInterface, RouterServiceInterface
{
	use CategoryServiceTrait;
	use HTMLRegistryAwareTrait;
	use RouterServiceTrait;

	/**
	 * Service đọc cấu hình của component, được inject qua DI.
	 *
	 * @var    ConfigService
	 * @since  2.0.4
	 */
	private ConfigService $configService;

	/**
	 * Service để ghi log, được inject qua DI
	 * @since 2.0.6
	 */
	private LogService $logService;

	/**
	 * Service để tự động xác định dạng số ít, số nhiều trong tiếng Anh
	 * @since 2.0.6
	 */
	private EnglishService $englishService;

	/**
	 * Được Joomla gọi sau khi component được boot từ DIC.
	 * Dùng để đăng ký các HTML service, v.v.
	 *
	 * @param   ContainerInterface  $container  Child DIC của component.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function boot(ContainerInterface $container): void
	{
		$this->getRegistry()->register('eqaadministrator', new AdministratorService);
	}

	/**
	 * Nhận ConfigService từ DIC (được gọi trong provider.php).
	 *
	 * @param   ConfigService  $configService
	 *
	 * @return  void
	 * @since   2.0.4
	 */
	public function setConfigService(ConfigService $configService): void
	{
		$this->configService = $configService;
	}

	/**
	 * Trả về ConfigService để các class khác sử dụng.
	 *
	 * @return  ConfigService
	 * @since   2.0.4
	 */
	public function getConfigService(): ConfigService
	{
		return $this->configService;
	}

	/**
	 * Nhận ConfigService từ DIC (được gọi trong provider.php).
	 *
	 * @param   LogService  $logService
	 * @since 2.0.6
	 */
	public function setLogService(LogService $logService): void
	{
		$this->logService = $logService;
	}

	/**
	 * Trả về LogService để các class trong component sử dụng
	 * @return LogService
	 *
	 * @since 2.0.6
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