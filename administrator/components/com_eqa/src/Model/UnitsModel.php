<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Component\Eqa\Administrator\Traits\CampusScopedList;
use Kma\Library\Kma\Helper\StateHelper;

/**
 * List model của 'đơn vị' (unit).
 *
 * Đơn vị là dữ liệu DÙNG CHUNG toàn Học viện, có gắn nhãn cơ sở đào tạo để
 * phân loại (và để suy ra cơ sở của người lao động qua unit_id). Vì vậy danh
 * sách KHÔNG bị lọc mặc định theo cơ sở đang làm việc; chỉ lọc khi người dùng
 * chủ động chọn cơ sở trong bộ lọc.
 *
 * @since 1.0.0
 */
class UnitsModel extends ListModel
{
    use CampusScopedList;

    /**
     * Thực thể DANH MỤC — dùng đủ 4 trạng thái (kể cả 'Đã lưu trữ' và 'Thùng rác').
     *
     * @var    int[]
     * @since  2.1.7
     */
    protected array $supportedStates = StateHelper::STATES_FULL;

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] = array('id', 'code', 'name', 'state', 'campus_id', 'campus_name');
        parent::__construct($config, $factory);
    }

    public function getListQuery()
    {
        $db = $this->getDatabase();

        $query = parent::getListQuery();
        $query->from($db->quoteName('#__eqa_units', 'a'))
            ->select($db->quoteName('a') . '.*')
            ->select($db->quoteName('c.name', 'campus_name'))
            ->leftJoin(
                $db->quoteName('#__eqa_campuses', 'c')
                . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.campus_id')
            );

        // Bộ lọc cơ sở đào tạo dạng tùy chọn (2.1.6)
        $this->applyOptionalCampusFilter($query, 'a.campus_id');

        //Lọc theo trạng thái (mặc định: chỉ hiển thị bản ghi đang được sử dụng)
        $this->applyStateFilter($query);

        $query->order($db->quoteName('a.parent_id') . ' ASC, ' . $db->quoteName('a.name') . ' ASC');

        return $query;
    }
}
