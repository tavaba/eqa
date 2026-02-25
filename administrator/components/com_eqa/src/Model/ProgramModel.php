<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Kma\Library\Kma\Model\AdminModel;

defined('_JEXEC') or die();

class ProgramModel extends AdminModel {

    //Rewrite hàm này để xử lý trường hợp người dùng để trống
    //các trường kiểu INTEGER 'firstrelease' hoặc/và 'lastupdate'.
    //Nếu không thiết lập thành NULL thì sẽ bị báo lỗi
    public function prepareTable($table): void
    {
        if(empty($table->firstrelease))
            $table->firstrelease=null;
        if(empty($table->lastupdate))
            $table->lastupdate=null;
    }
}
