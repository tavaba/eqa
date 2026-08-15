<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Library\Kma\Helper\StateHelper;

class SpecialitiesModel extends ListModel{
    /**
     * Thực thể DANH MỤC — dùng đủ 4 trạng thái (kể cả 'Đã lưu trữ' và 'Thùng rác').
     *
     * @var    int[]
     * @since  2.1.7
     */
    protected array $supportedStates = StateHelper::STATES_FULL;

    /**
     * Bảng #__eqa_specialities được truy vấn KHÔNG dùng alias, nên cột trạng
     * thái phải khai báo trần.
     *
     * @var    string
     * @since  2.1.7
     */
    protected string $stateColumn = 'state';

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('code','state','ordering');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'code', $direction = 'asc'): void
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $query =  $db->getQuery(true)
            ->from('#__eqa_specialities')
            ->select('*');

        //Lọc theo trạng thái (mặc định: chỉ hiển thị bản ghi đang được sử dụng)
        $this->applyStateFilter($query);

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','code'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
}