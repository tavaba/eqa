<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\LearnerAccessHelper;
use RuntimeException;

class LearnerExamsModel extends ListModel {
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('academicyear', 'term', 'examseason', 'name');
        parent::__construct($config, $factory);
    }
	public function canViewList(): bool
	{
		//Từ 2.1.6, toàn bộ logic phân quyền (quản trị viên theo cơ sở đào tạo, hoặc
		//người học tự xem) được gom vào LearnerAccessHelper để dùng chung và nhất quán.
		$selectedLearnerId = (int) $this->getState('filter.learner_id');
		if($selectedLearnerId <= 0)
			return false;

		try {
			LearnerAccessHelper::assertCanViewLearner($selectedLearnerId);
			return true;
		} catch (RuntimeException $e) {
			return false;
		}
	}
	public function getListQuery()
    {
        //Get learner_id
	    //This must be set by View before calling model
	    $learnerId = $this->getState('filter.learner_id');
		if(empty($learnerId))
			return null;

		// Chốt chặn quyền theo cơ sở đào tạo (2.1.6): phủ mọi lối vào model.
		LearnerAccessHelper::assertCanViewLearner((int) $learnerId);

        $db = DatabaseHelper::getDatabaseDriver();
        $columns = $db->quoteName(
            array('a.exam_id', 'e.academicyear',       'e.term', 'e.name',     'b.name', 'd.type',     'd.value',     'c.pam1', 'c.pam2', 'c.pam', 'a.attempt', 'c.allowed', 'a.debtor', 'a.anomaly','a.mark_orig', 'a.ppaa', 'a.mark_ppaa', 'a.mark_final', 'a.module_mark', 'a.module_base4_mark', 'a.module_grade', 'a.conclusion'),
            array('id',        'academicyear', 'term',   'examseason', 'name',   'stimulType', 'stimulValue', 'pam1',   'pam2',   'pam',   'attempt',   'allowed',   'isDebtor', 'anomaly',  'origMark',    'ppaa',   'ppaaMark',    'finalMark',    'moduleMark',    'moduleBase4Mark',    'moduleGrade',    'conclusion')
        );
        $query = $db->getQuery(true)
            ->select($columns)
            ->from('#__eqa_exam_learner AS a')
            ->leftJoin('#__eqa_exams AS b','b.id=a.exam_id')
            ->leftJoin('#__eqa_class_learner AS c','c.class_id=a.class_id AND c.learner_id=a.learner_id')
	        ->leftJoin('#__eqa_stimulations AS d', 'd.id=a.stimulation_id')
	        ->leftJoin('#__eqa_examseasons AS e', 'e.id=b.examseason_id')
	        ->leftJoin('#__eqa_classes AS g', 'g.id=a.class_id')
            ->where('a.learner_id = ' . $learnerId);

	    //Ordering
	    $orderingCol = $query->db->escape($this->getState('list.ordering','name'));
	    $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
	    $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.trim($search).'%');
            $query->where('`b`.`name` LIKE ' . $like );
        }

		$examseasonId = $this->getState('filter.examseason_id');
	    if(is_numeric($examseasonId))
	    {
			if ($examseasonId==0)
				$examseasonId = DatabaseHelper::getDefaultExamseason()->id;
			$query->where('`b`.`examseason_id`='.(int)$examseasonId);
	    }

	    $subjectId = $this->getState('filter.subject_id');
	    if(is_numeric($subjectId))
		    $query->where('g.subject_id='.(int)$subjectId);

	    $academicyear = $this->getState('filter.academicyear');
	    if(is_numeric($academicyear))
		    $query->where('`e`.`academicyear`='.(int)$academicyear);

	    $term = $this->getState('filter.term');
	    if(is_numeric($term))
		    $query->where('`e`.`term`='.(int)$term);

	    $attempt = $this->getState('filter.attempt');
	    if(is_numeric($attempt))
		    $query->where('`a`.`attempt`='.(int)$attempt);

	    return $query;
    }

	public function getSelectedExamseasonId()
	{
		$id = $this->getState('filter.examseason_id');
		if(empty($id))
			return null;
		return (int)$id;
	}
}