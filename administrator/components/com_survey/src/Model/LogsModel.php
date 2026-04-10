<?php
namespace Kma\Component\Survey\Administrator\Model;
defined('_JEXEC') or die();

use Kma\Component\Survey\Administrator\Enum\ObjectType;
use Kma\Component\Survey\Administrator\Constant\Action;

class LogsModel extends \Kma\Library\Kma\Model\LogsModel
{
	public function getActionClass(): string
	{
		return Action::class;
	}

	public function getObjectTypeClass(): string
	{
		return ObjectType::class;
	}
}