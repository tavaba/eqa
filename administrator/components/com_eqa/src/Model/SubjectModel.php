<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Kma\Library\Kma\Model\AdminModel;

defined('_JEXEC') or die();

class SubjectModel extends AdminModel{
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
