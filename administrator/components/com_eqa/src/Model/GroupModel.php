<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;

defined('_JEXEC') or die();

class GroupModel extends EqaAdminModel {
    public function prepareTable($table): void
    {
        $table->size=null;  //Không cho phép cập nhật trực tiếp sĩ số
        if(empty($table->homeroom_id))
            $table->homeroom_id = null;
        if(empty($table->adviser_id))
            $table->adviser_id = null;
    }
}
