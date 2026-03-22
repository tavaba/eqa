<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();
use Kma\Component\Eqa\Administrator\Base\ListModel;

class UnitsModel extends ListModel{
    public function getListQuery()
    {
        $query =  parent::getListQuery();
        $query->from('#__eqa_units')
            ->select('*')
            ->order('parent_id  ASC, name ASC');
        return $query;
    }
}