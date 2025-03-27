<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;

defined('_JEXEC') or die();

class SubjectModel extends EqaAdminModel{
    public function prepareTable($table)
    {
        parent::prepareTable($table);
        if(empty($table->credits))
            $table->credits=null;
        if(empty($table->finaltestduration))
            $table->finaltestduration=null;
        if(empty($table->finaltestweight))
            $table->finaltestweight=null;
        if(empty($table->testbankyear))
            $table->testbankyear=null;

    }
}
