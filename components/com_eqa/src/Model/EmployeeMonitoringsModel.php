<?php
namespace Kma\Component\Eqa\Site\Model;

defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseQuery;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

/**
 * Model phục vụ cán bộ xem lịch sử thực hiện nhiệm vụ trong các kỳ thi:
 *  - Coi thi (CBCT 1/2/3 tại phòng thi — #__eqa_examrooms.monitor1/2/3_id)
 *  - Coi thi kiêm chấm thi (CBCTChT 1/2 tại phòng thi — #__eqa_examrooms.examiner1/2_id)
 *  - Giám sát (ngoài các phòng thi — #__eqa_examsessions.supervisor_ids)
 *
 * Cấu trúc master-detail:
 *  - Danh sách CHÍNH (getListQuery, có phân trang): thống kê số lượt theo TỪNG KỲ THI,
 *    chỉ gồm các kỳ thi mà cán bộ có ít nhất một hoạt động;
 *  - Dữ liệu CHI TIẾT (getDetails): các lượt cụ thể của MỘT kỳ thi được chọn
 *    (qua filter.examseason_id); không phân trang vì quy mô nhỏ.
 *
 * Lưu ý phân quyền: model này KHÔNG cấp đặc quyền cho admin/superuser.
 * Cán bộ chỉ được xem dữ liệu của chính mình; View chịu trách nhiệm set
 * state 'filter.employee_id' từ phiên đăng nhập trước khi gọi model.
 *
 * @since 2.1.5
 */
class EmployeeMonitoringsModel extends ListModel
{
	public function __construct($config = [], ?MVCFactoryInterface $factory = null)
	{
		$config['filter_fields'] = ['examseason_id'];
		parent::__construct($config, $factory);
	}

	protected function populateState($ordering = 'examseasonId', $direction = 'DESC'): void
	{
		parent::populateState($ordering, $direction);
	}

	/**
	 * Chỉ cho phép cán bộ xem dữ liệu của chính mình.
	 * Admin/superuser không có đặc quyền đối với chức năng này.
	 *
	 * @return bool
	 * @since 2.1.5
	 */
	public function canViewList(): bool
	{
		$employeeId = (int) $this->getState('filter.employee_id');
		if (empty($employeeId))
			return false;

		$signedInEmployeeId = GeneralHelper::getSignedInEmployeeId();
		return !empty($signedInEmployeeId) && $signedInEmployeeId === $employeeId;
	}

	/**
	 * Danh sách CHÍNH: thống kê số lượt theo từng kỳ thi (kỳ mới nhất trước).
	 * Mỗi dòng: kỳ thi, số lượt coi thi, số lượt coi thi kiêm chấm thi,
	 * số lượt giám sát. Chỉ liệt kê các kỳ thi cán bộ có hoạt động.
	 *
	 * @return DatabaseQuery|null
	 * @since 2.1.5
	 */
	public function getListQuery()
	{
		$employeeId = (int) $this->getState('filter.employee_id');
		if (empty($employeeId))
			return null;

		$db = DatabaseHelper::getDatabaseDriver();

		// ---------------------------------------------------------------
		// Derived table d1: số lượt tại phòng thi, gộp theo kỳ thi.
		// Dùng SUM(CASE...) thay vì SUM(col = x) để tránh NULL nuốt tổng.
		// ---------------------------------------------------------------
		$monitorExpr =
			'SUM(CASE WHEN ' . $db->quoteName('a.monitor1_id') . ' = ' . $employeeId . ' THEN 1 ELSE 0 END)'
			. ' + SUM(CASE WHEN ' . $db->quoteName('a.monitor2_id') . ' = ' . $employeeId . ' THEN 1 ELSE 0 END)'
			. ' + SUM(CASE WHEN ' . $db->quoteName('a.monitor3_id') . ' = ' . $employeeId . ' THEN 1 ELSE 0 END)';
		$examinerExpr =
			'SUM(CASE WHEN ' . $db->quoteName('a.examiner1_id') . ' = ' . $employeeId . ' THEN 1 ELSE 0 END)'
			. ' + SUM(CASE WHEN ' . $db->quoteName('a.examiner2_id') . ' = ' . $employeeId . ' THEN 1 ELSE 0 END)';

		$roomStatQuery = $db->getQuery(true)
			->select([
				$db->quoteName('b.examseason_id', 'examseasonId'),
				$monitorExpr . ' AS ' . $db->quoteName('monitorCount'),
				$examinerExpr . ' AS ' . $db->quoteName('monitorExaminerCount'),
			])
			->from($db->quoteName('#__eqa_examrooms', 'a'))
			->innerJoin(
				$db->quoteName('#__eqa_examsessions', 'b'),
				$db->quoteName('b.id') . ' = ' . $db->quoteName('a.examsession_id')
			)
			->where($db->quoteName('b.examseason_id') . ' IS NOT NULL')
			->where('('
				. $db->quoteName('a.monitor1_id')  . ' = ' . $employeeId . ' OR '
				. $db->quoteName('a.monitor2_id')  . ' = ' . $employeeId . ' OR '
				. $db->quoteName('a.monitor3_id')  . ' = ' . $employeeId . ' OR '
				. $db->quoteName('a.examiner1_id') . ' = ' . $employeeId . ' OR '
				. $db->quoteName('a.examiner2_id') . ' = ' . $employeeId
				. ')')
			->group($db->quoteName('b.examseason_id'));

		// ---------------------------------------------------------------
		// Derived table d2: số lượt giám sát, gộp theo kỳ thi.
		// FIND_IN_SET an toàn vì $employeeId đã được ép kiểu int.
		// ---------------------------------------------------------------
		$supervisorStatQuery = $db->getQuery(true)
			->select([
				$db->quoteName('examseason_id', 'examseasonId'),
				'COUNT(*) AS ' . $db->quoteName('supervisorCount'),
			])
			->from($db->quoteName('#__eqa_examsessions'))
			->where($db->quoteName('examseason_id') . ' IS NOT NULL')
			->where('FIND_IN_SET(' . $employeeId . ', ' . $db->quoteName('supervisor_ids') . ')')
			->group($db->quoteName('examseason_id'));

		// ---------------------------------------------------------------
		// Query chính: kỳ thi + các số liệu (kỳ không có hoạt động bị loại)
		// ---------------------------------------------------------------
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('x.id', 'examseasonId'),
				$db->quoteName('x.name', 'examseasonName'),
				'COALESCE(' . $db->quoteName('d1.monitorCount') . ', 0) AS '
					. $db->quoteName('monitorCount'),
				'COALESCE(' . $db->quoteName('d1.monitorExaminerCount') . ', 0) AS '
					. $db->quoteName('monitorExaminerCount'),
				'COALESCE(' . $db->quoteName('d2.supervisorCount') . ', 0) AS '
					. $db->quoteName('supervisorCount'),
			])
			->from($db->quoteName('#__eqa_examseasons', 'x'))
			->leftJoin(
				'(' . $roomStatQuery . ') AS ' . $db->quoteName('d1'),
				$db->quoteName('d1.examseasonId') . ' = ' . $db->quoteName('x.id')
			)
			->leftJoin(
				'(' . $supervisorStatQuery . ') AS ' . $db->quoteName('d2'),
				$db->quoteName('d2.examseasonId') . ' = ' . $db->quoteName('x.id')
			)
			->where('(' . $db->quoteName('d1.examseasonId') . ' IS NOT NULL OR '
				. $db->quoteName('d2.examseasonId') . ' IS NOT NULL)')
			->order($db->quoteName('x.id') . ' DESC');

		return $query;
	}

	/**
	 * Dữ liệu CHI TIẾT: các lượt coi thi / coi thi kiêm chấm thi / giám sát
	 * của cán bộ trong MỘT kỳ thi. Quy mô nhỏ nên không phân trang.
	 *
	 * @param   int  $employeeId
	 * @param   int  $examseasonId
	 *
	 * @return array Mảng object: [start, sessionName, roomCode, examroomName, role]
	 * @since 2.1.5
	 */
	public function getDetails(int $employeeId, int $examseasonId): array
	{
		$db = DatabaseHelper::getDatabaseDriver();

		/**
		 * Tạo subquery cho một vai trò tại phòng thi.
		 *
		 * @param string $roleColumn Tên cột vai trò trong #__eqa_examrooms
		 * @param string $roleLabel  Nhãn hiển thị vai trò
		 * @param int    $roleOrder  Thứ tự sắp xếp phụ
		 */
		$buildRoomSubquery = function (string $roleColumn, string $roleLabel, int $roleOrder)
			use ($db, $employeeId, $examseasonId): DatabaseQuery
		{
			return $db->getQuery(true)
				->select([
					$db->quoteName('a.id', 'examroomId'),
					$db->quoteName('b.start', 'start'),
					$db->quoteName('b.name', 'sessionName'),
					$db->quoteName('c.code', 'roomCode'),
					$db->quoteName('a.name', 'examroomName'),
					$db->quote($roleLabel) . ' AS ' . $db->quoteName('role'),
					$roleOrder . ' AS ' . $db->quoteName('roleOrder'),
				])
				->from($db->quoteName('#__eqa_examrooms', 'a'))
				->innerJoin(
					$db->quoteName('#__eqa_examsessions', 'b'),
					$db->quoteName('b.id') . ' = ' . $db->quoteName('a.examsession_id')
				)
				->leftJoin(
					$db->quoteName('#__eqa_rooms', 'c'),
					$db->quoteName('c.id') . ' = ' . $db->quoteName('a.room_id')
				)
				->where($db->quoteName('a.' . $roleColumn) . ' = ' . $employeeId)
				->where($db->quoteName('b.examseason_id') . ' = ' . $examseasonId);
		};

		// Query chính: CBCT 1; nối thêm các vai trò còn lại
		$query = $buildRoomSubquery('monitor1_id', 'CBCT 1', 1);
		$query->unionAll($buildRoomSubquery('monitor2_id', 'CBCT 2', 2));
		$query->unionAll($buildRoomSubquery('monitor3_id', 'CBCT 3', 3));
		$query->unionAll($buildRoomSubquery('examiner1_id', 'CBCTChT 1', 4));
		$query->unionAll($buildRoomSubquery('examiner2_id', 'CBCTChT 2', 5));

		// Giám sát ca thi (không gắn với phòng cụ thể)
		$supervisorQuery = $db->getQuery(true)
			->select([
				'NULL AS ' . $db->quoteName('examroomId'),
				$db->quoteName('b.start', 'start'),
				$db->quoteName('b.name', 'sessionName'),
				'NULL AS ' . $db->quoteName('roomCode'),
				'NULL AS ' . $db->quoteName('examroomName'),
				$db->quote('Giám sát') . ' AS ' . $db->quoteName('role'),
				'9 AS ' . $db->quoteName('roleOrder'),
			])
			->from($db->quoteName('#__eqa_examsessions', 'b'))
			->where($db->quoteName('b.examseason_id') . ' = ' . $examseasonId)
			->where('FIND_IN_SET(' . $employeeId . ', ' . $db->quoteName('b.supervisor_ids') . ')');
		$query->unionAll($supervisorQuery);

		// Sắp xếp trên kết quả UNION
		$query->order($db->quoteName('start') . ' ASC, ' . $db->quoteName('roleOrder') . ' ASC');

		$db->setQuery($query);
		$items = $db->loadObjectList();
		if (empty($items))
			return [];

		// Nạp danh sách môn thi của từng phòng thi (một query cho tất cả các phòng)
		$examroomIds = [];
		foreach ($items as $item)
		{
			if (!empty($item->examroomId))
				$examroomIds[(int) $item->examroomId] = true;
		}
		$examNamesByRoom = [];
		if (!empty($examroomIds))
		{
			$examroomIdSet = '(' . implode(',', array_keys($examroomIds)) . ')';
			$query = $db->getQuery(true)
				->select('DISTINCT ' . $db->quoteName('el.examroom_id', 'examroomId')
					. ', ' . $db->quoteName('ex.name', 'examName'))
				->from($db->quoteName('#__eqa_exam_learner', 'el'))
				->innerJoin(
					$db->quoteName('#__eqa_exams', 'ex'),
					$db->quoteName('ex.id') . ' = ' . $db->quoteName('el.exam_id')
				)
				->where($db->quoteName('el.examroom_id') . ' IN ' . $examroomIdSet)
				->order($db->quoteName('ex.name') . ' ASC');
			$db->setQuery($query);
			foreach ($db->loadObjectList() as $row)
				$examNamesByRoom[(int) $row->examroomId][] = $row->examName;
		}
		foreach ($items as $item)
		{
			$item->examNames = !empty($item->examroomId)
				? ($examNamesByRoom[(int) $item->examroomId] ?? [])
				: [];
		}

		return $items;
	}

	/**
	 * Kỳ thi đang được chọn để xem chi tiết:
	 *  - filter là id cụ thể → dùng id đó;
	 *  - filter = 0 → kỳ thi mặc định của hệ thống;
	 *  - chưa chọn → kỳ thi gần nhất mà cán bộ có hoạt động (null nếu không có).
	 *
	 * @return int|null
	 * @since 2.1.5
	 */
	public function getSelectedExamseasonId(): ?int
	{
		$filter = $this->getState('filter.examseason_id');
		if (is_numeric($filter))
		{
			if ((int) $filter === 0)
				return (int) DatabaseHelper::getDefaultExamseason()->id;
			return (int) $filter;
		}

		$employeeId = (int) $this->getState('filter.employee_id');
		if (empty($employeeId))
			return null;
		return $this->getLatestActiveExamseasonId($employeeId);
	}

	/**
	 * Kỳ thi gần nhất (id lớn nhất) mà cán bộ có ít nhất một hoạt động
	 * (coi thi / coi thi kiêm chấm thi / giám sát).
	 *
	 * @param   int  $employeeId
	 *
	 * @return int|null
	 * @since 2.1.5
	 */
	protected function getLatestActiveExamseasonId(int $employeeId): ?int
	{
		$db = DatabaseHelper::getDatabaseDriver();

		// Kỳ gần nhất có hoạt động tại phòng thi
		$query = $db->getQuery(true)
			->select('MAX(' . $db->quoteName('b.examseason_id') . ')')
			->from($db->quoteName('#__eqa_examrooms', 'a'))
			->innerJoin(
				$db->quoteName('#__eqa_examsessions', 'b'),
				$db->quoteName('b.id') . ' = ' . $db->quoteName('a.examsession_id')
			)
			->where('('
				. $db->quoteName('a.monitor1_id')  . ' = ' . $employeeId . ' OR '
				. $db->quoteName('a.monitor2_id')  . ' = ' . $employeeId . ' OR '
				. $db->quoteName('a.monitor3_id')  . ' = ' . $employeeId . ' OR '
				. $db->quoteName('a.examiner1_id') . ' = ' . $employeeId . ' OR '
				. $db->quoteName('a.examiner2_id') . ' = ' . $employeeId
				. ')');
		$db->setQuery($query);
		$latestRoomSeasonId = (int) $db->loadResult();

		// Kỳ gần nhất có hoạt động giám sát
		$query = $db->getQuery(true)
			->select('MAX(' . $db->quoteName('examseason_id') . ')')
			->from($db->quoteName('#__eqa_examsessions'))
			->where('FIND_IN_SET(' . $employeeId . ', ' . $db->quoteName('supervisor_ids') . ')');
		$db->setQuery($query);
		$latestSupervisorSeasonId = (int) $db->loadResult();

		$latest = max($latestRoomSeasonId, $latestSupervisorSeasonId);
		return $latest > 0 ? $latest : null;
	}

	public function getStoreId($id = '')
	{
		$id .= ':' . $this->getState('filter.employee_id');
		return parent::getStoreId($id);
	}
}
