<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class ExamexamineesModel extends ListModel {
    public function __construct($config = [], MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('code','learner_code','firstname','lastname', 'stimulation','attempt','allowed', 'debtor', 'conclusion');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'firstname', $direction = 'asc')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        //Get exam id
        //This param must be set by the View before calling this method
        $examId = $this->getState('filter.exam_id');
        if(!is_numeric($examId))
            return null;

        $db = DatabaseHelper::getDatabaseDriver();
        $columns = $db->quoteName(
            array('a.learner_id','a.code','b.code',       'b.lastname', 'b.firstname', 'a.attempt', 'c.pam1', 'c.pam2','c.pam','c.allowed', 'e.type',      'a.debtor','d.name',  'a.anomaly',  'a.mark_final', 'a.module_mark', 'a.module_grade', 'a.conclusion'),
            array('id',          'code',  'learner_code', 'lastname',   'firstname',   'attempt',    'pam1',   'pam2', 'pam',  'allowed',   'stimulation', 'debtor',  'examroom','anomaly'    ,'mark_final',   'module_mark',   'module_grade',   'conclusion')
        );
        $query = $db->getQuery(true)
            ->select($columns)
            ->from('#__eqa_exam_learner AS a')
            ->leftJoin('#__eqa_learners AS b','a.learner_id = b.id')
            ->leftJoin('#__eqa_class_learner AS c', 'a.learner_id=c.learner_id AND a.class_id=c.class_id')
	        ->leftJoin('#__eqa_examrooms AS d', 'a.examroom_id=d.id')
	        ->leftJoin('#__eqa_stimulations AS e', 'a.stimulation_id=e.id')
            ->where('a.exam_id = '.(int)$examId);

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
            $query->where('(concat_ws(" ",`b`.`lastname`,`b`.`firstname`) LIKE '.$like.' OR `b`.`code` LIKE '.$like.')');
        }

        $attempt = $this->getState('filter.attempt');
        if(is_numeric($attempt)){
            $query->where('`attempt` = '.(int)$attempt);
        }

        $allowed = $this->getState('filter.allowed');
        if(is_numeric($allowed)){
            $query->where('`allowed` = '.(int)$allowed);
        }

	    $isDebtor = $this->getState('filter.debtor');
	    if(is_numeric($isDebtor)){
		    $query->where($db->quoteName('a.debtor'). '=' . (int)$isDebtor);
	    }

		$stimulationType = $this->getState('filter.stimulation_type');
		if(is_numeric($stimulationType))
		{
			if($stimulationType == -1)
				$query->where($db->quoteName('e.type') .'>0');
			else
				$query->where($db->quoteName('e.type') . '=' .(int)$stimulationType);
		}

		$anomaly = $this->getState('filter.anomaly');
		if(is_numeric($anomaly))
			$query->where('a.anomaly=' . (int)$anomaly);

	    $conclusion = $this->getState('filter.conclusion');
	    if(is_numeric($conclusion))
		    $query->where('a.conclusion=' . (int)$conclusion);



	    $concluded = $this->getState('filter.concluded');
	    if(is_numeric($concluded))
	    {
		    if($concluded==0)
			    $query->where('conclusion IS NULL');
		    else
			    $query->where('conclusion IS NOT NULL');
	    }


	    //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','firstname'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);
        $query->order('firstname ASC, lastname ASC');

        return $query;
    }
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.exam_id');
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.allowed');
        $id .= ':' . $this->getState('filter.attempt');
        return parent::getStoreId($id);
    }

}