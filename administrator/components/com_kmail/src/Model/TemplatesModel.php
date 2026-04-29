<?php
namespace Kma\Component\Kmail\Administrator\Model;
defined('_JEXEC') or die();

use Kma\Component\Kmail\Administrator\Enum\ObjectType;
use Kma\Library\Kma\Model\MailTemplatesModel as BaseMailTemplatesModel;

/**
 * Model danh sách Mail Template của com_kmail.
 *
 * Kế thừa BaseMailTemplatesModel (lib_kma).
 * Override getContextTypeOptions() dùng MailContextType từ lib_kma.
 *
 * @since 1.0.0
 */
class TemplatesModel extends BaseMailTemplatesModel
{
	protected function getLogObjectType(): int
	{
		return ObjectType::Template->value;
	}
}
