<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Kma\Component\Eqa\Administrator\Base\AdminModel;
use Kma\Component\Eqa\Administrator\Traits\CampusFormField;
defined('_JEXEC') or die();

class UnitModel extends AdminModel
{
    use CampusFormField;

    /**
     * @param   array  $data
     * @param   bool   $loadData
     *
     * @return  mixed
     * @since   2.1.6
     */
    public function getForm($data = [], $loadData = true)
    {
        return $this->applyCampusFieldVisibility(parent::getForm($data, $loadData));
    }
}
