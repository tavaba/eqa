<?php
namespace Kma\Component\Kmail\Administrator\Model;
defined('_JEXEC') or die();

use Kma\Component\Kmail\Administrator\Enum\ObjectType;
use Kma\Library\Kma\Model\AdminModel;
class TemplateModel extends AdminModel
{
	protected function getLogObjectType(): int
	{
		return ObjectType::Template->value;
	}
}
