<?php
namespace Kma\Component\Kmail\Administrator\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;

/**
 * Service đọc các tham số cấu hình của component com_kmail.
 *
 * Cách dùng:
 *   $config = new ConfigService();
 *   $org    = $config->getOrganization();
 *
 * @since 1.0.0
 */
class ConfigService
{
	/**
	 * Tham số cấu hình của component com_kmail.
	 *
	 * @var    Registry
	 * @since 1.0.0
	 */
	private Registry $params;

	/**
	 * Constructor — tự động nạp tham số cấu hình của com_kmail.
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		$this->params = ComponentHelper::getParams('com_kmail');
	}

	/**
	 * Domain mặc định cho email của học viên, sinh viên
	 * @since 1.0.0
	 */
	public function getLearnerMailDomain():string
	{
		return $this->params->get('learner_mail_domain','actvn.edu.vn');
	}

	/**
	 * Số email gửi đồng thời mỗi loạt.
	 *
	 * @since 1.0.0
	 */
	public function getBatchSize(): int
	{
		return $this->params->get('params.batch_size', 50);
	}

	/**
	 * Số lần gửi tối đa
	 *
	 * @since 1.0.0
	 */
	public function getMaxAttempts(): int
	{
		return $this->params->get('params.max_attempts', 3);
	}

	/**
	 * Khoảng thời gian giữa 2 lần gửi
	 *
	 * @since 1.0.0
	 */
	public function getRetryIntervalMinutes(): int
	{
		return $this->params->get('params.retry_interval_minutes', 5);
	}

}
