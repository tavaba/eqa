<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;

class CoursesModel extends EqaListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('code','admissionyear','degree','published','ordering');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'code', $direction = 'asc')
    {
        parent::populateState($ordering, $direction);
    }

    public function getListQuery()
    {
        $db = $this->getDatabase();
        $columns = $db->quoteName(
            array('a.id','a.code','a.admissionyear', 'a.description', 'a.published', 'a.ordering', 'b.spec_id', 'b.name', 'b.degree'),
            array('id',   'code','admissionyear',    'description', 'published',  'ordering', 'spec_id', 'program', 'degree')
        );

        $query =  parent::getListQuery();
        $query->from('#__eqa_courses AS a')
            ->leftJoin('#__eqa_programs AS b','a.prog_id = b.id')
            ->select($columns);

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
            $query->where('a.description LIKE '.$like);
        }

        $spec_id = $this->getState('filter.spec_id');
        if(is_numeric($spec_id)){
            $query->where('b.spec_id = '.(int)$spec_id);
        }

        $prog_id = $this->getState('filter.prog_id');
        if(is_numeric($prog_id)){
            $query->where('a.prog_id = '.(int)$prog_id);
        }

        $degree = $this->getState('filter.degree');
        if(is_numeric($degree)){
            $query->where('b.degree = '.(int)$degree);
        }

        $admissionyear = $this->getState('filter.admissionyear');
        if(is_numeric($admissionyear)){
            $query->where('a.admissionyear = '.(int)$admissionyear);
        }

        $published = $this->getState('filter.published');
        if(is_numeric($published)){
            $query->where('a.published = '.(int)$published);
        }


        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','code'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }
    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.spec_id');
        $id .= ':' . $this->getState('filter.prog_id');
        $id .= ':' . $this->getState('filter.degree');
        $id .= ':' . $this->getState('filter.admissionyear');
        $id .= ':' . $this->getState('filter.published');
        return parent::getStoreId($id);
    }
}