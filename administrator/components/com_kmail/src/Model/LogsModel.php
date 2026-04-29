<?php
namespace Kma\Component\Kmail\Administrator\Model;
defined('_JEXEC') or die();

use Kma\Component\Kmail\Administrator\Constant\Action;
use Kma\Component\Kmail\Administrator\Enum\ObjectType;
use Kma\Library\Kma\Model\LogsModel as BaseLogsModel;

/**
 * Model danh sách nhật ký hệ thống của com_kmail.
 *
 * Chỉ cần khai báo tên bảng — toàn bộ query/filter logic
 * được kế thừa từ BaseLogsModel.
 *
 * @since 1.0.0
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
