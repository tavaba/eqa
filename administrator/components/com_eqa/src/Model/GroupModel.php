<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Kma\Component\Eqa\Administrator\Base\AdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Traits\CampusFormField;

defined('_JEXEC') or die();

class GroupModel extends AdminModel {
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
    public function prepareTable($table): void
    {
	    if(isset($table->size))
			unset($table->size);    //Không cho cập nhật trực tiếp sĩ số
        if(empty($table->homeroom_id))
            $table->homeroom_id = null;
        if(empty($table->adviser_id))
            $table->adviser_id = null;
    }
	public function getLearners(int $groupId): array
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'code', 'lastname', 'firstname']))
			->from('#__eqa_learners')
			->where($db->quoteName('group_id') . ' = ' . $groupId)
			->order($db->quoteName('firstname') . ' ASC')
			->order($db->quoteName('lastname') . ' ASC');
		$db->setQuery($query);
		return $db->loadObjectList();
	}
}
