<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Kma\Component\Eqa\Administrator\Enum\Action;
use Kma\Component\Eqa\Administrator\Enum\ObjectType;
use Kma\Library\Kma\DataObject\LogEntry;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Service\LogService;

defined('_JEXEC') or die();

/**
 * Lưu ý: model này KHÔNG kế thừa {@see \Kma\Component\Eqa\Administrator\Base\AdminModel}
 * hay {@see \Kma\Component\Eqa\Administrator\Base\ListModel} (kế thừa thẳng
 * BaseDatabaseModel của Joomla core), nên KHÔNG có sẵn $logService/writeLog()
 * như các model khác trong component. Phần dưới đây tự bổ sung hạ tầng đó
 * (tối thiểu, chỉ cho model này) để có thể ghi log.
 *
 * @since 2.1.6
 */
class StimulationModel extends BaseDatabaseModel {
	/** @since 2.1.6 */
	protected ?LogService $logService = null;

	public function __construct($config = [], $factory = null)
	{
		parent::__construct($config, $factory);
		$this->logService = ComponentHelper::getLogService();
	}

	/**
	 * @since 2.1.6
	 */
	protected function writeLog(LogEntry $entry): void
	{
		if ($this->logService) {
			$this->logService->write($entry);
		}
	}

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
			$this->writeLog(new LogEntry(
				action: Action::CLEAR_STIMULATIONS,
				objectType: ObjectType::Subject->value,
				isSuccess: false,
				errorMessage: Text::_('COM_EQA_MSG_DATABASE_ERROR'),
				extraData: ['stimulation_ids' => $stimulIds],
			));
			return false;
		}
		$app->enqueueMessage(Text::sprintf('COM_EQA_MSG_N_ITEMS_DELETED',sizeof($stimulIds)),'success');
		$this->writeLog(new LogEntry(
			action: Action::CLEAR_STIMULATIONS,
			objectType: ObjectType::Subject->value,
			isSuccess: true,
			extraData: ['stimulation_ids' => $stimulIds],
		));
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
		$createdBy = (int)$app->getIdentity()->id;
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
			$this->writeLog(new LogEntry(
				action: Action::STIMULATE,
				objectType: ObjectType::Subject->value,
				isSuccess: false,
				objectId: $subjectId,
				errorMessage: Text::_('COM_EQA_MSG_DATABASE_ERROR'),
				extraData: ['learner_ids' => $learnerIds, 'type' => $stimulType, 'value' => $stimulValue, 'reason' => $stimulReason],
			));
			return false;
		}
		$app->enqueueMessage(Text::sprintf('COM_EQA_MSG_N_ITEMS_INSERTED',sizeof($learnerIds)),'success');
		$this->writeLog(new LogEntry(
			action: Action::STIMULATE,
			objectType: ObjectType::Subject->value,
			isSuccess: true,
			objectId: $subjectId,
			extraData: ['learner_ids' => $learnerIds, 'type' => $stimulType, 'value' => $stimulValue, 'reason' => $stimulReason],
		));
		return true;
	}
}
