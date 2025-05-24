<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;

class ClassesModel extends EqaListModel{
    public function __construct($config = [], MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('coursegroup','code','name','size','npam', 'academicyear','term');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'academicyear', $direction = 'desc')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $columns = $db->quoteName(
            array('a.id','a.coursegroup','a.code','a.name',  'a.lecturer_id',  'b.code',   'a.term', 'a.size', 'a.npam', 'a.description'),
            array('id',    'coursegroup', 'code',    'name', 'lecturer_id',  'academicyear', 'term',   'size', 'npam',   'description')
        );
        $query =  $db->getQuery(true);
        $query->from('#__eqa_classes AS a')
            ->leftJoin('#__eqa_academicyears AS b','a.academicyear_id = b.id')
            ->select($columns);

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
            $query->where('(a.name LIKE ' . $like . 'OR a.code LIKE ' . $like . ')');
        }

        $subject_id = $this->getState('filter.subject_id');
        if(is_numeric($subject_id)){
            $query->where('a.subject_id = '.(int)$subject_id);
        }

        $pam = $this->getState('filter.pam');
        switch ($pam){
            case 'none':
                $query->where('`npam`=0');
                break;
            case 'full':
                $query->where($db->quoteName('npam') . '=' . $db->quoteName('size'));
                break;
            case 'partial':
                $query->where([
                    $db->quoteName('npam') . '>0',
                    $db->quoteName('npam') . '<' . $db->quoteName('size')
                ]);
        }

        $academicyear_id = $this->getState('filter.academicyear_id');
        if(is_numeric($academicyear_id)){
            $query->where('a.academicyear_id = '.(int)$academicyear_id);
        }

        $term = $this->getState('filter.term');
        if(is_numeric($term)){
            $query->where('a.term = '.(int)$term);
        }

        $lecturer_id = $this->getState('filter.lecturer_id');
        if(is_numeric($lecturer_id)){
            $query->where('a.lecturer_id = '.(int)$lecturer_id);
        }

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','academicyear'));
        $orderingDir = $query->db->escape($this->getState('list.direction','desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.subject_id');
        $id .= ':' . $this->getState('filter.testtype');
        $id .= ':' . $this->getState('filter.academicyear_id');
        $id .= ':' . $this->getState('filter.term');
        $id .= ':' . $this->getState('filter.lecturer_id');
        return parent::getStoreId($id);
    }
}