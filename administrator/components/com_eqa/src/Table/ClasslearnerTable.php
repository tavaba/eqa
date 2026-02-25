<?php
namespace Kma\Component\Eqa\Administrator\Table;
defined('_JEXEC') or die();

use Joomla\Database\DatabaseDriver;
use Kma\Component\Eqa\Administrator\Base\Table;
class ClasslearnerTable extends Table{
	public function __construct(DatabaseDriver $db, string $tableName = '#__eqa_class_learner', string $keyName = 'id')
	{
		parent::__construct($db, $tableName, $keyName);
	}
}