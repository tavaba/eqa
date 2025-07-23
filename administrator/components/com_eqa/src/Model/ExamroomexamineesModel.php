<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;

class ExamroomexamineesModel extends ListModel {
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('examinee_code', 'learner_code','firstname','lastname','attempt','allow', 'conclusion');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'examinee_code', $direction = 'asc')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        //Get examroom id
        //This param must be set by the View before calling this method
        $examroomId = $this->getState('filter.examroom_id');
        if(!is_numeric($examroomId))
            return null;

        $db = $this->getDatabase();
        $columns = $db->quoteName(
            array('a.learner_id', 'a.code',        'b.code',       'b.lastname', 'b.firstname', 'a.attempt', 'c.pam1', 'c.pam2','c.pam','c.allowed','a.mark_final', 'a.module_mark', 'a.module_grade', 'a.conclusion'),
            array('id',           'examinee_code', 'learner_code', 'lastname',    'firstname',   'attempt',  'pam1',   'pam2', 'pam',   'allowed',   'mark_final',  'module_mark',   'module_grade',   'conclusion')
        );
        $query = $db->getQuery(true)
            ->select($columns)
            ->from('#__eqa_exam_learner AS a')
            ->leftJoin('#__eqa_learners AS b','a.learner_id = b.id')
            ->leftJoin('#__eqa_class_learner AS c', 'a.learner_id=c.learner_id AND a.class_id=c.class_id')
            ->where('a.examroom_id = '.(int)$examroomId);

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
            $query->where('(concat_ws(" ",`b`.`lastname`,`b`.`firstname`) LIKE '.$like.' OR `b`.`code` LIKE '.$like .')');
        }

        $attempt = $this->getState('filter.attempt');
        if(is_numeric($attempt)){
            $query->where('`attempt` = '.(int)$attempt);
        }

        $allowed = $this->getState('filter.allowed');
        if(is_numeric($allowed)){
            $query->where('`allowed` = '.(int)$allowed);
        }

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','examinee_code'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.allowed');
        $id .= ':' . $this->getState('filter.attempt');
        return parent::getStoreId($id);
    }
}