<?php
namespace Kma\Component\Eqa\Administrator\Table;
defined('_JEXEC') or die();
use Kma\Library\Kma\Helper\StateHelper;
use Kma\Library\Kma\Table\Table;
class ExamseasonTable extends Table{

    /**
     * Thực thể VẬN HÀNH — chỉ dùng 2 trạng thái.
     *
     * Khai báo này khiến Kma\Library\Kma\Table\Table::normalizeState() chặn
     * mọi giá trị 'Đã lưu trữ' (2) và 'Thùng rác' (-2) lọt xuống CSDL, kể cả khi
     * chúng đến từ nguồn ngoài giao diện (import, script, URL tự sửa).
     *
     * @var    int[]
     * @since  2.1.7
     */
    protected array $supportedStates = StateHelper::STATES_BASIC;
}