<?php
namespace Kma\Component\Eqa\Administrator\Base;
defined('_JEXEC') or die();
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Kma\Component\Eqa\Administrator\Helper\StringHelper;

class EqaTable extends Table{
    public function __construct(DatabaseDriver $db, string $tableName='', string $keyName='')
    {
        if(empty($tableName)){
            $className = get_class($this);
            $shortClassName = basename(str_replace('\\', '/', $className));
            $item = substr($shortClassName,0,strlen($shortClassName)-5); //strlen('Table') = 5
            $items = StringHelper::convertSingleToPlural($item);
            $tableName = '#__eqa_'.strtolower($items);
        }
        if(empty($keyName))
            $keyName='id';
        parent::__construct($tableName,$keyName, $db);
    }
}