<?php
namespace Kma\Component\Survey\Administrator\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Library\Kma\Helper\DatabaseHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Model\ListModel;

class UnitsModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id', 'type', 'code', 'size');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'id', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $subquerySize = $db->getQuery(true)
            ->select('COUNT(1)')
            ->from('#__survey_respondents AS b')
            ->where('b.unit_id=a.id');
        $columns = [
            $db->quoteName('a.id'),
            $db->quoteName('a.created_by'),     //Must be left as is for access control
            $db->quoteName('a.type'),
            $db->quoteName('a.code'),
            $db->quoteName('a.name'),
            $db->quoteName('a.note')
        ];
        $query =  $db->getQuery(true)
            ->from('#__survey_units AS a')
            ->select($columns)
            ->select('('.$subquerySize.') AS ' . $db->quoteName('size'));

        //Filtering
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . $db->escape(trim($search), true)  . '%');
            $query->where('(name LIKE '.$like.' OR code LIKE '.$like.')');
        }

        $type = $this->getState('filter.type');
        if(is_numeric($type))
            $query->where($db->quoteName('type').'='.(int)$type);

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering', 'id'));
        $orderingDir = $query->db->escape($this->getState('list.direction', 'desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }

    public function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.type');
        return parent::getStoreId($id);
    }

    public function updateUnits(int $type, array $units): int
    {
        $db = DatabaseHelper::getDatabaseDriver();
        $userId = Factory::getApplication()->getIdentity()->id;
        $now = $db->quote(DatetimeHelper::getCurrentHanoiDatetime());
        $columns = $db->quoteName([
            'type',
            'code',
            'name',
            'note',
            'created',
            'created_by',
            'modified',
            'modified_by'
        ]);
        $tuples = [];
        foreach ($units as $unit) {
            $values = [
                $type,
                $db->quote($unit['code']),
                $db->quote($unit['name']),
                empty($unit['note']) ? 'NULL' : $db->quote($unit['note']),
                $now,
                (int)$userId,
                $now,
                (int)$userId];
            $tuples[] = implode(',', $values);
        }

        //Prepare query
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__survey_units'))
            ->columns($columns)
            ->values($tuples);
        $query = str_replace('INSERT', 'INSERT IGNORE', $query->__toString());
        $db->setQuery($query)->execute();

        //Return number of affected rows
        return $db->getAffectedRows();
    }
    public function canCreate(?string $specificAction = 'com.create.unit'): bool
    {
        return parent::canCreate($specificAction);
    }
    public function canSync():bool
    {
        $user = $this->user;
        return $user->authorise('com.sync.unit', $this->option);
    }
}