<?php
namespace Kma\Component\Kmail\Administrator\Extension;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Kma\Component\Kmail\Administrator\Service\ConfigService;
use Kma\Component\Kmail\Administrator\Service\HTML\AdministratorService;
use Kma\Library\Kma\Service\EnglishService;
use Kma\Library\Kma\Service\LogService;
use Kma\Library\Kma\Service\MailService;
use Psr\Container\ContainerInterface;
use Joomla\CMS\Component\Router\RouterServiceInterface;

/**
 * Component class for com_kmail
 *
 * @since  __BUMP_VERSION__
 */
class KmailComponent extends MVCComponent implements BootableExtensionInterface, CategoryServiceInterface, RouterServiceInterface
{
	use CategoryServiceTrait;
	use HTMLRegistryAwareTrait;
	use RouterServiceTrait;

	/**
	 * Service đọc cấu hình của component, được inject qua DI.
	 *
	 * @var    ConfigService
	 * @since  1.0.0
	 */
	private ConfigService $configService;

	/**
	 * Service để tự động xác định dạng số ít, số nhiều trong tiếng Anh
	 * @since  1.0.0
	 */
	private EnglishService $englishService;

	/**
	 * Service để ghi log, được inject qua DI
	 * @since  1.0.0
	 */
	private LogService $logService;

	/**
	 * Service gửi email, được inject qua DI
	 * @since  1.0.0
	 */
	private MailService $mailService;

	/**
	 * Được Joomla gọi sau khi component được boot từ DIC.
	 * Dùng để đăng ký các HTML service, v.v.
	 *
	 * @param   ContainerInterface  $container  Child DIC của component.
	 *
	 * @return  void
	 * @since  1.0.0
	 */
	public function boot(ContainerInterface $container): void
	{
		$this->getRegistry()->register('kmailadministrator', new AdministratorService);
	}

	/**
	 * Nhận ConfigService từ DIC (được gọi trong provider.php).
	 *
	 * @param   ConfigService  $configService
	 *
	 * @return  void
	 * @since  1.0.0
	 */
	public function setConfigService(ConfigService $configService): void
	{
		$this->configService = $configService;
	}

	/**
	 * Trả về ConfigService để các class khác sử dụng.
	 *
	 * @return  ConfigService
	 * @since  1.0.0
	 */
	public function getConfigService(): ConfigService
	{
		return $this->configService;
	}

	/**
	 * Phương thức để tiếp nhận EnglishService từ DIC
	 * @param   EnglishService  $englishService
	 * @since  1.0.0
	 */
	public function setEnglishService(EnglishService $englishService): void
	{
		$this->englishService = $englishService;
	}

	/**
	 * Phương thức để các class trong MVC của component lấy EnglishService
	 * @return EnglishService
	 * @since  1.0.0
	 */
	public function getEnglishService(): EnglishService
	{
		return $this->englishService;
	}

	/**
	 * Nhận LogService từ DIC (được gọi trong provider.php).
	 *
	 * @param   LogService  $logService
	 * @since  1.0.0
	 */
	public function setLogService(LogService $logService): void
	{
		$this->logService = $logService;
	}

	/**
	 * Trả về LogService để các class trong component sử dụng
	 * @return LogService
	 *
	 * @since  1.0.0
	 */
	public function getLogService(): LogService
	{
		return $this->logService;
	}

	/**
	 * Nhận MailService từ DIC (được gọi trong provider.php).
	 *
	 * @param   MailService  $mailService
	 * @since  1.0.0
	 */
	public function setMailService(MailService $mailService): void
	{
		$this->mailService = $mailService;
	}

	/**
	 * Trả về MailService để các class trong component sử dụng
	 * @return MailService
	 *
	 * @since  1.0.0
	 */
	public function getMailService(): MailService
	{
		return $this->mailService;
	}


}