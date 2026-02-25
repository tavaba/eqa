<?php
namespace Kma\Component\Survey\Administrator\Model;

use Exception;
use Kma\Library\Kma\Helper\DatabaseHelper;
use Kma\Library\Kma\Model\AdminModel;

defined('_JEXEC') or die();

class RespondentgroupModel extends AdminModel {
    public function canCreate(?string $specificAction = 'com.create.rgroup'): bool
    {
        return parent::canCreate($specificAction);
    }

    /**
     * @param int $groupId
     * @param array $respondentIds
     * @return int Number of added members
     * @throws Exception
     * @since 1.0.0
     */
    public function addMembers(int $groupId, array $respondentIds):int
    {
        $db = DatabaseHelper::getDatabaseDriver();
        $columns = ['group_id', 'respondent_id'];
        $values=[];
        foreach ($respondentIds as $respondentId) {
            $values[] = sprintf('%d,%d', $groupId,$respondentId);
        }
        $query = $db->getQuery(true)
            ->insert('#__survey_respondentgroup_respondent')
            ->columns($columns)
            ->values($values);
        $query = str_replace('INSERT','INSERT IGNORE',$query->__toString());
        $db->setQuery($query);
        if(!$db->execute())
            throw new Exception('Có lỗi phát sinh khi thêm thành viên vào nhóm');
        return $db->getAffectedRows();
    }

    /**
     * @param int $groupId
     * @param array $respondentIds
     * @return int Number of removed members
     * @throws Exception
     * @since 1.0.0
     */
    public function removeMembers(int $groupId, array $respondentIds):int
    {
        $db = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->delete('#__survey_respondentgroup_respondent')
            ->where([
                'group_id=' . $groupId,
                'respondent_id IN (' . implode(',', $respondentIds) . ')',
            ]);
        $db->setQuery($query);
        if(!$db->execute())
            throw new Exception('Có lỗi phát sinh khi xóa thành viên khỏi nhóm');
        return $db->getAffectedRows();
    }
}
