<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

defined('_JEXEC') or die();

class StimulationModel extends BaseDatabaseModel {
	public function clear(array $stimulIds):bool
	{
		//Init
		$db = $this->getDatabase();
		$app = Factory::getApplication();

		//Process
		$stimulIdSet = '(' . implode(',', $stimulIds) . ')';
		$query = $db->getQuery(true)
			->delete('#__eqa_stimulations')
			->where('id IN ' . $stimulIdSet);
		$db->setQuery($query);
		if(!$db->execute()){
			$app->enqueueMessage(Text::_('COM_EQA_MSG_DATABASE_ERROR'),'error');
			return false;
		}
		$app->enqueueMessage(Text::sprintf('COM_EQA_MSG_N_ITEMS_DELETED',sizeof($stimulIds)),'success');
		return true;
	}
	public function stimulate(int $subjectId, array $learnerIds, int $stimulType, float|null $stimulValue, string|null $stimulReason, string $timeStamp, string $username):bool
	{
		//1. Init
		$db = $this->getDatabase();
		$app = Factory::getApplication();

		//2. Set stimulations
		$columns = $db->quoteName(array('subject_id', 'learner_id', 'type', 'value', 'reason', 'created_at', 'created_by'));
		$stimulReason = $db->quote($stimulReason);
		$createdAt = $db->quote($timeStamp);
		$createdBy = $db->quote($username);
		$tupes = [];
		foreach ($learnerIds as $learnerId)
		{
			$values = [$subjectId, $learnerId, $stimulType, $stimulValue, $stimulReason, $createdAt, $createdBy];
			$tupes[] = implode(',', $values);
		}
		$query = $db->getQuery(true)
			->insert('#__eqa_stimulations')
			->columns($columns)
			->values($tupes);
		$db->setQuery($query);
		if(!$db->execute())
		{
			$app->enqueueMessage(Text::_('COM_EQA_MSG_DATABASE_ERROR'),'error');
			return false;
		}
		$app->enqueueMessage(Text::sprintf('COM_EQA_MSG_N_ITEMS_INSERTED',sizeof($learnerIds)),'success');
		return true;
	}
}
