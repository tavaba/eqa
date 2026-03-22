<?php
namespace Kma\Component\Survey\Administrator\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Component\Survey\Administrator\Base\ListModel;

class RespondentsModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('code', 'lastname');
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
            ->from('#__survey_respondents AS r')
            ->select('r.*');

        //Filtering
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $like = $db->quote('%' . $db->escape(trim($search), true)  . '%');
            $query->where('(r.code LIKE '.$like.' OR concat_ws(" ", r.lastname, r.firstname, r.name) LIKE '.$like.')');
        }

        $type = $this->getState('filter.type');
        if(is_numeric($type))
            $query->where($db->quoteName('r.type').'='.(int)$type);
        $isPerson = $this->getState('filter.is_person');
        if(is_numeric($isPerson))
            $query->where('r.is_person=' . (int)$isPerson);
        $unitId = $this->getState('filter.unit_id');
        if(is_numeric($unitId))
            $query->where($db->quoteName('r.unit_id').'='.(int)$unitId);
        $respondentgroupId = $this->getState('filter.respondentgroup_id');
        if(is_numeric($respondentgroupId))
            $query->rightJoin('#__survey_respondentgroup_respondent AS rgr','rgr.respondent_id=r.id AND rgr.group_id='.$respondentgroupId)
                ->where($db->quoteName('rgr.group_id') . ' = ' . $respondentgroupId);
        $gender = $this->getState('filter.gender');
        if(is_numeric($gender))
            $query->where($db->quoteName('gender').'='.(int)$gender);

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering', 'firstname'));
        $orderingDir = $query->db->escape($this->getState('list.direction', 'asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);
        if($orderingCol=='firstname')
            $query->order('lastname ASC');

        return $query;
    }

    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.type');
        $id .= ':' . $this->getState('filter.is_person');
        $id .= ':' . $this->getState('filter.unit_id');
        $id .= ':' . $this->getState('filter.respondentgroup_id');
        $id .= ':' . $this->getState('filter.gender');
        return parent::getStoreId($id);
    }

    public function getCurrentSize(): int
    {
        $db = $this->getDatabase();
        $db->setQuery('SELECT COUNT(*) FROM #__survey_respondents');
        return $db->loadResult();
    }

    public function getMinModified(int $type, array $ids=[]):?string
    {
        $db = $this->getDatabase();
        $query =  $db->getQuery(true)
            ->from('#__survey_respondents')
            ->select('MIN(modified)');           //The MIN() function ignores NULL values.
        if($type)
            $query->where($db->quoteName('type').'='.$type);
        if(!empty($ids))
            $query->where($db->quoteName('id').' IN ('.implode(',', $ids).')');
        $db->setQuery($query);
        return $db->loadResult();
    }

    /**
     * @param int $type
     * @param string $courseCode
     * @param array $learners An array of respondents. Each element have these fields:
     *                  ['code','firstname','lastname','phone','email']. Some fields may be null.
     * @return int Number of updated rows
     * @throws Exception
     * @since 1.0.0
     */
    public function updateLearners(string $courseCode, array $learners): int
    {
        if(count($learners)==0)
            return 0;

        $db = $this->getDatabase();

        //Step 1. Retrieve the unit_id for the given $courseCode.
        $db->setQuery('SELECT `id` FROM #__survey_units WHERE `code`=' . $db->quote($courseCode));
        $unitId = $db->loadResult();
        if(empty($unitId))
            throw new Exception('Không tìm thấy đơn vị có mã ' . htmlspecialchars($courseCode));

        //STEP 2. Update and Insert new records.
        $quotedNow = $db->quote(DatetimeHelper::getCurrentHanoiDatetime());
        $userId = Factory::getApplication()->getIdentity()->id;
        $items = [];
        foreach ($learners as $learner)
        {
            //Prepare values to be inserted into DB.
            $item=[];
            $item['code']=$db->quote($learner['code']);
            $item['type']=RespondentHelper::RESPONDENT_TYPE_LEARNER;
            $item['unit_id']=$unitId;
            $item['firstname'] = isset($learner['firstname']) ? $db->quote($learner['firstname']) : 'NULL';
            $item['lastname'] = isset($learner['lastname']) ? $db->quote($learner['lastname']) : 'NULL';
            $item['gender'] = isset($learner['gender']) ? $learner['gender'] : 'NULL';
            $item['email'] = isset($learner['email']) ? $db->quote($learner['email']) : 'NULL';
            $item['phone'] = isset($learner['phone']) ? $db->quote($learner['phone']) : 'NULL';
            $item['note'] = isset($learner['note']) ? $db->quote($learner['note']) : 'NULL';
            $item['modified']=$quotedNow;
            $item['modified_by']=$userId;
            $item['created']=$quotedNow;
            $item['created_by']=$userId;
            $items[] = $item;
        }

        // Get column names from the first item
        $columns = array_keys($items[0]);

        // Quote column names with backticks
        $colsSql = '`' . implode('`, `', $columns) . '`';

        // Build values list for all items
        $valuesList = [];
        foreach ($items as $item) {
            $valuesList[] = '(' . implode(', ', array_values($item)) . ')';
        }

        // Build ON DUPLICATE KEY UPDATE part (exclude 'code' since it's UNIQUE)
        $updates = [];
        foreach ($columns as $col) {
            if ($col !== 'code' && $col !== 'created' && $col !== 'created_by') {
                $updates[] = "`$col` = VALUES(`$col`)";
            }
        }
        $updatesSql = implode(', ', $updates);

        // Final query (multi-row insert with update)
        $query = "
        INSERT INTO `#__survey_respondents` ($colsSql)
        VALUES " . implode(", ", $valuesList) . "
        ON DUPLICATE KEY UPDATE $updatesSql
    ";

        $db->setQuery($query);
        $db->execute();
        if(!$db->execute())
            throw new Exception("Có lỗi khi cập nhật thông tin vào CSDL");
        return $db->getAffectedRows();
    }

    public function updateEmployees($employees): void
    {
        if(count($employees)==0)
            return;

        $db = $this->getDatabase();

        //Get unit code mapping and check if there are any missing units.
        $db->setQuery('SELECT `id`,`code` FROM #__survey_units WHERE `type`='.RespondentHelper::RESPONDENT_UNIT_TYPE_DEPARTMENT);
        $unitIds = $db->loadAssocList('code','id');
        foreach ($employees as $employee){
            $unitCode = $employee['unit_code'];
            if(!isset($unitIds[$unitCode]))
                throw new Exception('Không tìm thấy đơn vị có mã ' . htmlspecialchars($unitCode));
        }

        //STEP 2. Update and Insert new records.
        $quotedNow = $db->quote(DatetimeHelper::getCurrentHanoiDatetime());
        $userId = Factory::getApplication()->getIdentity()->id;
        $items = [];
        foreach ($employees as $employee)
        {
            //Prepare values to be inserted into DB.
            $item=[];
            $item['code']=$db->quote($employee['code']);
            $item['type']=RespondentHelper::RESPONDENT_TYPE_EMPLOYEE;
            $item['unit_id']=$unitIds[$employee['unit_code']];
            $item['firstname'] = isset($employee['firstname']) ? $db->quote($employee['firstname']) : 'NULL';
            $item['lastname'] = isset($employee['lastname']) ? $db->quote($employee['lastname']) : 'NULL';
            $item['gender'] = isset($employee['gender']) ? $employee['gender'] : 'NULL';
            $item['email'] = isset($employee['email']) ? $db->quote($employee['email']) : 'NULL';
            $item['phone'] = isset($employee['phone']) ? $db->quote($employee['phone']) : 'NULL';
            $item['note'] = isset($employee['note']) ? $db->quote($employee['note']) : 'NULL';
            $item['modified']=$quotedNow;
            $item['modified_by']=$userId;
            $item['created']=$quotedNow;
            $item['created_by']=$userId;
            $items[] = $item;
        }

        // Get column names from the first item
        $columns = array_keys($items[0]);

        // Quote column names with backticks
        $colsSql = '`' . implode('`, `', $columns) . '`';

        // Build values list for all items
        $valuesList = [];
        foreach ($items as $item) {
            $valuesList[] = '(' . implode(', ', array_values($item)) . ')';
        }

        // Build ON DUPLICATE KEY UPDATE part (exclude 'code' since it's UNIQUE)
        $updates = [];
        foreach ($columns as $col) {
            if ($col !== 'code' && $col !== 'created' && $col !== 'created_by') {
                $updates[] = "`$col` = VALUES(`$col`)";
            }
        }
        $updatesSql = implode(', ', $updates);

        // Final query (multi-row insert with update)
        $query = "
        INSERT INTO `#__survey_respondents` ($colsSql)
        VALUES " . implode(", ", $valuesList) . "
        ON DUPLICATE KEY UPDATE $updatesSql
    ";

        $db->setQuery($query);
        $db->execute();
        if(!$db->execute())
            throw new Exception("Có lỗi khi cập nhật thông tin vào CSDL");
    }
    public function updateVisitingTeachers($visitingLecturers): void
    {
        if(count($visitingLecturers)==0)
            return;

        $db = $this->getDatabase();

        //Get the default unit id for visiting lecturers.
        $db->setQuery('SELECT `id` FROM #__survey_units WHERE `code`=\'GVM\'');
        $defaultUnitId = $db->loadResult();
        if(empty($defaultUnitId))
            throw new Exception('Không tìm thấy đơn vị mặc định (GVM) cho giảng viên thỉnh giảng');

        $quotedNow = $db->quote(DatetimeHelper::getCurrentHanoiDatetime());
        $userId = Factory::getApplication()->getIdentity()->id;
        $items = [];
        foreach ($visitingLecturers as $employee)
        {
            //Prepare values to be inserted into DB.
            $item=[];
            $item['code']=$db->quote($employee['code']);
            $item['type']=RespondentHelper::RESPONDENT_TYPE_VISITING_LECTURER;
            $item['unit_id']=$defaultUnitId;
            $item['firstname'] = isset($employee['firstname']) ? $db->quote($employee['firstname']) : 'NULL';
            $item['lastname'] = isset($employee['lastname']) ? $db->quote($employee['lastname']) : 'NULL';
            $item['gender'] = isset($employee['gender']) ? $employee['gender'] : 'NULL';
            $item['email'] = isset($employee['email']) ? $db->quote($employee['email']) : 'NULL';
            $item['phone'] = isset($employee['phone']) ? $db->quote($employee['phone']) : 'NULL';
            $item['note'] = isset($employee['note']) ? $db->quote($employee['note']) : 'NULL';
            $item['modified']=$quotedNow;
            $item['modified_by']=$userId;
            $item['created']=$quotedNow;
            $item['created_by']=$userId;
            $items[] = $item;
        }

        // Get column names from the first item
        $columns = array_keys($items[0]);

        // Quote column names with backticks
        $colsSql = '`' . implode('`, `', $columns) . '`';

        // Build values list for all items
        $valuesList = [];
        foreach ($items as $item) {
            $valuesList[] = '(' . implode(', ', array_values($item)) . ')';
        }

        /*
         * Build ON DUPLICATE KEY UPDATE part
         * - exclude 'code' since it's UNIQUE
         * - exclude 'unit_id' because this info is NOT managed by the CORE system
         */
        $updates = [];
        foreach ($columns as $col) {
            if ($col !== 'code' && $col !== 'created' && $col !== 'created_by' && $col !== 'unit_id') {
                $updates[] = "`$col` = VALUES(`$col`)";
            }
        }
        $updatesSql = implode(', ', $updates);

        // Final query (multi-row insert with update)
        $query = "
        INSERT INTO `#__survey_respondents` ($colsSql)
        VALUES " . implode(", ", $valuesList) . "
        ON DUPLICATE KEY UPDATE $updatesSql
    ";

        $db->setQuery($query);
        $db->execute();
        if(!$db->execute())
            throw new Exception("Có lỗi khi cập nhật thông tin vào CSDL");
    }
    public function canCreate(?string $specificAction = 'com.create.respondent'): bool
    {
        return parent::canCreate($specificAction);
    }
    public function canSync():bool
    {
        $user = $this->user;
        return $user->authorise('com.sync.respondent', $this->option);
    }
}