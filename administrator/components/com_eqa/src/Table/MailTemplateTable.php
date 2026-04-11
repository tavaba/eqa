<?php
namespace Kma\Component\Eqa\Administrator\Table;
defined('_JEXEC') or die();

use Joomla\Database\DatabaseDriver;
use Kma\Library\Kma\Table\Table;
class MailTemplateTable extends Table
{
	public function __construct(DatabaseDriver $db, string $tableName='#__eqa_mail_templates', string $keyName='')
	{
		parent::__construct($db, $tableName, $keyName);
	}
}