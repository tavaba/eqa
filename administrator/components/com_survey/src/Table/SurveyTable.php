<?php
namespace Kma\Component\Survey\Administrator\Table;

use Joomla\CMS\Table\Table as BaseTable;
use Joomla\Database\DatabaseDriver;
use Kma\Component\Survey\Administrator\Base\Table;

defined('_JEXEC') or die();
class SurveyTable extends Table{
    public function __construct(DatabaseDriver $db){
        parent::__construct($db,'','');
        $this->setColumnAlias('published', 'state');
    }
    protected function _getAssetParentId(?BaseTable $table = null, $id = null): int
    {
        //If it's a single survey, just call parent method.
        if(empty($this->campaign_id))
            return parent::_getAssetParentId($table, $id);

        //Otherwise we need to find the campaign asset id.
        $parentAssetName = 'com_survey.campaign.'.$this->campaign_id;
        $db = $this->_db;
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__assets')
            ->where('name = ' . $db->quote($parentAssetName));
        $db->setQuery($query);
        return $db->loadResult();
    }
}