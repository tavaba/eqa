<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\AdminModel;
use Kma\Component\Eqa\Administrator\Enum\ObjectType;
use Kma\Component\Eqa\Administrator\Enum\SpecialMark;
use Kma\Component\Eqa\Administrator\Extension\EqaComponent;
use Kma\Component\Eqa\Administrator\Helper\ConfigHelper;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Service\CreditClassNameParser;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;

defined('_JEXEC') or die();

class ClassModel extends AdminModel
{
	/** Trạng thái xử lý một worksheet trong importPamForSheet(). */
	public const int PAM_SHEET_PROCESSED = 0;   // Đã đọc và gọi importPams()
	public const int PAM_SHEET_SKIPPED   = 1;   // Bỏ qua (tên lớp không hợp lệ / lớp con / lớp không tồn tại / lớp trắng đã chọn bỏ qua)
	public const int PAM_SHEET_BLANK     = 2;   // Lớp trắng nhưng KHÔNG chọn "Bỏ qua lớp trắng" -> cần gộp báo cáo
	protected function prepareTable($table)
    {
	    if(empty($table->lecturer_id))
			$table->lecturer_id=null;
	    if(empty($table->start))
			$table->start=null;
		if(empty($table->finish))
			$table->finish=null;
		if(empty($table->topicdeadline))
			$table->topicdeadline=null;
		if(empty($table->topicdate))
			$table->topicdate=null;
		if(empty($table->thesisdate))
			$table->thesisdate=null;
		if(empty($table->pamdeadline))
			$table->pamdeadline=null;
		if(empty($table->pamdate))
			$table->pamdate=null;
        parent::prepareTable($table);
    }

	public function canDelete($record = null): bool
	{
		if(!parent::canDelete($record))
			return false;

		if($record==null)
			return true;

		//Chỉ có thể xóa nếu chưa có thí sinh nào có điểm quá trình
		$classId = is_object($record) ? $record->id : (int)$record;
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->from('#__eqa_class_learner')
			->select('*')
			->where([
				'class_id = '.$classId,
				'pam IS NOT NULL'
			])
			->setLimit(1);
		$db->setQuery($query);
		$item = $db->loadObject();
		if(!empty($item))
			return false;
		return true;
	}

	public function delete(&$pks): bool
	{
		//Xác định mảng các ID
		$classIds = is_array($pks) ? $pks : [$pks];
		$classIds = array_filter($classIds);

		//Xóa sinh viên của lớp học
		$db = $this->getDatabase();
		foreach ($classIds as $classId)
		{
			if(!$this->canDelete($classId))
			{
				$class = $this->getItem($classId);
				$msg = sprintf('Không thể xóa lớp "%s (%s)". Hãy đảm bảo rằng chưa có sinh viên nào có điểm quá trình', $class->name, $class->code);
				throw new Exception($msg);
			}
			$query = $db->getQuery(true)
				->delete('#__eqa_class_learner')
				->where('class_id = '.$classId);
			$db->setQuery($query);
			if(!$db->execute())
				return false;
		}

		//Call parent
		return parent::delete($pks);
	}

	public function getLogObjectType(): int
	{
		return ObjectType::CreditClass->value;
	}

	/**
	 * Check whether PAM of a learner can be editted. For now a PAM record can be editted
	 * if the learner has not gotten mark from any exams yet.
	 *
	 * @param   int  $classId
	 * @param   int  $learnerId
	 *
	 * @return bool
	 *
	 * @since 1.2.3
	 */
	public function canEditPam(int $classId, int $learnerId):bool
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from('#__eqa_exam_learner')
			->where([
				'class_id = '.$classId,
				'learner_id= '.$learnerId,
				'conclusion IS NOT NULL'
			])
			->setLimit(1);
		$db->setQuery($query);
		return !$db->loadResult();
	}

	/**
	 * Thêm HVSV vào một lớp học phần. Có sử dụng transaction.
	 *
	 * @param   int    $classId       ID của lớp học phần.
	 * @param   array  $learnerCodes  Mảng chứa các mã HVSV cần thêm vào lớp dạng $rowIndex => $learnerCode.
	 * @return array   [$countTotal, $countAdded, $countExisting]
	 * @throws Exception Controller chịu trách nhiệm xử lý lỗi
	 * @since 1.2.0
	 */
	public function importLearners(int $classId, array $learnerCodes, array $learnerMap=[]): array
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Check if the class exists and is published
		$db->setQuery("SELECT COUNT(*) FROM `#__eqa_classes` WHERE `id`=$classId AND `published`=1");
		$count = (int) $db->loadResult();
		if($count==0)
			throw new Exception('Lớp không tồn tại hoặc đã bị vô hiệu hóa');

		//2. Check if any of these learners does not exist in the database
		//   If there are absentees, throw an exception with a list of whole codes,
		//   so the user can fix all of the errors at once instead of fixing them one-by-one
		if(empty($learnerMap))
			$learnerMap = DatabaseHelper::getLearnerMap([],8000);   //Lấy tối đa 8000 bản ghi
		if(empty($learnerMap))
			throw new Exception('Không tìm thấy dữ liệu học viên');
		$absentees = [];
		foreach ($learnerCodes as $rowIndex => $learnerCode)
		{
			if(!array_key_exists($learnerCode, $learnerMap))
				$absentees[] = $learnerCode . "({$rowIndex})";
		}
		if(!empty($absentees))
		{
			$msg = sprintf('Có %d HVSV không tồn tại trong CSDL: %s',
				count($absentees),
				htmlentities(implode('; ', $absentees))
			);
			throw new Exception($msg);
		}

		//3. Load all existing learners for this class
		$db->setQuery("SELECT `learner_id` FROM `#__eqa_class_learner` WHERE `class_id`=$classId");
		$existingLearnerIds = $db->loadColumn();

		//4. Try to add learners to the class
		$db->transactionStart();
		$countExisting=0;
		try
		{
			foreach ($learnerCodes as $rowIndex => $learnerCode)        //$rowIndex is the index of row in the input Excel file
			{
				//1. Get the learner id
				$learnerId = $learnerMap[$learnerCode];

				//2. Check if the learner has already been added to the class
				if(in_array($learnerId, $existingLearnerIds))
				{
					$countExisting++;
					continue; //Skip this row since it's already in the database
				}

				//3. Add the learner to the class
				$db->setQuery("INSERT INTO `#__eqa_class_learner`(`class_id`, `learner_id`) VALUES($classId,$learnerId)");
				if(!$db->execute())
					throw new Exception("Thêm HVSV thất bại (dữ liệu tại dòng {$rowIndex})");
			}
			$countTotal = count($learnerCodes);
			$countAdded=$countTotal-$countExisting;

			//5. Update class size
			$newSize = count($existingLearnerIds) + $countAdded;
			$query = $db->getQuery(true)
				->update('#__eqa_classes')
				->set('`size` = '.$newSize)
				->where('id = '.$classId);
			$db->setQuery($query);
			if(!$db->execute())
				throw new Exception("Cập nhật sĩ số thất bại");

			//6. Commit changes
			$db->transactionCommit();
			return [$countTotal, $countAdded, $countExisting];
		}
		catch(Exception $e){
			$db->transactionRollback();
			throw $e;
		}
	}

	/**
	 * Đọc & chuẩn hóa điểm quá trình từ dữ liệu thô của một worksheet.
	 *
	 * KHÔNG truy vấn CSDL nên dùng chung cho cả nhập theo lớp (ClassController) lẫn nhập
	 * hàng loạt (ClassesController). Áp dụng: chuẩn hóa điểm (toPam), tự hoàn thiện PAM theo
	 * công thức mặc định (nếu bật), và ép TKD khi PAM > 0 nhưng TP1/TP2 dưới ngưỡng thành phần.
	 *
	 * Các dòng có điểm không hợp lệ được GOM lại và ném MỘT exception liệt kê đầy đủ.
	 *
	 * @param   array   $sheetData               Dữ liệu thô ($worksheet->toArray('')).
	 * @param   bool    $completePamCalculation  Tự hoàn thiện PAM nếu ô PAM trống.
	 * @param   string  $classCode               Mã lớp (chỉ dùng cho thông báo, có thể rỗng).
	 * @param   int     $firstDataRow            Chỉ số dòng đầu tiên chứa dữ liệu (mặc định 14 ~ dòng 15).
	 *
	 * @return  array   Danh sách ['row_index','learner_code','pam1','pam2','pam','allowed','description'].
	 *
	 * @throws  Exception  Nếu có dòng điểm không hợp lệ (gộp).
	 * @since   2.1.1
	 */
	public function readPamSheet(array $sheetData, bool $completePamCalculation, string $classCode = '', int $firstDataRow = 14): array
	{
		$app    = Factory::getApplication();
		$filter = InputFilter::getInstance();

		$data        = [];
		$invalidRows = [];

		for ($i = $firstDataRow; ; $i++) {
			if (empty($sheetData[$i])) {
				break;                                          // Hết dữ liệu
			}
			$learnerCode = trim($sheetData[$i][1] ?? '');       // Cột B
			if ($learnerCode === '') {
				break;                                          // Hết danh sách
			}
			$rowNumber = $i + 1;                                // Số hiệu dòng trên Excel

			// Ghi chú (cột M = 12) phục vụ suy luận điểm đặc biệt
			$descriptionRaw    = $sheetData[$i][12] ?? '';
			$descriptionForPam = $descriptionRaw !== '' ? mb_strtolower(trim($descriptionRaw)) : '';

			// PAM1 (cột I = 8), PAM2 (cột J = 9)
			$pam1 = ExamHelper::toPam($sheetData[$i][8] ?? '', $descriptionForPam);
			$pam2 = ExamHelper::toPam($sheetData[$i][9] ?? '', $descriptionForPam);
			if ($pam1 === false || $pam2 === false) {
				$invalidRows[] = $rowNumber . ' (' . $learnerCode . ')';
				continue;
			}

			// PAM (cột K = 10) — tự hoàn thiện nếu ô trống và bật tùy chọn
			$pamCell = $sheetData[$i][10] ?? '';
			if (is_string($pamCell)) {
				$pamCell = trim($pamCell);
			}
			if ($pamCell === '') {
				if (!$completePamCalculation) {
					$invalidRows[] = $rowNumber . ' (' . $learnerCode . ')';
					continue;
				}
				// Ưu tiên giữ điểm đặc biệt của TP1/TP2 (nếu có), ngược lại tính theo công thức mặc định
				$pam = $pam1 < 0 ? $pam1 : ($pam2 < 0 ? $pam2 : ExamHelper::calculatePamForDefaultFormular($pam1, $pam2));
			} else {
				$pam = ExamHelper::toPam($pamCell, $descriptionForPam);
				if ($pam === false) {
					$invalidRows[] = $rowNumber . ' (' . $learnerCode . ')';
					continue;
				}
			}

			// [MỚI] Ngưỡng thành phần: ép TKD nếu PAM dương mà TP1/TP2 dưới ngưỡng (C1/C2)
			if ($pam > 0 && ExamHelper::pamFailsComponentThreshold($pam1, $pam2)) {
				$label = $classCode !== ''
					? sprintf('Lớp %s - HVSV %s', $classCode, $learnerCode)
					: sprintf('HVSV %s', $learnerCode);
				$app->enqueueMessage(sprintf(
					'%s: TP1/TP2 dưới ngưỡng nhưng ĐQT = %s (>0); đã tự động chuyển thành TKD (thi không đạt)',
					$label, ExamHelper::markToText($pam)
				), 'warning');
				$pam = SpecialMark::TKD->value;
			}

			$data[] = [
				'row_index'    => $rowNumber,
				'learner_code' => $learnerCode,
				'pam1'         => $pam1,
				'pam2'         => $pam2,
				'pam'          => $pam,
				'allowed'      => ExamHelper::isAllowedToFinalExam($pam1, $pam2, $pam),
				'description'  => trim($filter->clean($descriptionRaw)),
			];
		}

		// Gom lỗi -> ném MỘT exception liệt kê đầy đủ
		if (!empty($invalidRows)) {
			$prefix = $classCode !== '' ? sprintf('Lớp %s: ', $classCode) : '';
			throw new Exception(sprintf(
				'%sCó %d dòng ĐQT không hợp lệ (dòng %s)',
				$prefix, count($invalidRows), implode(', ', $invalidRows)
			));
		}

		return $data;
	}

	/**
	 * Kiểm tra một lớp học phần có đủ điều kiện để nhập/ghi ĐQT hay không.
	 * Điều kiện: lớp đang published và CHƯA tổ chức thi (chưa có thí sinh được gán số báo danh).
	 *
	 * Tách riêng để kiểm tra SỚM (trước khi đọc dữ liệu & phát cảnh báo), tránh hiển thị
	 * thông báo cho lớp rốt cuộc bị từ chối.
	 *
	 * @param   int  $classId  ID lớp học phần.
	 *
	 * @return  void
	 * @throws  Exception  Nếu lớp không tồn tại/không published hoặc đã tổ chức thi.
	 * @since   2.x
	 */
	public function assertPamImportable(int $classId): void
	{
		$db = DatabaseHelper::getDatabaseDriver();

		// 1. Lớp phải tồn tại và đang published
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__eqa_classes'))
			->where($db->quoteName('id') . ' = ' . (int) $classId)
			->where($db->quoteName('published') . ' = 1');
		$db->setQuery($query);
		if ((int) $db->loadResult() === 0) {
			throw new Exception('Lớp không tồn tại hoặc đã bị vô hiệu hóa');
		}

		// 2. Chưa tổ chức thi (chưa ai được gán số báo danh)
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__eqa_exam_learner'))
			->where($db->quoteName('class_id') . ' = ' . (int) $classId)
			->where($db->quoteName('code') . ' IS NOT NULL');
		$db->setQuery($query);
		if ((int) $db->loadResult() > 0) {
			throw new Exception('Đã tổ chức thi, không thể nhập ĐQT');
		}
	}

	/**
	 * Nhập điểm quá trình (PAM) cho một lớp học phần trong một transaction.
	 *
	 * - KHÔNG ghi đè điểm của HVSV đã có ĐQT (đếm vào $countIgnored).
	 * - Nếu $data chứa HVSV không thuộc lớp thì gom lại và ném MỘT exception liệt kê đầy đủ.
	 * - Yêu cầu lớp đang published và chưa tổ chức thi.
	 *
	 * @param   int    $classId          ID lớp học phần.
	 * @param   array  $data             Mảng bản ghi từ readPamSheet().
	 * @param   bool   $setPamDateToday  Ghi nhận hôm nay là ngày bàn giao ĐQT nếu toàn lớp đã có điểm.
	 *
	 * @return  array  [$classSize, $countUpdated, $countIgnored, $npam]
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function importPams(int $classId, array $data, bool $setPamDateToday): array
	{
		$db = DatabaseHelper::getDatabaseDriver();

		// 1-2. Điều kiện được phép nhập ĐQT (published + chưa tổ chức thi)
		$this->assertPamImportable($classId);

		// 3. Bản đồ mã HVSV -> id trong lớp
		$query = $db->getQuery(true)
			->select($db->quoteName('b.code', 'code') . ', ' . $db->quoteName('a.learner_id', 'id'))
			->from($db->quoteName('#__eqa_class_learner', 'a'))
			->leftJoin($db->quoteName('#__eqa_learners', 'b') . ' ON ' . $db->quoteName('b.id') . ' = ' . $db->quoteName('a.learner_id'))
			->where($db->quoteName('a.class_id') . ' = ' . (int) $classId);
		$db->setQuery($query);
		$classLearnerMap = $db->loadAssocList('code', 'id');    // [code] => id
		if (empty($classLearnerMap)) {
			throw new Exception('Lớp học phần rỗng, không thể nhập ĐQT');
		}

		// 4. HVSV không thuộc lớp -> gom & ném MỘT exception
		$absentees = [];
		foreach ($data as $item) {
			if (!array_key_exists($item['learner_code'], $classLearnerMap)) {
				$absentees[] = $item['learner_code'] . ' (' . $item['row_index'] . ')';
			}
		}
		if (!empty($absentees)) {
			throw new Exception(sprintf(
				'Có %d HVSV không tồn tại trong lớp học phần: %s',
				count($absentees), htmlentities(implode('; ', $absentees))
			));
		}

		// 5. Tập id HVSV đã có ĐQT (để KHÔNG ghi đè)
		$query = $db->getQuery(true)
			->select($db->quoteName('learner_id'))
			->from($db->quoteName('#__eqa_class_learner'))
			->where($db->quoteName('class_id') . ' = ' . (int) $classId)
			->where('(' . $db->quoteName('pam1') . ' IS NOT NULL OR '
				. $db->quoteName('pam2') . ' IS NOT NULL OR '
				. $db->quoteName('pam')  . ' IS NOT NULL)');
		$db->setQuery($query);
		$scoredLearnerIds = array_map('intval', $db->loadColumn());

		// 6. Ghi điểm trong transaction
		$countUpdated = 0;
		$countIgnored = 0;
		$db->transactionStart();
		try {
			foreach ($data as $item) {
				$learnerId = (int) $classLearnerMap[$item['learner_code']];

				// Không ghi đè HVSV đã có ĐQT
				if (in_array($learnerId, $scoredLearnerIds, true)) {
					$countIgnored++;
					continue;
				}

				$allowed     = !empty($item['allowed']);
				$description = $item['description'] ?? '';

				$setData = [
					$db->quoteName('pam1')    . ' = ' . (float) $item['pam1'],
					$db->quoteName('pam2')    . ' = ' . (float) $item['pam2'],
					$db->quoteName('pam')     . ' = ' . (float) $item['pam'],
					$db->quoteName('allowed') . ' = ' . (int) $allowed,
				];
				$setData[] = $description === ''
					? $db->quoteName('description') . ' = NULL'
					: $db->quoteName('description') . ' = ' . $db->quote($description);
				if (!$allowed) {
					$setData[] = $db->quoteName('expired') . ' = 1';
				}

				$query = $db->getQuery(true)
					->update($db->quoteName('#__eqa_class_learner'))
					->set($setData)
					->where($db->quoteName('class_id') . ' = ' . (int) $classId)
					->where($db->quoteName('learner_id') . ' = ' . $learnerId);
				$db->setQuery($query);
				if (!$db->execute()) {
					throw new Exception(sprintf('Nhập ĐQT thất bại (dữ liệu tại dòng %s)', $item['row_index']));
				}
				$countUpdated++;
			}

			// 7. Cập nhật npam
			$query = $db->getQuery(true)
				->select('COUNT(*)')
				->from($db->quoteName('#__eqa_class_learner'))
				->where($db->quoteName('class_id') . ' = ' . (int) $classId)
				->where($db->quoteName('pam') . ' IS NOT NULL');
			$db->setQuery($query);
			$npam = (int) $db->loadResult();

			$query = $db->getQuery(true)
				->update($db->quoteName('#__eqa_classes'))
				->set($db->quoteName('npam') . ' = ' . $npam)
				->where($db->quoteName('id') . ' = ' . (int) $classId);
			$db->setQuery($query);
			if (!$db->execute()) {
				throw new Exception('Cập nhật số lượng HVSV có ĐQT thất bại');
			}

			$classSize = count($classLearnerMap);

			// 8. Ngày bàn giao ĐQT nếu toàn lớp đã có điểm (lưu UTC)
			if ($setPamDateToday && $npam === $classSize) {
				$query = $db->getQuery(true)
					->update($db->quoteName('#__eqa_classes'))
					->set($db->quoteName('pamdate') . ' = ' . $db->quote(DatetimeHelper::getCurrentUtcTime()))
					->where($db->quoteName('id') . ' = ' . (int) $classId);
				$db->setQuery($query);
				if (!$db->execute()) {
					throw new Exception('Cập nhật ngày bàn giao ĐQT thất bại');
				}
			}

			$db->transactionCommit();
		} catch (Exception $e) {
			$db->transactionRollback();
			throw $e;
		}

		return [$classSize, $countUpdated, $countIgnored, $npam];
	}

	/**
	 * Nhập ĐQT cho một worksheet (một lớp học phần) trong luồng nhập hàng loạt.
	 *
	 * Chỉ điều phối: parse tên lớp (C7) -> classId, kiểm tra lớp trắng, rồi ủy thác đọc dữ liệu
	 * cho readPamSheet() và ghi CSDL cho importPams(). KHÔNG catch exception về dữ liệu — để lan
	 * lên controller xử lý theo từng sheet.
	 *
	 * @param   array   $sheetData   Dữ liệu thô của worksheet.
	 * @param   string  $sheetTitle  Tên worksheet (cho thông báo).
	 * @param   string  $fileName    Tên file Excel (cho thông báo).
	 * @param   array   $options     ['ignoreBlankClasses','completePamCalculation','pamDateToday'] (bool).
	 *
	 * @return  array   ['status' => int (PAM_SHEET_*), 'classCode' => string]
	 *
	 * @throws  Exception
	 * @since   2.x
	 */
	public function importPamForSheet(array $sheetData, string $sheetTitle, string $fileName, array $options): array
	{
		$app = Factory::getApplication();

		$completePamCalculation = !empty($options['completePamCalculation']);
		$ignoreBlankClasses     = !empty($options['ignoreBlankClasses']);
		$pamDateToday           = !empty($options['pamDateToday']);

		// 1. Parse tên lớp (C7) & resolve classId
		$parser    = new CreditClassNameParser();
		$className = trim($sheetData[6][2] ?? '');
		if (!$parser->parse($className)) {
			$app->enqueueMessage(htmlentities(sprintf('Tên lớp không hợp lệ: %s --> %s : %s', $fileName, $sheetTitle, $className)), 'error');
			return ['status' => self::PAM_SHEET_SKIPPED, 'classCode' => ''];
		}
		if (!$parser->isPrimaryClass()) {                       // Bỏ qua lớp con
			return ['status' => self::PAM_SHEET_SKIPPED, 'classCode' => ''];
		}

		$subjectCode = trim($sheetData[5][12] ?? '');
		$classCode   = $subjectCode . '-' . $parser->getClassCodeTail();
		$classId     = DatabaseHelper::getClassId($classCode);
		if (empty($classId)) {
			$app->enqueueMessage(sprintf('Mã lớp học phần "%s" không tồn tại', $classCode), 'error');
			return ['status' => self::PAM_SHEET_SKIPPED, 'classCode' => $classCode];
		}

		// 2. Lớp trắng? (TP1 của HVSV đầu tiên ~ ô I15). Luôn bỏ qua & tiếp tục;
		//    nếu KHÔNG chọn "Bỏ qua lớp trắng" thì đánh dấu để controller gộp báo cáo.
		if (trim($sheetData[14][8] ?? '') === '') {
			$status = $ignoreBlankClasses ? self::PAM_SHEET_SKIPPED : self::PAM_SHEET_BLANK;
			return ['status' => $status, 'classCode' => $classCode];
		}

		// 2.5. [MỚI] Kiểm tra điều kiện được phép nhập ĐQT TRƯỚC khi đọc dữ liệu,
		//      để không phát cảnh báo TKD cho lớp không đủ điều kiện.
		$this->assertPamImportable($classId);

		// 3. Đọc dữ liệu (có thể ném exception gộp nếu có dòng không hợp lệ)
		$data = $this->readPamSheet($sheetData, $completePamCalculation, $classCode);

		// 4. Ghi CSDL (tự quản transaction; có thể ném exception nếu có HVSV lạ / đã thi...)
		[$classSize, $countUpdated, $countIgnored, $npam] = $this->importPams($classId, $data, $pamDateToday);

		// 5. Tổng kết cho lớp
		$app->enqueueMessage(sprintf(
			'Lớp %s (sĩ số %d): đã nhập %d, bỏ qua %d (đã có điểm), tổng đã có ĐQT %d/%d',
			$classCode, $classSize, $countUpdated, $countIgnored, $npam, $classSize
		), $npam === $classSize ? 'message' : 'warning');

		return ['status' => self::PAM_SHEET_PROCESSED, 'classCode' => $classCode];
	}

	public function updatePam(int $classId, int $learnerId, float $pam1, float $pam2, float $pam, bool $allowed, bool $expired, string $description)
	{
		$userId = Factory::getApplication()->getIdentity()->id;
		$currentTime = DatetimeHelper::getCurrentUtcTime();
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->update('#__eqa_class_learner')
			->set([
				'description='.$db->quote($description),
				'pam1='.$pam1,
				'pam2='.$pam2,
				'pam='.$pam,
				'allowed='.intval($allowed),
				'expired='.intval($expired),
				'modified_by='.$userId,
				'modified_at=' . $db->quote($currentTime),
			])
			->where('class_id = '.$classId.' AND learner_id = '.$learnerId);
		$db->setQuery($query);

		//Execute the query. If the query fails, throw an exception with error message
		if(!$db->execute())
			throw new Exception($this->getError());
	}

	/**
	 * Xuất danh sách điểm quá trình của một lớp học phần.
	 * Thứ tự sắp xếp được lấy từ tham số cấu hình 'pam_sort_order':
	 *   - 'name': sắp theo tên (firstname) rồi họ đệm (lastname)
	 *   - 'code': sắp theo mã người học
	 *
	 * @param   int  $classId  ID của lớp học phần
	 *
	 * @return array
	 */
	/**
	 * Xuất danh sách điểm quá trình của một lớp học phần.
	 * Thứ tự sắp xếp được lấy từ tham số cấu hình 'pam_sort_order':
	 *   - 'name': sắp theo tên (firstname) rồi họ đệm (lastname)
	 *   - 'code': sắp theo mã người học
	 *
	 * @param   int  $classId  ID của lớp học phần
	 *
	 * @return array
	 */
	public function exportPams(int $classId): array
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('b.code'),
			$db->quoteName('b.lastname'),
			$db->quoteName('b.firstname'),
			$db->quoteName('c.code') . ' AS ' . $db->quoteName('group'),
			$db->quoteName('a.pam1'),
			$db->quoteName('a.pam2'),
			$db->quoteName('a.pam'),
			$db->quoteName('a.description'),
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_class_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'a.learner_id=b.id')
			->leftJoin('#__eqa_groups AS c', 'c.id=b.group_id')
			->where('a.class_id = ' . $classId);

		/**
		 * Áp dụng thứ tự sắp xếp theo tham số cấu hình
		 * @var EqaComponent $component
		 */
		$component = ComponentHelper::getComponent();
		$configService = $component->getConfigService();
		if ($configService->getPersonSortOrder() === 'code')
		{
			$query->order($db->quoteName('b.code') . ' ASC');
		}
		else
		{
			// Mặc định: sắp theo tên (firstname) trước, rồi họ đệm (lastname)
			$query->order($db->quoteName('b.firstname') . ' ASC, ' . $db->quoteName('b.lastname') . ' ASC');
		}

		$db->setQuery($query);
		return $db->loadObjectList();
	}
	public function addLearners(int $classId, array $learnerCodes): void
    {
        $db = $this->getDatabase();

        //Lấy danh sách HVSV các khóa, các lớp đang published
        $query = $db->getQuery(true)
            ->from('#__eqa_learners AS a')
            ->leftJoin('#__eqa_groups AS b', 'a.group_id = b.id')
            ->leftJoin('#__eqa_courses AS c', 'b.course_id = c.id')
            ->select('a.id AS id, a.code AS code')
            ->where('b.published>0 AND c.published>0');
        $db->setQuery($query);
        $learnerIds = $db->loadAssocList('code','id');

        $countAbsence = 0;
        $countError = 0;
        $countSuccess = 0;
        $listAbsence = '';
        $listError = '';
        foreach ($learnerCodes as $learnerCode){
            //Kiểm tra xem learner có tồn tại hay không
            if(!isset($learnerIds[$learnerCode])){
                $countAbsence++;
                $listAbsence .= $learnerCode . '; ';
                continue;
            }
            $db->transactionStart();
            $learnerId = $learnerIds[$learnerCode];
            try {
                //Thêm vào lớp học phần
                $query = $db->getQuery(true)
                    ->insert('#__eqa_class_learner')
                    ->columns('class_id, learner_id')
                    ->values("$classId, $learnerId");
                $db->setQuery($query);
                $db->execute();

                //Cập nhật sĩ số
                $query = $db->getQuery(true)
                    ->update('#__eqa_classes')
                    ->set('`size` = `size` + 1')
                    ->where('id = '.$classId);
                $db->setQuery($query);
                $db->execute();

                $db->transactionCommit();
                $countSuccess++;
            }
            catch (Exception $e){
                $db->transactionRollback();
                $countError++;
                $listError .= $learnerCode . '; ';
            }
        }

        //Set messages
        $app = Factory::getApplication();
        if($countSuccess>0){
            $msg = Text::sprintf('COM_EQA_MSG_CLASS_IMPORT_N_LEARNERS_SUCCESS', $countSuccess);
            $app->enqueueMessage($msg,'success');
        }
        if($countAbsence>0){
            $msg = Text::sprintf('COM_EQA_MSG_CLASS_IMPORT_N_LEARNERS_ABSENT',$countAbsence);
            $msg .= ': ' . $listAbsence;
            $app->enqueueMessage($msg,'error');
        }
        if($countError>0){
            $msg = Text::sprintf('COM_EQA_MSG_CLASS_IMPORT_N_LEARNERS_FAILED',$countError);
            $msg .= ': ' . $listError;
            $app->enqueueMessage($msg,'error');
        }
    }
    public function removeLearner(int $classId, int $learnerId) : bool{
        $app = Factory::getApplication();
        $db = $this->getDatabase();

        //Check if this learner (of this class) present in any exam
        $columns = $db->quoteName(
            array('b.code', 'b.firstname', 'b.lastname'),
            array('code','firstname','lastname')
        );
        $query = $db->getQuery(true)
            ->from('#__eqa_exam_learner AS a')
            ->leftJoin('#__eqa_learners AS b', 'a.learner_id=b.id')
            ->select($columns)
            ->where('a.class_id='.$classId. ' AND a.learner_id='.$learnerId);
        $db->setQuery($query);
        $learner = $db->loadObject();
        if(!empty($learner)){
            $temp = htmlentities("$learner->lastname $learner->firstname ($learner->code)");
            $msg = Text::sprintf('COM_EQA_MSG_CANNOT_DELETE_S_BECAUSE_OF_EXAM', $temp);
            $app->enqueueMessage($msg,'error');
            return false;
        }

        //Try to remove the learner from the class
        $db->transactionStart();
        try
        {
            //Remove the leaner from the class
            $query = $db->getQuery(true)
                ->delete('#__eqa_class_learner')
                ->where('class_id = '.(int)$classId.' AND learner_id = '.(int)$learnerId);
            $db->setQuery($query);
            $db->execute();

            //Decrement the class size
            $query = $db->getQuery(true)
                ->update('#__eqa_classes')
                ->set('`size` = `size`-1')
                ->where('id = '.$classId);
            $db->setQuery($query);
            $db->execute();

            //Inform
            $db->setQuery('SELECT * FROM #__eqa_learners WHERE id='.$learnerId);
            $learner = $db->loadObject();
            $learnerInfo = htmlentities("$learner->lastname $learner->firstname ($learner->code)");
            $msg = Text::sprintf('COM_EQA_MSG_S_REMOVED_FROM_THE_CLASS', $learnerInfo);
            $app->enqueueMessage($msg,'success');

            //Commit
            $db->transactionCommit();
            return true;
        }
        catch (Exception $e){
            $db->transactionRollback();
            $msg = Text::_('COM_EQA_MSG_ERROR_TASK_FAILED');
            $app->enqueueMessage($msg,'error');
            return false;
        }
    }
    public function setAllowed(int $classId, array $learnerIds, bool $allowed):bool
    {
        $app = Factory::getApplication();
        $db = $this->getDatabase();

        //HVSV chỉ có thể được CHO THI hay CẤM THI khi chưa có trong danh sách thi
        //Vì thế, trước hết sẽ lập danh sách HVSV của lớp học này đã có mặt trong các môn thi (1 hoặc nhiều lần)
        $query = $db->getQuery(true)
            ->select('learner_id')
            ->from('#__eqa_exam_learner')
            ->where('class_id=' . $classId . ' AND mark_orig IS NOT NULL');
        $db->setQuery($query);
        $exceptedLearnerIds = $db->loadColumn();

        //Chia danh sách HVSV ban đầu thành 2 phần: đã/chưa có trong danh sách thi
        $acceptedIds = [];
        $rejectedIds = [];
        foreach ($learnerIds as $id){
            if(in_array($id, $exceptedLearnerIds))
                $rejectedIds[] = $id;
            else
                $acceptedIds[] = $id;
        }

        //Xử lý trường hợp không hợp lệ
        if(!empty($rejectedIds)){
            $rejectedIdSet = '(' . implode(',', $rejectedIds) . ')';
            $query = $db->getQuery(true)
                ->from('#__eqa_learners')
                ->select('code')
                ->where('id IN '.$rejectedIdSet);
            $db->setQuery($query);
            $rejectedCodes = implode(', ', $db->loadColumn());
            $msg = Text::sprintf('COM_EQA_MSG_CANNOT_ALLOW_OR_DENY_S_TAKE_EXAM', htmlentities($rejectedCodes));
            $app->enqueueMessage($msg,'error');
        }

        //Thiết lập thuộc tính 'allowed' cho các trươờng hợp hợp lệ
        if(!empty($acceptedIds))
        {
            $acceptedIdSet = '(' . implode(',', $acceptedIds) . ')';

            //Lấy danh sách HVSV để xuất thông báo
            $query = $db->getQuery(true)
                ->from('#__eqa_learners')
                ->select('code')
                ->where('id IN '.$acceptedIdSet);
            $db->setQuery($query);
            $acceptedCodes = implode(', ', $db->loadColumn());

            //Thực hiện thay đổi 'allowed' thì cũng cần thay đổi 'expired'
            $valueAllowed = $allowed ? 1 : 0;
            $valueExpired = $allowed ? 0 : 1;
            $query = $db->getQuery(true)
                ->update('#__eqa_class_learner')
                ->set(array(
                    $db->quoteName('allowed').'='.$valueAllowed,
                    $db->quoteName('expired').'='.$valueExpired
                ))
                ->where('class_id = '. $classId . ' AND learner_id IN '. $acceptedIdSet);
            $db->setQuery($query);
            if($db->execute()) {
                $msg = Text::_('COM_EQA_MSG_TASK_SUCCESS') . ': ' . htmlentities($acceptedCodes);
                $app->enqueueMessage($msg, 'success');
                return true;
            }
            else {
                $msg = Text::_('COM_EQA_MSG_ERROR_TASK_FAILED') . ': ' . htmlentities($acceptedCodes);
                $app->enqueueMessage($msg, 'error');
                return false;
            }
        }

        return true;
    }
	public function getLearnerIds(int $classId): array
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$query = $db->getQuery(true)
			->select('learner_id')
			->from('#__eqa_class_learner')
			->where('class_id = '.$classId);
		$db->setQuery($query);
		return $db->loadColumn();
	}

	public function getLearners(int $classId): array
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('a.class_id')            . ' AS ' . $db->quoteName('classId'),
			$db->quoteName('a.learner_id')          . ' AS ' . $db->quoteName('learnerId'),
			$db->quoteName('b.code')                . ' AS ' . $db->quoteName('learnerCode'),
			$db->quoteName('b.lastname')            . ' AS ' . $db->quoteName('lastname'),
			$db->quoteName('b.firstname')           . ' AS ' . $db->quoteName('firstname'),
			$db->quoteName('a.ntaken')              . ' AS ' . $db->quoteName('ntaken'),
			$db->quoteName('a.expired')             . ' AS ' . $db->quoteName('expired'),
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_class_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'a.learner_id=b.id')
			->where('a.class_id = ' . $classId);
		$db->setQuery($query);
		return $db->loadObjectList();
	}
	public function getLearnerInfo($classId, $learnerId)
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = [
			$db->quoteName('a.class_id')            . ' AS ' . $db->quoteName('classId'),
			$db->quoteName('a.learner_id')          . ' AS ' . $db->quoteName('learnerId'),
			$db->quoteName('b.code')                . ' AS ' . $db->quoteName('learnerCode'),
			$db->quoteName('b.lastname')            . ' AS ' . $db->quoteName('lastname'),
			$db->quoteName('b.firstname')           . ' AS ' . $db->quoteName('firstname'),
			$db->quoteName('a.pam1')                . ' AS ' . $db->quoteName('pam1'),
			$db->quoteName('a.pam2')                . ' AS ' . $db->quoteName('pam2'),
			$db->quoteName('a.pam')                 . ' AS ' . $db->quoteName('pam'),
			$db->quoteName('a.allowed')             . ' AS ' . $db->quoteName('allowed'),
			$db->quoteName('a.description')         . ' AS ' . $db->quoteName('description'),
			$db->quoteName('a.ntaken')              . ' AS ' . $db->quoteName('ntaken'),
			$db->quoteName('a.expired')             . ' AS ' . $db->quoteName('expired'),
		];
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_class_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'a.learner_id=b.id')
			->where('a.class_id = '.$classId.' AND a.learner_id = '.$learnerId);
		$db->setQuery($query);
		return $db->loadObject();
	}

	/**
	 * Tạo lớp học phần cho một lớp hành chính hoặc nhóm người học.
	 *
	 * @param   string  $targetType   'group' hoặc 'cohort'
	 * @param   int     $targetId     ID của lớp hành chính hoặc nhóm
	 * @param   int     $subjectId    ID môn học
	 * @param   int     $term         Học kỳ
	 * @param   int     $academicyear Năm học (encoded INT, ví dụ: 2025)
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   2.0.4
	 */
	public function addForGroupOrCohort(
		string $targetType,
		int $targetId,
		int $subjectId,
		int $term,
		int $academicyear
	): void {
		$db = DatabaseHelper::getDatabaseDriver();

		// 1. Lấy mã và danh sách người học của target
		if ($targetType === 'group') {
			$query = $db->getQuery(true)
				->select($db->quoteName('code'))
				->from($db->quoteName('#__eqa_groups'))
				->where('id = ' . $targetId);
			$db->setQuery($query);
			$targetCode = $db->loadResult();

			$query = $db->getQuery(true)
				->select($db->quoteName('id'))
				->from($db->quoteName('#__eqa_learners'))
				->where('group_id = ' . $targetId);
			$db->setQuery($query);
			$learnerIds = $db->loadColumn();

		} elseif ($targetType === 'cohort') {
			$query = $db->getQuery(true)
				->select($db->quoteName('code'))
				->from($db->quoteName('#__eqa_cohorts'))
				->where('id = ' . $targetId);
			$db->setQuery($query);
			$targetCode = $db->loadResult();

			$query = $db->getQuery(true)
				->select($db->quoteName('learner_id'))
				->from($db->quoteName('#__eqa_cohort_learner'))
				->where('cohort_id = ' . $targetId);
			$db->setQuery($query);
			$learnerIds = $db->loadColumn();

		} else {
			throw new Exception('Invalid target type');
		}

		// 2. Lấy mã và tên môn học
		$query = $db->getQuery(true)
			->select($db->quoteName(['code', 'name']))
			->from($db->quoteName('#__eqa_subjects'))
			->where('id = ' . $subjectId);
		$db->setQuery($query);
		$subject = $db->loadObject();

		// 3. Tính 2 chữ số cuối của năm học trực tiếp từ INT
		//    Ví dụ: 2025 % 100 = 25
		$firstYear = $academicyear % 100;

		// 4. Tính mã và tên lớp học phần
		$classCode = sprintf('%s-%d-%02d(%s-01)', $subject->code, $term, $firstYear, $targetCode);
		$className = sprintf('%s-%d-%02d(%s-01)', $subject->name, $term, $firstYear, $targetCode);

		$db->transactionStart();
		try {
			// 5. Tạo lớp học phần mới
			$query = $db->getQuery(true)
				->insert($db->quoteName('#__eqa_classes'))
				->columns($db->quoteName(['coursegroup', 'code', 'name', 'subject_id', 'term', 'academicyear', 'size']))
				->values(implode(',', [
					$db->quote($targetCode),
					$db->quote($classCode),
					$db->quote($className),
					(int) $subjectId,
					(int) $term,
					(int) $academicyear,
					count($learnerIds),
				]));
			$db->setQuery($query);
			if (!$db->execute()) {
				throw new Exception('Tạo lớp học phần mới thất bại');
			}
			$classId = $db->insertid();

			// 6. Thêm người học vào lớp
			$tuples = array_map(fn($id) => $classId . ',' . (int) $id, $learnerIds);
			$query  = $db->getQuery(true)
				->insert($db->quoteName('#__eqa_class_learner'))
				->columns('class_id, learner_id')
				->values($tuples);
			$db->setQuery($query);
			if (!$db->execute()) {
				throw new Exception('Thêm HVSV vào lớp học phần mới thất bại');
			}

			$db->transactionCommit();

		} catch (Exception $e) {
			$db->transactionRollback();
			throw $e;
		}
	}
}
