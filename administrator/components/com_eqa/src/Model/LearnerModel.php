<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use RuntimeException;

defined('_JEXEC') or die();

class LearnerModel extends EqaAdminModel{

    /*
     * Rewrite 'delete' method to decrement the group size sequentially.
     * Transaction is used to ensure data integrity.
     */
    public function delete(&$pks): bool
    {
        // Get the database object and start a transaction
        $db = $this->getDatabase();
        $db->transactionStart();

        try {
            // Loop through each primary key (student ID)
            foreach ($pks as $pk) {
                $learner = $this->getItem($pk);
                $learner = GeneralHelper::castToCmsObject($learner);
                $groupId = $learner->group_id;

                // Decrement the size of the group
                $query = $db->getQuery(true)
                    ->update('#__eqa_groups')
                    ->set('`size` = `size`-1')
                    ->where('id = '.(int)$groupId);
                $db->setQuery($query);
                $db->execute();
            }

            // Proceed with the deletion
            $result = parent::delete($pks);

            // Commit the transaction if success and roll back if failed
            if($result) {
                $db->transactionCommit();
                return true;
            }
            else {
                $db->transactionRollback();
                return false;
            }
        } catch (Exception $e) {
            // Roll back the transaction if something else goes wrong
            $db->transactionRollback();
            throw $e;
        }
    }

    /*
     * Rewrite 'save' method to increment the group size sequentially.
     * Transaction is used to ensure data integrity.
     */
    public function save($data): bool
    {
        // Get the database object and start a transaction
        $db = $this->getDatabase();
        $db->transactionStart();

        try {
            // Perform the save operation
            $isNew = empty($data['id']);
            $item = $this->getItem($data['id']);
            $item = GeneralHelper::castToCmsObject($item);
            $previousGroupId = $isNew ? null : $item->group_id;
            $result = parent::save($data);

            if ($result) {
                $groupId = $data['group_id'];

                // Update group sizes
                if ($isNew) {
                    $query = $db->getQuery(true)
                        ->update('#__eqa_groups')
                        ->set('`size` = `size` + 1')
                        ->where('id = '.(int)$groupId);
                    $db->setQuery($query);
                    $db->execute();
                } else {
                    if ($previousGroupId != $groupId) {     //Moved student to another group
                        $query = $db->getQuery(true)
                            ->update('#__eqa_groups')
                            ->set('`size` = `size` - 1')
                            ->where('id = '.(int)$previousGroupId);
                        $db->setQuery($query);
                        $db->execute();

                        $query = $db->getQuery(true)
                            ->update('#__eqa_groups')
                            ->set('`size` = `size` + 1')
                            ->where('id = '.(int)$groupId);
                        $db->setQuery($query);
                        $db->execute();
                    }
                }
            }

            // Commit the transaction
            $db->transactionCommit();
            return $result;
        } catch (Exception $e) {
            // Roll back the transaction if something goes wrong
            $db->transactionRollback();
            throw $e;
        }
    }

	public function markDebt(array $learnerIds, int $debt):  bool
	{
		$learnerIdSet = '(' . implode(',',$learnerIds) . ')';
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->update('#__eqa_learners')
			->set('debtor = '.$debt)
			->where('id IN ' . $learnerIdSet);
		$db->setQuery($query);
		return $db->execute();
	}
}
