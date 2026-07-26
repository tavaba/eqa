<?php
namespace Kma\Component\Eqa\Site\Model;

defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\ListModel;
use Kma\Component\Eqa\Administrator\Enum\ExamStatus;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

/**
 * Model tổng hợp hoạt động chấm thi của một cán bộ trong một kỳ thi,
 * từ 4 nguồn (nhất quán với ExamseasonModel::getMarkingProductionDetails()):
 *   1. Chấm túi bài thi viết  (#__eqa_packages + #__eqa_papers)
 *   2. Chấm tại phòng thi     (#__eqa_examrooms + #__eqa_exam_learner)
 *   3. Chấm thi trên máy      (#__eqa_mmproductions)
 *   4. Chấm phúc khảo         (#__eqa_regradings)
 *
 * Mỗi dòng kết quả tương ứng một cặp (môn thi, hình thức chấm) kèm số bài
 * đã chấm với vai trò Chấm 1 và Chấm 2.
 *
 * Phân quyền: cán bộ chỉ xem được dữ liệu của chính mình; View set state
 * 'filter.employee_id' từ phiên đăng nhập. Admin/superuser không có đặc quyền.
 *
 * @since 2.1.5
 */
class EmployeeMarkingsModel extends ListModel
{
	public const string SOURCE_PACKAGE   = 'Chấm túi bài thi viết';
	public const string SOURCE_EXAMROOM  = 'Chấm tại phòng thi';
	public const string SOURCE_MACHINE   = 'Chấm thi trên máy';
	public const string SOURCE_REGRADING = 'Chấm phúc khảo';

	public function __construct($config = [], ?MVCFactoryInterface $factory = null)
	{
		$config['filter_fields'] = ['examseason_id'];
		parent::__construct($config, $factory);
	}

	/**
	 * Chỉ cho phép cán bộ xem dữ liệu của chính mình.
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
	 * Kỳ thi được lọc. Không chọn hoặc chọn giá trị 0 thì lấy kỳ thi mặc định.
	 *
	 * @return int
	 * @since 2.1.5
	 */
	public function getSelectedExamseasonId(): ?int
	{
		$filter = $this->getState('filter.examseason_id');
		if (is_numeric($filter) && (int) $filter > 0)
			return (int) $filter;
		return null;
	}

	/**
	 * Dữ liệu tổng hợp không đến từ một câu truy vấn đơn nên override getItems().
	 * Danh sách mỗi kỳ thi của một cán bộ là nhỏ, không cần phân trang.
	 *
	 * @return array
	 * @since 2.1.5
	 */
	public function getItems()
	{
		$employeeId = (int) $this->getState('filter.employee_id');
		if (empty($employeeId))
			return [];

		$examseasonId = $this->getSelectedExamseasonId();
		return $this->getMarkingSummary($employeeId, $examseasonId);
	}

	/**
	 * Tổng hợp hoạt động chấm thi của cán bộ trong một kỳ thi.
	 *
	 * @param   int  $employeeId
	 * @param   int  $examseasonId
	 *
	 * @return array Mảng object: [examId, examCode, examName, status, statusLabel,
	 *               source, count1, count2, canViewDetail]
	 * @since 2.1.5
	 */
	public function getMarkingSummary(int $employeeId, ?int $examseasonId): array
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$items = [];

		$examColumns = [
			$db->quoteName('ex.id',     'examId'),
			$db->quoteName('ex.code',   'examCode'),
			$db->quoteName('ex.name',   'examName'),
			$db->quoteName('ex.status', 'status'),
		];

		// ---------------------------------------------------------------
		// Nguồn 1: Chấm túi bài thi viết
		// ---------------------------------------------------------------
		$query = $db->getQuery(true)
			->select(array_merge($examColumns, [
				'SUM(CASE WHEN ' . $db->quoteName('pk.examiner1_id') . ' = ' . $employeeId
					. ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('count1'),
				'SUM(CASE WHEN ' . $db->quoteName('pk.examiner2_id') . ' = ' . $employeeId
					. ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('count2'),
			]))
			->from($db->quoteName('#__eqa_papers', 'p'))
			->innerJoin(
				$db->quoteName('#__eqa_packages', 'pk'),
				$db->quoteName('pk.id') . ' = ' . $db->quoteName('p.package_id')
			)
			->innerJoin(
				$db->quoteName('#__eqa_exams', 'ex'),
				$db->quoteName('ex.id') . ' = ' . $db->quoteName('p.exam_id')
			)
			->where('(' . $db->quoteName('pk.examiner1_id') . ' = ' . $employeeId
				. ' OR ' . $db->quoteName('pk.examiner2_id') . ' = ' . $employeeId . ')')
			->group($db->quoteName('ex.id'));
		if($examseasonId)
			$query->where($db->quoteName('ex.examseason_id') . ' = ' . $examseasonId);
		$db->setQuery($query);

		foreach ($db->loadObjectList() as $row)
			$items[] = $this->buildItem($row, self::SOURCE_PACKAGE);

		// ---------------------------------------------------------------
		// Nguồn 2: Chấm tại phòng thi (thực hành, vấn đáp...)
		// ---------------------------------------------------------------
		$query = $db->getQuery(true)
			->select(array_merge($examColumns, [
				'SUM(CASE WHEN ' . $db->quoteName('er.examiner1_id') . ' = ' . $employeeId
					. ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('count1'),
				'SUM(CASE WHEN ' . $db->quoteName('er.examiner2_id') . ' = ' . $employeeId
					. ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('count2'),
			]))
			->from($db->quoteName('#__eqa_exam_learner', 'el'))
			->innerJoin(
				$db->quoteName('#__eqa_examrooms', 'er'),
				$db->quoteName('er.id') . ' = ' . $db->quoteName('el.examroom_id')
			)
			->innerJoin(
				$db->quoteName('#__eqa_exams', 'ex'),
				$db->quoteName('ex.id') . ' = ' . $db->quoteName('el.exam_id')
			)
			->where('(' . $db->quoteName('er.examiner1_id') . ' = ' . $employeeId
				. ' OR ' . $db->quoteName('er.examiner2_id') . ' = ' . $employeeId . ')')
			->group($db->quoteName('ex.id'));
		if($examseasonId)
			$query->where($db->quoteName('ex.examseason_id') . ' = ' . $examseasonId);
		$db->setQuery($query);
		foreach ($db->loadObjectList() as $row)
			$items[] = $this->buildItem($row, self::SOURCE_EXAMROOM);

		// ---------------------------------------------------------------
		// Nguồn 3: Chấm thi trên máy
		// ---------------------------------------------------------------
		$query = $db->getQuery(true)
			->select(array_merge($examColumns, [
				'SUM(CASE WHEN ' . $db->quoteName('m.role') . ' = 1 THEN '
					. $db->quoteName('m.quantity') . ' ELSE 0 END) AS ' . $db->quoteName('count1'),
				'SUM(CASE WHEN ' . $db->quoteName('m.role') . ' = 2 THEN '
					. $db->quoteName('m.quantity') . ' ELSE 0 END) AS ' . $db->quoteName('count2'),
			]))
			->from($db->quoteName('#__eqa_mmproductions', 'm'))
			->innerJoin(
				$db->quoteName('#__eqa_exams', 'ex'),
				$db->quoteName('ex.id') . ' = ' . $db->quoteName('m.exam_id')
			)
			->where($db->quoteName('m.examiner_id') . ' = ' . $employeeId)
			->group($db->quoteName('ex.id'));
		if($examseasonId)
			$query->where($db->quoteName('ex.examseason_id') . ' = ' . $examseasonId);
		$db->setQuery($query);
		foreach ($db->loadObjectList() as $row)
			$items[] = $this->buildItem($row, self::SOURCE_MACHINE);

		// ---------------------------------------------------------------
		// Nguồn 4: Chấm phúc khảo (chỉ tính yêu cầu đã có kết quả)
		// ---------------------------------------------------------------
		$query = $db->getQuery(true)
			->select(array_merge($examColumns, [
				'SUM(CASE WHEN ' . $db->quoteName('rg.examiner1_id') . ' = ' . $employeeId
					. ' AND ' . $db->quoteName('rg.result') . ' IS NOT NULL'
					. ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('count1'),
				'SUM(CASE WHEN ' . $db->quoteName('rg.examiner2_id') . ' = ' . $employeeId
					. ' AND ' . $db->quoteName('rg.result') . ' IS NOT NULL'
					. ' THEN 1 ELSE 0 END) AS ' . $db->quoteName('count2'),
			]))
			->from($db->quoteName('#__eqa_regradings', 'rg'))
			->innerJoin(
				$db->quoteName('#__eqa_exams', 'ex'),
				$db->quoteName('ex.id') . ' = ' . $db->quoteName('rg.exam_id')
			)
			->where('(' . $db->quoteName('rg.examiner1_id') . ' = ' . $employeeId
				. ' OR ' . $db->quoteName('rg.examiner2_id') . ' = ' . $employeeId . ')')
			->group($db->quoteName('ex.id'));
		if($examseasonId)
			$query->where($db->quoteName('ex.examseason_id') . ' = ' . $examseasonId);
		$db->setQuery($query);
		foreach ($db->loadObjectList() as $row)
			$items[] = $this->buildItem($row, self::SOURCE_REGRADING);

		// Sắp xếp: theo mã môn thi, rồi theo hình thức chấm
		usort($items, static function (object $a, object $b): int {
			$cmp = strcmp($a->examCode, $b->examCode);
			if ($cmp !== 0)
				return $cmp;
			return strcmp($a->source, $b->source);
		});

		return $items;
	}

	/**
	 * Chuẩn hóa một dòng kết quả tổng hợp.
	 *
	 * @param   object  $row     Dòng dữ liệu từ CSDL
	 * @param   string  $source  Hình thức chấm
	 *
	 * @return object
	 * @since 2.1.5
	 */
	private function buildItem(object $row, string $source): object
	{
		$status = ExamStatus::tryFrom((int) $row->status);
		return (object) [
			'examId'        => (int) $row->examId,
			'examCode'      => $row->examCode,
			'examName'      => $row->examName,
			'status'        => (int) $row->status,
			'statusLabel'   => $status?->getLabel() ?? 'Không xác định',
			'source'        => $source,
			'count1'        => (float) $row->count1,
			'count2'        => (float) $row->count2,
			'canViewDetail' => (int) $row->status >= ExamStatus::MarkFull->value,
		];
	}
}
