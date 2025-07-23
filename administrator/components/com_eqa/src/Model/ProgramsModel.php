<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;

class ProgramsModel extends EqaListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('degree','format','approach','published','ordering','speciality');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'speciality', $direction = 'asc')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $columns = $db->quoteName(
            array('a.id', 'a.spec_id',  'a.name', 'a.degree','a.format','a.approach', 'a.firstrelease', 'a.lastupdate', 'a.description', 'a.published', 'a.ordering', 'b.code'),
            array('id',    'spec_id',    'name',   'degree','format','approach', 'firstrelease', 'lastupdate', 'description', 'published',   'ordering', 'speciality')
        );
        $query->from('#__eqa_programs AS a')
            ->leftJoin('#__eqa_specialities AS b','a.spec_id = b.id')
            ->select($columns);

        //Filtering
        $search = $this->getState('filter.search');
        if(!empty($search)){
            $like = $db->quote('%'.$search.'%');
            $query->where('a.name LIKE '.$like);
        }

        $spec_id = $this->getState('filter.spec_id');
        if(is_numeric($spec_id)){
            $query->where('a.spec_id = '.(int)$spec_id);
        }

        $degree = $this->getState('filter.degree');
        if(is_numeric($degree)){
            $query->where('a.degree = '.(int)$degree);
        }

        $format = $this->getState('filter.format');
        if(is_numeric($format)){
            $query->where('a.format = '.(int)$format);
        }

        $approach = $this->getState('filter.approach');
        if(is_numeric($approach)){
            $query->where('a.approach = '.(int)$approach);
        }

        $published = $this->getState('filter.published');
        if(is_numeric($published)){
            $query->where('a.published = '.(int)$published);
        }

        //Ordering
        $orderingCol = $query->db->escape($this->getState('list.ordering','speciality'));
        $orderingDir = $query->db->escape($this->getState('list.direction','asc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }

    public function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.spec_id');
        $id .= ':' . $this->getState('filter.degree');
        $id .= ':' . $this->getState('filter.format');
        $id .= ':' . $this->getState('filter.approach');
        $id .= ':' . $this->getState('filter.published');
        return parent::getStoreId($id);
    }
}
