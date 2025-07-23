<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

class LearnerexamsModel extends ListModel {
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('academicyear', 'term', 'examseason', 'name');
        parent::__construct($config, $factory);
    }
	public function canViewList(): bool
	{
		//1. Check if the user has manage permission on this component
		$acceptedPermissions = ['core.manage', 'eqa.supervise'];
		if(GeneralHelper::checkPermissions($acceptedPermissions))
			return true;

		//2. Or if he/she is the selected learner that views his/her own information
		//a. There must be a learner ID
		$selectedLearnerId = $this->getState('filter.learner_id');
		if(empty($selectedLearnerId))
			return false;

		//b. And the corresponding learner code must exist...
		$db = DatabaseHelper::getDatabaseDriver();
		$db->setQuery('SELECT `code` FROM #__eqa_learners WHERE id='.$selectedLearnerId);
		$learnerCode = $db->loadResult();
		if(empty($learnerCode))
			return false;

		//c. ... and match with signed-in user's learner code
		$signedInLearnerCode = GeneralHelper::getSignedInLearnerCode();
		if (empty($signedInLearnerCode) || ($learnerCode != $signedInLearnerCode))
			return false;
		return true;
	}
	public function getListQuery()
    {
        //Get learner_id
	    //This must be set by View before calling model
	    $learnerId = $this->getState('filter.learner_id');
		if(empty($learnerId))
			return null;

        $db = DatabaseHelper::getDatabaseDriver();
        $columns = $db->quoteName(
            array('f.code',       'e.term', 'e.name',     'a.exam_id', 'b.name', 'd.type',     'd.value',     'c.pam1', 'c.pam2', 'c.pam', 'a.attempt', 'c.allowed', 'a.debtor', 'a.anomaly','a.mark_orig', 'a.ppaa', 'a.mark_ppaa', 'a.mark_final', 'a.module_mark', 'a.module_grade', 'a.conclusion'),
            array('academicyear', 'term',   'examseason', 'id',        'name',   'stimulType', 'stimulValue', 'pam1',   'pam2',   'pam',   'attempt',   'allowed',   'isDebtor', 'anomaly',  'origMark',    'ppaa',   'ppaaMark',    'finalMark',    'moduleMark',    'moduleGrade',    'conclusion')
        );
        $query = $db->getQuery(true)
            ->select($columns)
            ->from('#__eqa_exam_learner AS a')
            ->leftJoin('#__eqa_exams AS b','b.id=a.exam_id')
            ->leftJoin('#__eqa_class_learner AS c','c.class_id=a.class_id AND c.learner_id=a.learner_id')
	        ->leftJoin('#__eqa_stimulations AS d', 'd.id=a.stimulation_id')
	        ->leftJoin('#__eqa_examseasons AS e', 'e.id=b.examseason_id')
	        ->leftJoin('#__eqa_academicyears AS f', 'f.id=e.academicyear_id')
            ->where('a.learner_id = ' . $learnerId);

	    //Ordering
	    $orderingCol = $query->db->escape($this->getState('list.ordering','name'));
	    $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
	    $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
            $query->where('`b`.`name` LIKE ' . $like );
        }

		$examseasonId = $this->getState('filter.examseason_id');
	    if(is_numeric($examseasonId))
	    {
			if ($examseasonId==0)
				$examseasonId = DatabaseHelper::getDefaultExamseason()->id;
			$query->where('`b`.`examseason_id`='.(int)$examseasonId);
	    }

		$academicyearId = $this->getState('filter.academicyear_id');
		if(is_numeric($academicyearId))
			$query->where('`e`.`academicyear_id`='.(int)$academicyearId);

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
		$id = $this->getState('filter.examsession_id');
		if(empty($id))
			return null;
		return (int)$id;
	}
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.class_id');
        $id .= ':' . $this->getState('filter.search');
	    $id .= ':' . $this->getState('filter.allowed');
	    $id .= ':' . $this->getState('filter.learner_id');
        return parent::getStoreId($id);
    }
}