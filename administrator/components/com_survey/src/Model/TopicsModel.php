<?php
namespace Kma\Component\Survey\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Survey\Administrator\Base\ListModel;
use Kma\Library\Kma\Helper\StateHelper;

class TopicsModel extends ListModel
{
    /**
     * Thực thể DANH MỤC — dùng đủ 4 trạng thái.
     *
     * @var    int[]
     * @since  1.0.5
     */
    protected array $supportedStates = StateHelper::STATES_FULL;

    /**
     * Bảng #__survey_topics được truy vấn KHÔNG dùng alias.
     *
     * @var    string
     * @since  1.0.5
     */
    protected string $stateColumn = 'state';

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id','title', 'createdBy', 'state');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'title', $direction = 'asc'): void
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $columns = [
            $db->quoteName('id'),
            $db->quoteName('title'),
            $db->quoteName('description'),
            $db->quoteName('created_by'),     //Must be left as is for access control
            $db->quoteName('modified_by'),
            $db->quoteName('modified'),
            $db->quoteName('state')         . ' AS ' . $db->quoteName('state'),
        ];
        $query =  $db->getQuery(true)
            ->from('#__survey_topics')
            ->select($columns);

        //Filtering
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . $db->escape(trim($search), true)  . '%');
            $query->where('(title LIKE ' . $like . ')');
        }

        //Lọc theo trạng thái (mặc định: chỉ hiển thị bản ghi đang được sử dụng)
        $this->applyStateFilter($query);

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering', 'title'));
        $orderingDir = $query->db->escape($this->getState('list.direction', 'asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }

    public function canCreate(?string $specificAction = 'com.create.topic'): bool
    {
        return parent::canCreate($specificAction);
    }
}