<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Kma\Library\Kma\Model\AdminModel;

defined('_JEXEC') or die();

class EmployeeModel extends AdminModel
{
    public function prepareTable($table)
    {
        if(empty($table->code))
            $table->code = null;
        parent::prepareTable($table);
    }
}
