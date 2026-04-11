<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Kma\Component\Eqa\Administrator\Enum\MailContextType;
use Kma\Component\Eqa\Administrator\Enum\ObjectType;

defined('_JEXEC') or die();

class MailTemplatesModel extends \Kma\Library\Kma\Model\MailTemplatesModel
{
 	protected function getLogObjectType(): int
	{
		return ObjectType::MailTemplate->value;
	}

	protected function getContextTypeOptions(): array
	{
		return MailContextType::getOptions();
	}
}