<?php
namespace Kma\Component\Survey\Administrator\Model;

use Exception;
use Kma\Library\Kma\Model\AdminModel;

defined('_JEXEC') or die();

class ClassModel extends AdminModel
{
    public function getLearnerIds(int $classId): array
    {
        $db = $this->getDatabase();

        //Load learner codes
        $db->setQuery('SELECT learners FROM #__survey_classes WHERE id='.$classId);
        $learners = $db->loadResult();
        if(empty($learners))
            return [];
        $learnerCodes = explode(',', $learners);

        //Load learner ids
        $quotedLearnerCodes = array_map(function ($code) use ($db) {return $db->quote($code);}, $learnerCodes);
        $db->setQuery('SELECT id,code FROM #__survey_respondents WHERE code IN ('.implode(', ', $quotedLearnerCodes).')');
        $learnerIds = $db->loadAssocList('code','id');

        //Check if all learners exist in the database and return their IDs
        $missingLearners = array_diff($learnerCodes, array_keys($learnerIds));
        if(!empty($missingLearners))
            throw new Exception('Các HVSV sau đây chưa tồn tại trong danh sách người được khảo sát: '.implode(', ',$missingLearners));
        return array_values($learnerIds);
    }
}
