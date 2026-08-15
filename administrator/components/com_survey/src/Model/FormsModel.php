<?php
namespace Kma\Component\Survey\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Survey\Administrator\Base\ListModel;
use Kma\Library\Kma\Helper\StateHelper;

class FormsModel extends ListModel
{
    /**
     * Thực thể DANH MỤC — dùng đủ 4 trạng thái.
     *
     * @var    int[]
     * @since  1.0.5
     */
    protected array $supportedStates = StateHelper::STATES_FULL;

    /**
     * Bảng chính mang alias 'f' trong getListQuery().
     *
     * @var    string
     * @since  1.0.5
     */
    protected string $stateColumn = 'f.state';

    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id', 'type', 'createdBy', 'state');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'id', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery_bak()
    {
        $db = $this->getDatabase();
        $columns = [
            $db->quoteName('f.id'),
            $db->quoteName('f.title'),
            $db->quoteName('f.description'),
            $db->quoteName('f.model'),
            $db->quoteName('f.created_by'),
            $db->quoteName('f.modified_by'),
            $db->quoteName('f.modified'),
            $db->quoteName('f.state'),
        ];
        $query =  $db->getQuery(true)
            ->select($columns)
            ->from('#__survey_forms AS f');
        $query->select('GROUP_CONCAT(p.title SEPARATOR ",") AS topics')
            ->leftJoin($db->quoteName('#__survey_form_topic', 'fp'), 'fp.form_id = f.id')
            ->leftJoin($db->quoteName('#__survey_topics', 'p'), 'p.id = fp.topic_id')
            ->group('f.id');

        //Filtering
        //Lọc theo trạng thái (mặc định: chỉ hiển thị bản ghi đang được sử dụng)
        $this->applyStateFilter($query);

        $topicId = $this->getState('filter.topic_id');
        if ($topicId) {
            $query->where('EXISTS (
                SELECT 1
                FROM #__survey_form_topic AS fp2
                WHERE fp2.form_id = f.id
                AND fp2.topic_id = ' . (int)$topicId . ')');
        }

        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . $db->escape(trim($search), true)  . '%');
            $query->where('(title LIKE ' . $like . ')');
        }

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering', 'id'));
        $orderingDir = $query->db->escape($this->getState('list.direction', 'desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $columns = [
            $db->quoteName('f.id'),
            $db->quoteName('f.created_by'),     //Must be left as is for access control
            $db->quoteName('f.asset_id'),       //Must be left as is for access control
            $db->quoteName('f.title'),
            $db->quoteName('f.description'),
            $db->quoteName('f.modified_by'),
            $db->quoteName('f.modified'),
            $db->quoteName('f.state'),
        ];
        $query =  $db->getQuery(true)
            ->select($columns)
            ->from('#__survey_forms AS f');

        //Join a 'topics' column with concatenated topic ids, bg_colors and titles.
        //The value will look like: "Teaching Evaluation::#ff6666||Exam Feedback::#66ccff"
        $query->select('GROUP_CONCAT(CONCAT(p.title, "::", p.bg_color) SEPARATOR "||") AS topics')
            ->leftJoin($db->quoteName('#__survey_form_topic', 'fp'), 'fp.form_id = f.id')
            ->leftJoin($db->quoteName('#__survey_topics', 'p'), 'p.id = fp.topic_id')
            ->group('f.id');

        //Filtering
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . $db->escape(trim($search), true)  . '%');
            $query->where('(title LIKE ' . $like . ')');
        }

        $topicId = $this->getState('filter.topic_id');
        if ($topicId) {
            $query->where('EXISTS (
                SELECT 1
                FROM #__survey_form_topic AS fp2
                WHERE fp2.form_id = f.id
                AND fp2.topic_id = ' . (int)$topicId . ')');
        }

        //Lọc theo trạng thái (mặc định: chỉ hiển thị bản ghi đang được sử dụng)
        $this->applyStateFilter($query);

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering', 'id'));
        $orderingDir = $query->db->escape($this->getState('list.direction', 'desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }

    public function canCreate(?string $specificAction = 'com.create.form'): bool
    {
        return parent::canCreate($specificAction);
    }

}