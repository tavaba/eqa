<?php
namespace Kma\Component\Survey\Administrator\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Library\Kma\Helper\DatabaseHelper;
use Kma\Library\Kma\Model\ListModel;

class ClassesModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id', 'code', 'term', 'academicyear','size', 'startDate', 'endDate');
        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'id', $direction = 'desc'): void
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $columns = [
            $db->quoteName('id'),
            $db->quoteName('code'),
            $db->quoteName('term'),
            $db->quoteName('academicyear'),
            $db->quoteName('subject'),
            $db->quoteName('size'),
            $db->quoteName('lecturer'),
            $db->quoteName('start_date')     . ' AS ' . $db->quoteName('startDate'),
            $db->quoteName('end_date')       . ' AS ' . $db->quoteName('endDate'),
        ];
        $query =  $db->getQuery(true)
            ->from('#__survey_classes')
            ->select($columns);

        //Filtering
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . $db->escape(trim($search), true)  . '%');
            $query->where('(code LIKE ' . $like . ' OR subject LIKE ' . $like . ')');
        }

        $term = $this->getState('filter.term');
        if(is_numeric($term))
            $query->where('term='.(int)$term);

        $acdemicYear = $this->getState('filter.academicyear');
        if(is_numeric($acdemicYear))
            $query->where('academicyear='.(int)$acdemicYear);

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering', 'id'));
        $orderingDir = $query->db->escape($this->getState('list.direction', 'desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }

    public function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.search');
        return parent::getStoreId($id);
    }

    public function getLastAcademicyearAndTerm(): array
    {
        $db = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select(['academicyear', 'term'])
            ->from('#__survey_classes')
            ->order('academicyear DESC, term DESC')
            ->setLimit(1);
        $db->setQuery($query);
        $record = $db->loadObject();
        if(empty($record))
            return [0,0];
        return [$record->academicyear,$record->term];
    }

    public function updateClasses(array $classes):array
    {
        $db = DatabaseHelper::getDatabaseDriver();
        $columns = $db->quoteName(['code', 'term', 'academicyear', 'subject', 'size', 'lecturer', 'start_date', 'end_date']);
        $countAdded = 0;
        $countUpdated=0;
        foreach ($classes as $class) {
            //Check if 'code' already exists in the database.
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__survey_classes')
                ->where("code={$db->quote($class['code'])}");
            $item = $db->setQuery($query)->loadAssoc();

            //Insert new record if it does not exist. Otherwise, update existing one.
            if(empty($item))
            {
                $values = [
                    $db->quote($class['code']),
                    (int)$class['term'],
                    (int)$class['academicyear'],
                    $db->quote($class['subject']),
                    0,                                  //Class size
                    is_null($class['lecturer']) ? '' : $db->quote($class['lecturer']),
                    is_null($class['start_date']) ? 'NULL' : $db->quote($class['start_date']),
                    is_null($class['end_date']) ? 'NULL' : $db->quote($class['end_date'])
                ];
                $tuple = implode(',', $values);
                $query = $db->getQuery(true)
                    ->insert('#__survey_classes')
                    ->columns($columns)
                    ->values($tuple);
                $db->setQuery($query);
                if(!$db->execute())
                    throw new Exception('Failed to insert class.');
                $countAdded++;
                continue;
            }

            //Update existing record if it already exists and there is any change.
            $toUpdate = ($item['lecturer'] != $class['lecturer'])
                || ($item['start_date'] != $class['start_date'])
                || ($item['end_date'] != $class['end_date']);
            if(!$toUpdate)
                continue;
            $quotedLecturer = is_null($class['lecturer']) ? '' : $db->quote($class['lecturer']);
            $quotedStartDate = is_null($class['start_date']) ? 'NULL' : $db->quote($class['start_date']);
            $quotedEndDate   = is_null($class['end_date']) ? 'NULL' : $db->quote($class['end_date']);

            $query = $db->getQuery(true)
                ->update('#__survey_classes')
                ->set([
                    $db->quoteName('lecturer').'='.$quotedLecturer,
                    $db->quoteName('start_date').'='.$quotedStartDate,
                    $db->quoteName('end_date').'='.$quotedEndDate
                ])
                ->where("id={$item['id']}");
            $db->setQuery($query);
            if(!$db->execute())
                throw new Exception('Failed to update class.');
            $countUpdated++;
        }
        return [$countAdded, $countUpdated];
    }
    public function getCompactInfo(array $classIds):array
    {
        $db = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'code', 'subject', 'lecturer', 'size', 'start_date','end_date']))
            ->from('#__survey_classes')
            ->where('id IN ('.implode(',',$classIds).')');
        $db->setQuery($query);
        return $db->loadObjectList();
    }

    public function canSync():bool
    {
        $user = $this->user;
        return $user->authorise('com.sync.class', $this->option);
    }
}