<?php

namespace Kma\Component\Eqa\Administrator\Model;

defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Library\Kma\Model\ListModel;

/**
 * Model danh sách kỳ sát hạch.
 *
 * @since 2.0.5
 */
class AssessmentsModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] = [
            'id', 'title', 'type', 'result_type',
            'start_date', 'end_date',
            'allow_registration', 'completed', 'published', 'ordering',
        ];
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'start_date', $direction = 'DESC'): void
    {
        parent::populateState($ordering, $direction);
    }

    // =========================================================================
    // Query
    // =========================================================================

    /**
     * Xây dựng câu truy vấn danh sách kỳ sát hạch.
     *
     * @return \Joomla\Database\QueryInterface
     * @since 2.0.5
     */
    public function getListQuery()
    {
        $db = $this->getDatabase();

        // Subquery: đếm số thí sinh đã đăng ký
        $subCandidates = $db->getQuery(true)
            ->select('COUNT(1)')
            ->from($db->quoteName('#__eqa_assessment_learner', 'al'))
            ->where($db->quoteName('al.assessment_id') . ' = ' . $db->quoteName('a.id'));

        $query = parent::getListQuery();
        $query
            ->from($db->quoteName('#__eqa_assessments', 'a'))
            ->select($db->quoteName([
                'a.id', 'a.title', 'a.type', 'a.result_type',
                'a.start_date', 'a.end_date', 'a.fee',
                'a.max_candidates', 'a.allow_registration',
                'a.completed', 'a.published', 'a.ordering',
            ]))
            ->select('(' . $subCandidates . ') AS ' . $db->quoteName('ncandidate'));

        // ----- Filtering -----

        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . trim($search) . '%');
            $query->where($db->quoteName('a.title') . ' LIKE ' . $like);
        }

        $type = $this->getState('filter.type');
        if (is_numeric($type)) {
            $query->where($db->quoteName('a.type') . ' = ' . (int) $type);
        }

        $resultType = $this->getState('filter.result_type');
        if (is_numeric($resultType)) {
            $query->where($db->quoteName('a.result_type') . ' = ' . (int) $resultType);
        }

        $completed = $this->getState('filter.completed');
        if (is_numeric($completed)) {
            $query->where($db->quoteName('a.completed') . ' = ' . (int) $completed);
        }

        $published = $this->getState('filter.published');
        if (is_numeric($published)) {
            $query->where($db->quoteName('a.published') . ' = ' . (int) $published);
        }

        // Filter theo năm (extract từ start_date, không cần cột riêng)
        $year = $this->getState('filter.year');
        if (is_numeric($year) && (int) $year > 0) {
            $query->where('YEAR(' . $db->quoteName('a.start_date') . ') = ' . (int) $year);
        }

        // ----- Ordering -----
        $orderingCol = $db->escape($this->getState('list.ordering', 'start_date'));
        $orderingDir = $db->escape($this->getState('list.direction', 'DESC'));
        $query->order($db->quoteName('a.' . $orderingCol) . ' ' . $orderingDir);

        return $query;
    }

    /**
     * @inheritDoc
     * @since 2.0.5
     */
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.type');
        $id .= ':' . $this->getState('filter.result_type');
        $id .= ':' . $this->getState('filter.completed');
        $id .= ':' . $this->getState('filter.published');
        $id .= ':' . $this->getState('filter.year');
        return parent::getStoreId($id);
    }

    // =========================================================================
    // Helpers dùng cho View
    // =========================================================================

    /**
     * Lấy danh sách các năm có ít nhất một kỳ sát hạch (từ start_date).
     * Dùng để populate dropdown filter năm.
     *
     * @return int[]
     * @since 2.0.5
     */
    public function getAvailableYears(): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('DISTINCT YEAR(' . $db->quoteName('start_date') . ') AS ' . $db->quoteName('year'))
            ->from($db->quoteName('#__eqa_assessments'))
            ->order($db->quoteName('year') . ' DESC');

        $db->setQuery($query);
        return array_map('intval', $db->loadColumn());
    }
}
