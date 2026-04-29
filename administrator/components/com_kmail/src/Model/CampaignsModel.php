<?php
namespace Kma\Component\Kmail\Administrator\Model;
defined('_JEXEC') or die();

use Kma\Component\Kmail\Administrator\Enum\ObjectType;
use Kma\Library\Kma\Model\MailCampaignsModel as BaseMailCampaignModel;
class CampaignsModel extends BaseMailCampaignModel
{
	protected function getLogObjectType(): int
	{
		return ObjectType::Campaign->value;
	}

	/**
	 * Phương thức rỗng để đáp ứng yêu cầu override phương thức trừu tượng.
	 */
	protected function resolveRecipients(int $contextType, int $contextId, ?string $recipientFilter): array
	{
		return [];
	}
}
