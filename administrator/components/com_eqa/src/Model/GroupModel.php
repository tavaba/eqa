<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Kma\Library\Kma\Model\AdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

defined('_JEXEC') or die();

class GroupModel extends AdminModel {
    public function prepareTable($table): void
    {
        $table->size=null;  //Không cho phép cập nhật trực tiếp sĩ số
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
