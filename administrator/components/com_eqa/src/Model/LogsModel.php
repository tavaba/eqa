<?php

/**
 * @package     Kma\Component\Eqa\Administrator\Model
 * @since       2.0.6
 */

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Constant\Action;
use Kma\Component\Eqa\Administrator\Enum\ObjectType;
use Kma\Library\Kma\Model\LogsModel as BaseLogsModel;

/**
 * Model danh sách nhật ký hệ thống của com_eqa.
 *
 * Chỉ cần khai báo tên bảng — toàn bộ query/filter logic
 * được kế thừa từ BaseLogsModel.
 *
 * @since 2.0.6
 */
class LogsModel extends BaseLogsModel
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
