<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use JFactory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;
use function Symfony\Component\String\b;

defined('_JEXEC') or die();

class RegradingModel extends EqaAdminModel {
	/**
	 * Ghi nhận yêu cầu đính chính được chấp nhận
	 * @param   int     $itemId
	 * @param   string  $currentUsername
	 * @param   string  $currentTime
	 *
	 *
	 * @throws Exception
	 * @since 1.1.10
	 */
	public function accept(int $itemId, string $currentUsername, string $currentTime)
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Get information about the grade correction request
		$columns = [
			$db->quoteName('b.code')                 . ' AS ' . $db->quoteName('learnerCode'),
			'CONCAT_WS(" ", b.lastname, b.firstname)'      . ' AS ' . $db->quoteName('fullname'),
			$db->quoteName('d.completed')            . ' AS ' . $db->quoteName('examseasonCompleted')
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->leftJoin('#__eqa_exams AS c', 'c.id=a.exam_id')
			->leftJoin('#__eqa_examseasons AS d', 'd.id=c.examseason_id')
			->where('a.id = ' . $itemId);
		$db->setQuery($query);
		$item = $db->loadObject();

		//2. Check if can accept
		if(!isset($item))
			throw new Exception("Không tìm thấy yêu cầu phúc khảo");
		if($item->examseasonCompleted)
			throw new Exception("Kỳ thi đã kết thúc nên không thể chấp nhận yêu cầu phúc khảo");

		//3. Update database
		$query = $db->getQuery(true)
			->update('#__eqa_regradings')
			->set([
				'status = ' . ExamHelper::EXAM_PPAA_STATUS_ACCEPTED,
				'handled_by = ' . $db->quote($currentUsername),
				'handled_at = ' . $db->quote($currentTime)
			])
			->where('id = ' . $itemId);
		$db->setQuery($query);
		if(!$db->execute())
			throw new Exception("Xảy ra lỗi khi chấp nhận yêu cầu phúc khảo");

		//4. Return success message
		$app = JFactory::getApplication();
		$msg = Text::sprintf('Yêu cầu phúc khảo của <b>%s (%s)</b> đã được chấp nhận',
			$item->fullname, $item->learnerCode
		);
		$app->enqueueMessage($msg, "success");
	}
	public function reject(int $itemId, string $currentUsername, string $currentTime)
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Get information about the grade correction request
		$columns = [
			$db->quoteName('b.code')                 . ' AS ' . $db->quoteName('learnerCode'),
			'CONCAT_WS(" ", b.lastname, b.firstname)'      . ' AS ' . $db->quoteName('fullname'),
			$db->quoteName('d.completed')            . ' AS ' . $db->quoteName('examseasonCompleted')
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_regradings AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->leftJoin('#__eqa_exams AS c', 'c.id=a.exam_id')
			->leftJoin('#__eqa_examseasons AS d', 'd.id=c.examseason_id')
			->where('a.id = ' . $itemId);
		$db->setQuery($query);
		$item = $db->loadObject();

		//2. Check if can reject
		if(!isset($item))
			throw new Exception("Không tìm thấy yêu cầu phúc khảo");
		if($item->examseasonCompleted)
			throw new Exception("Kỳ thi đã kết thúc nên không thể từ chối yêu cầu phúc khảo");

		//3. Update database
		$query = $db->getQuery(true)
			->update('#__eqa_regradings')
			->set([
				'status = ' . ExamHelper::EXAM_PPAA_STATUS_REJECTED,
				'handled_by = ' . $db->quote($currentUsername),
				'handled_at = ' . $db->quote($currentTime)
			])
			->where('id = ' . $itemId);
		$db->setQuery($query);
		if(!$db->execute())
			throw new Exception("Xảy ra lỗi khi từ chối yêu cầu phúc khảo");

		//4. Return success message
		$app = JFactory::getApplication();
		$msg = Text::sprintf('Yêu cầu phúc khảo của <b>%s (%s)</b> đã bị từ chối',
			$item->fullname, $item->learnerCode
		);
		$app->enqueueMessage($msg, "success");
	}
}
