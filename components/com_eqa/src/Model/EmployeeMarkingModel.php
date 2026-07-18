<?php
namespace Kma\Component\Eqa\Site\Model;

defined('_JEXEC') or die();

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Kma\Component\Eqa\Administrator\Enum\Anomaly;
use Kma\Component\Eqa\Administrator\Enum\ExamStatus;
use Kma\Component\Eqa\Administrator\Enum\PpaaStatus;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

/**
 * Model cung cấp dữ liệu chi tiết kết quả chấm thi của một môn thi
 * cho cán bộ chấm thi xem lại:
 *  - Danh sách ĐẦY ĐỦ thí sinh của môn thi (SBD, số phách nếu có — đã ráp phách,
 *    điểm gốc, bất thường) kèm thông tin CBChT 1/2 của từng thí sinh;
 *  - Danh sách các bài chấm phúc khảo mà cán bộ này thực hiện.
 *
 * Điều kiện xem: môn thi ở trạng thái 'Đã có đủ điểm thi' (MarkFull) trở lên
 * VÀ cán bộ có tham gia chấm môn thi này (ở ít nhất 1 trong 4 nguồn).
 * Admin/superuser không có đặc quyền đối với chức năng này.
 *
 * @since 2.1.5
 */
class EmployeeMarkingModel extends BaseDatabaseModel
{
	/**
	 * Kiểm tra điều kiện xem chi tiết kết quả chấm của một môn thi.
	 *
	 * @param   int          $examId
	 * @param   int          $employeeId
	 * @param   string|null  $error  Thông báo lỗi (nếu không được xem)
	 *
	 * @return bool
	 * @since 2.1.5
	 */
	public function canViewDetail(int $examId, int $employeeId, ?string &$error = null): bool
	{
		// 1. Cán bộ phải là chính người đang đăng nhập
		$signedInEmployeeId = GeneralHelper::getSignedInEmployeeId();
		if (empty($signedInEmployeeId) || $signedInEmployeeId !== $employeeId)
		{
			$error = 'Bạn không có quyền xem thông tin này';
			return false;
		}

		// 2. Môn thi phải ở trạng thái 'Đã có đủ điểm thi' trở lên
		$db = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select($db->quoteName('status'))
			->from($db->quoteName('#__eqa_exams'))
			->where($db->quoteName('id') . ' = ' . $examId);
		$db->setQuery($query);
		$status = $db->loadResult();
		if ($status === null)
		{
			$error = 'Không tìm thấy môn thi';
			return false;
		}
		if ((int) $status < ExamStatus::MarkFull->value)
		{
			$error = 'Môn thi chưa có đủ điểm thi nên chưa thể xem chi tiết kết quả chấm';
			return false;
		}

		// 3. Cán bộ phải có tham gia chấm môn thi này
		if (!$this->hasParticipated($examId, $employeeId))
		{
			$error = 'Bạn không tham gia chấm môn thi này';
			return false;
		}

		return true;
	}

	/**
	 * Kiểm tra cán bộ có tham gia chấm một môn thi hay không
	 * (ở ít nhất một trong 4 nguồn: túi bài thi, phòng thi, chấm máy, phúc khảo).
	 *
	 * @param   int  $examId
	 * @param   int  $employeeId
	 *
	 * @return bool
	 * @since 2.1.5
	 */
	public function hasParticipated(int $examId, int $employeeId): bool
	{
		$db = DatabaseHelper::getDatabaseDriver();

		// Nguồn 1: Túi bài thi
		$query = $db->getQuery(true)
			->select('1')
			->from($db->quoteName('#__eqa_packages'))
			->where($db->quoteName('exam_id') . ' = ' . $examId)
			->where('(' . $db->quoteName('examiner1_id') . ' = ' . $employeeId
				. ' OR ' . $db->quoteName('examiner2_id') . ' = ' . $employeeId . ')')
			->setLimit(1);
		$db->setQuery($query);
		if ($db->loadResult())
			return true;

		// Nguồn 2: Phòng thi
		$query = $db->getQuery(true)
			->select('1')
			->from($db->quoteName('#__eqa_examrooms', 'er'))
			->innerJoin(
				$db->quoteName('#__eqa_exam_learner', 'el'),
				$db->quoteName('el.examroom_id') . ' = ' . $db->quoteName('er.id')
			)
			->where($db->quoteName('el.exam_id') . ' = ' . $examId)
			->where('(' . $db->quoteName('er.examiner1_id') . ' = ' . $employeeId
				. ' OR ' . $db->quoteName('er.examiner2_id') . ' = ' . $employeeId . ')')
			->setLimit(1);
		$db->setQuery($query);
		if ($db->loadResult())
			return true;

		// Nguồn 3: Chấm máy
		$query = $db->getQuery(true)
			->select('1')
			->from($db->quoteName('#__eqa_mmproductions'))
			->where($db->quoteName('exam_id') . ' = ' . $examId)
			->where($db->quoteName('examiner_id') . ' = ' . $employeeId)
			->setLimit(1);
		$db->setQuery($query);
		if ($db->loadResult())
			return true;

		// Nguồn 4: Phúc khảo
		$query = $db->getQuery(true)
			->select('1')
			->from($db->quoteName('#__eqa_regradings'))
			->where($db->quoteName('exam_id') . ' = ' . $examId)
			->where('(' . $db->quoteName('examiner1_id') . ' = ' . $employeeId
				. ' OR ' . $db->quoteName('examiner2_id') . ' = ' . $employeeId . ')')
			->setLimit(1);
		$db->setQuery($query);
		return (bool) $db->loadResult();
	}

	/**
	 * Danh sách đầy đủ thí sinh của môn thi kèm kết quả chấm.
	 * Với bài thi viết, thực hiện ráp phách (papers.mask → learner) để hiển thị
	 * đủ thông tin thí sinh; CBChT được xác định qua túi bài thi hoặc phòng thi.
	 *
	 * @param   int  $examId
	 * @param   int  $employeeId  Dùng để đánh dấu các dòng do chính cán bộ này chấm
	 *
	 * @return array Mảng object: [examineeCode, learnerCode, lastname, firstname,
	 *               attempt, mask, packageNumber, markOrig, anomalyLabel,
	 *               examiner1Name, examiner2Name, isMine]
	 * @since 2.1.5
	 */
	public function getExaminees(int $examId, int $employeeId): array
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('el.code',         'examineeCode'),
			$db->quoteName('el.attempt',      'attempt'),
			$db->quoteName('el.anomaly',      'anomaly'),
			$db->quoteName('el.mark_orig',    'markOrig'),
			$db->quoteName('l.code',          'learnerCode'),
			$db->quoteName('l.lastname',      'lastname'),
			$db->quoteName('l.firstname',     'firstname'),
			$db->quoteName('p.mask',          'mask'),
			$db->quoteName('pk.number',       'packageNumber'),
			$db->quoteName('pk.examiner1_id', 'packageExaminer1Id'),
			$db->quoteName('pk.examiner2_id', 'packageExaminer2Id'),
			$db->quoteName('er.examiner1_id', 'roomExaminer1Id'),
			$db->quoteName('er.examiner2_id', 'roomExaminer2Id'),
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from($db->quoteName('#__eqa_exam_learner', 'el'))
			->innerJoin(
				$db->quoteName('#__eqa_learners', 'l'),
				$db->quoteName('l.id') . ' = ' . $db->quoteName('el.learner_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_papers', 'p'),
				$db->quoteName('p.exam_id') . ' = ' . $db->quoteName('el.exam_id')
				. ' AND ' . $db->quoteName('p.learner_id') . ' = ' . $db->quoteName('el.learner_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_packages', 'pk'),
				$db->quoteName('pk.id') . ' = ' . $db->quoteName('p.package_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_examrooms', 'er'),
				$db->quoteName('er.id') . ' = ' . $db->quoteName('el.examroom_id')
			)
			->where($db->quoteName('el.exam_id') . ' = ' . $examId)
			->order($db->quoteName('el.code') . ' ASC');
		$db->setQuery($query);
		$items = $db->loadObjectList();
		if (empty($items))
			return [];

		// Thu thập id của tất cả CBChT xuất hiện trong danh sách để lấy tên
		$examinerIds = [];
		foreach ($items as $item)
		{
			foreach (['packageExaminer1Id', 'packageExaminer2Id', 'roomExaminer1Id', 'roomExaminer2Id'] as $field)
			{
				if (!empty($item->$field))
					$examinerIds[(int) $item->$field] = true;
			}
		}
		$examinerInfos = !empty($examinerIds)
			? DatabaseHelper::getEmployeeInfos(array_keys($examinerIds))
			: [];

		$getExaminerName = static function (?int $examinerId) use ($examinerInfos): ?string
		{
			if (empty($examinerId) || !isset($examinerInfos[$examinerId]))
				return null;
			$info = $examinerInfos[$examinerId];
			return trim($info['lastname'] . ' ' . $info['firstname']);
		};

		// Chuẩn hóa từng dòng: xác định CBChT (ưu tiên túi bài thi, sau đó phòng thi),
		// nhãn bất thường và cờ đánh dấu bài do chính cán bộ này chấm
		foreach ($items as $item)
		{
			$examiner1Id = !empty($item->packageExaminer1Id)
				? (int) $item->packageExaminer1Id
				: (!empty($item->roomExaminer1Id) ? (int) $item->roomExaminer1Id : null);
			$examiner2Id = !empty($item->packageExaminer2Id)
				? (int) $item->packageExaminer2Id
				: (!empty($item->roomExaminer2Id) ? (int) $item->roomExaminer2Id : null);

			$item->examiner1Name = $getExaminerName($examiner1Id);
			$item->examiner2Name = $getExaminerName($examiner2Id);
			$item->isMine = ($examiner1Id === $employeeId) || ($examiner2Id === $employeeId);

			$anomaly = Anomaly::tryFrom((int) $item->anomaly);
			$item->anomalyLabel = (!empty($item->anomaly) && $anomaly !== null)
				? $anomaly->getLabel()
				: null;
		}

		return $items;
	}

	/**
	 * Danh sách các bài chấm phúc khảo của cán bộ đối với một môn thi.
	 *
	 * @param   int  $examId
	 * @param   int  $employeeId
	 *
	 * @return array Mảng object: [learnerCode, lastname, firstname, markOrig,
	 *               result, role, statusLabel]
	 * @since 2.1.5
	 */
	public function getRegradings(int $examId, int $employeeId): array
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('rg.id',           'id'),
			$db->quoteName('l.code',          'learnerCode'),
			$db->quoteName('l.lastname',      'lastname'),
			$db->quoteName('l.firstname',     'firstname'),
			$db->quoteName('el.mark_orig',    'markOrig'),
			$db->quoteName('rg.result',       'result'),
			$db->quoteName('rg.status',       'status'),
			$db->quoteName('rg.examiner1_id', 'examiner1Id'),
			$db->quoteName('rg.examiner2_id', 'examiner2Id'),
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from($db->quoteName('#__eqa_regradings', 'rg'))
			->innerJoin(
				$db->quoteName('#__eqa_learners', 'l'),
				$db->quoteName('l.id') . ' = ' . $db->quoteName('rg.learner_id')
			)
			->leftJoin(
				$db->quoteName('#__eqa_exam_learner', 'el'),
				$db->quoteName('el.exam_id') . ' = ' . $db->quoteName('rg.exam_id')
				. ' AND ' . $db->quoteName('el.learner_id') . ' = ' . $db->quoteName('rg.learner_id')
			)
			->where($db->quoteName('rg.exam_id') . ' = ' . $examId)
			->where('(' . $db->quoteName('rg.examiner1_id') . ' = ' . $employeeId
				. ' OR ' . $db->quoteName('rg.examiner2_id') . ' = ' . $employeeId . ')')
			->order($db->quoteName('l.firstname') . ' ASC');
		$db->setQuery($query);
		$items = $db->loadObjectList();
		if (empty($items))
			return [];

		foreach ($items as $item)
		{
			$item->role = ((int) $item->examiner1Id === $employeeId) ? 'Chấm PK 1' : 'Chấm PK 2';
			$status = PpaaStatus::tryFrom((int) $item->status);
			$item->statusLabel = $status?->getLabel() ?? '';
		}
		return $items;
	}
}
