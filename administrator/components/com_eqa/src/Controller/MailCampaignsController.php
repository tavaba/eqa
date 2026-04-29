<?php
namespace Kma\Component\Eqa\Administrator\Controller;
defined('_JEXEC') or die();

use Exception;

/**
 * Controller phục vụ việc gửi email cho component.
 * Method chính: 'notify'.
 * Yêu cầu component phải có model MailCampaigns kế thừa từ lib_kma
 * @since 2.0.8
 */
class MailCampaignsController extends \Kma\Library\Kma\Controller\MailCampaignsController
{
	/**
	 * @inheritDoc
	 */
	protected function checkSendMailPermission(int $contextType, int $contextId): void
	{
		$user = $this->app->getIdentity();
		$allowed = $user->authorise('eqa.sendmail', $this->option);
		if(!$allowed)
			throw new Exception('Bạn không có quyền gửi email');
	}
}
