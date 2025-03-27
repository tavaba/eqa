<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();
use Kma\Component\Eqa\Administrator\Base\EqaListModel;

class UnitsModel extends EqaListModel{
    public function getListQuery()
    {
        $query =  parent::getListQuery();
        $query->from('#__eqa_units')
            ->select('*')
            ->order('parent_id  ASC, name ASC');
        return $query;
    }
}