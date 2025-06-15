<?php
namespace Kma\Component\Eqa\Administrator\Controller;
use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\EqaFormController;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use stdClass;

defined('_JEXEC') or die();

class FixerController extends  EqaFormController
{
	public function initExaminees(): array
	{
		$examinees = [];
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='AT210405';
		$oneExaminee->pam1=5.0;
		$oneExaminee->pam2=8.0;
		$oneExaminee->origMark=4.0;
		$oneExaminee->moduleMark=4.6;
		$oneExaminee->moduleGrade='D';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='CT090302';
		$oneExaminee->pam1=6.0;
		$oneExaminee->pam2=8.0;
		$oneExaminee->origMark=7.0;
		$oneExaminee->moduleMark=6.9;
		$oneExaminee->moduleGrade='C+';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='AT210303';
		$oneExaminee->pam1=5.0;
		$oneExaminee->pam2=7.0;
		$oneExaminee->origMark=6.0;
		$oneExaminee->moduleMark=5.9;
		$oneExaminee->moduleGrade='C';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='DT080304';
		$oneExaminee->pam1=7.0;
		$oneExaminee->pam2=8.0;
		$oneExaminee->origMark=4.0;
		$oneExaminee->moduleMark=5.0;
		$oneExaminee->moduleGrade='D+';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='AT210522';
		$oneExaminee->pam1=9.0;
		$oneExaminee->pam2=10;
		$oneExaminee->origMark=4.5;
		$oneExaminee->moduleMark=5.9;
		$oneExaminee->moduleGrade='C';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='CT090222';
		$oneExaminee->pam1=5.5;
		$oneExaminee->pam2=8.0;
		$oneExaminee->origMark=0.0;
		$oneExaminee->moduleMark=1.9;
		$oneExaminee->moduleGrade='F';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='AT210523';
		$oneExaminee->pam1=7.5;
		$oneExaminee->pam2=9.0;
		$oneExaminee->origMark=7.0;
		$oneExaminee->moduleMark=7.3;
		$oneExaminee->moduleGrade='B';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='DT080223';
		$oneExaminee->pam1=9.0;
		$oneExaminee->pam2=10;
		$oneExaminee->origMark=8.0;
		$oneExaminee->moduleMark=8.4;
		$oneExaminee->moduleGrade='B+';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='DT080324';
		$oneExaminee->pam1=7.0;
		$oneExaminee->pam2=8.0;
		$oneExaminee->origMark=6.5;
		$oneExaminee->moduleMark=6.7;
		$oneExaminee->moduleGrade='C+';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='AT210529';
		$oneExaminee->pam1=8.0;
		$oneExaminee->pam2=8.0;
		$oneExaminee->origMark=6.5;
		$oneExaminee->moduleMark=7.0;
		$oneExaminee->moduleGrade='B';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='AT210534';
		$oneExaminee->pam1=7.5;
		$oneExaminee->pam2=8.0;
		$oneExaminee->origMark=9.5;
		$oneExaminee->moduleMark=8.9;
		$oneExaminee->moduleGrade='A';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='AT210336';
		$oneExaminee->pam1=8.0;
		$oneExaminee->pam2=9.0;
		$oneExaminee->origMark=0.0;
		$oneExaminee->moduleMark=2.5;
		$oneExaminee->moduleGrade='F';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='AT210135';
		$oneExaminee->pam1=8.0;
		$oneExaminee->pam2=10;
		$oneExaminee->origMark=9.0;
		$oneExaminee->moduleMark=8.9;
		$oneExaminee->moduleGrade='A';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='AT210437';
		$oneExaminee->pam1=8.0;
		$oneExaminee->pam2=9.0;
		$oneExaminee->origMark=5.5;
		$oneExaminee->moduleMark=6.3;
		$oneExaminee->moduleGrade='C+';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='AT210544';
		$oneExaminee->pam1=8.5;
		$oneExaminee->pam2=9.0;
		$oneExaminee->origMark=7.5;
		$oneExaminee->moduleMark=7.8;
		$oneExaminee->moduleGrade='B+';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='DT080142';
		$oneExaminee->pam1=6.5;
		$oneExaminee->pam2=9.0;
		$oneExaminee->origMark=10;
		$oneExaminee->moduleMark=9.2;
		$oneExaminee->moduleGrade='A+';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='DT080342';
		$oneExaminee->pam1=6.0;
		$oneExaminee->pam2=8.0;
		$oneExaminee->origMark=5.8;
		$oneExaminee->moduleMark=6.0;
		$oneExaminee->moduleGrade='C';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='AT210347';
		$oneExaminee->pam1=6.0;
		$oneExaminee->pam2=10;
		$oneExaminee->origMark=6.5;
		$oneExaminee->moduleMark=6.7;
		$oneExaminee->moduleGrade='C+';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='DT080244';
		$oneExaminee->pam1=8.5;
		$oneExaminee->pam2=9.0;
		$oneExaminee->origMark=9.0;
		$oneExaminee->moduleMark=8.9;
		$oneExaminee->moduleGrade='A';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='AT210546';
		$oneExaminee->pam1=8.0;
		$oneExaminee->pam2=8.0;
		$oneExaminee->origMark=9.0;
		$oneExaminee->moduleMark=8.7;
		$oneExaminee->moduleGrade='A';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='DT080347';
		$oneExaminee->pam1=8.5;
		$oneExaminee->pam2=8.0;
		$oneExaminee->origMark=5.5;
		$oneExaminee->moduleMark=6.4;
		$oneExaminee->moduleGrade='C+';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='CT090247';
		$oneExaminee->pam1=8.5;
		$oneExaminee->pam2=10;
		$oneExaminee->origMark=7.0;
		$oneExaminee->moduleMark=7.6;
		$oneExaminee->moduleGrade='B';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		$oneExaminee = new stdClass();
		$oneExaminee->learnerCode='AT210156';
		$oneExaminee->pam1=5.5;
		$oneExaminee->pam2=10;
		$oneExaminee->origMark=7.0;
		$oneExaminee->moduleMark=7.0;
		$oneExaminee->moduleGrade='B';
		$oneExaminee->finalMark=$oneExaminee->origMark;
		$examinees[] = $oneExaminee;
		return $examinees;
	}

	public function fix(): void
	{
		$examId=140;
		$db = DatabaseHelper::getDatabaseDriver();
		$this->setRedirect(Route::_('index.php?option=com_eqa', false));

		/**
		 * Sửa điểm môn giải tích 1 (mã môn thi là 140). Các bước thực hiện như sau
		 * 1.
		 */
		//Khởi tạo thông tin thí sinh
		$examinees = $this->initExaminees();

		//Lấy thông tin về kết quả hiện tại của từng thí sinh
		$columns = $db->quoteName(
			array('a.id', 'b.class_id', 'c.ntaken', 'c.expired',  'b.module_grade', 'b.anomaly', 'b.stimulation_id'),
			array('id',   'classId',    'ntaken',   'expired',    'moduleGrade',    'anomaly',   'stimulationId')
		);
		$db->transactionStart();
		try{
			foreach ($examinees as $examinee) {
				$query = $db->getQuery(true)
					->select($columns)
					->from('#__eqa_learners AS a')
					->leftJoin('#__eqa_exam_learner AS b', 'b.learner_id=a.id')
					->leftJoin('#__eqa_class_learner AS c', 'c.class_id=b.class_id AND c.learner_id=a.id')
					->where([
						'a.code = ' . $db->quote($examinee->learnerCode),
						'b.exam_id = ' . $examId
					]);
				$db->setQuery($query);
				$obj = $db->loadObject();
				if(empty($obj))
					throw new Exception('Không tìm thấy thí sinh '. $examinee->learnerCode);
				if($obj->ntaken != 1)
					throw new Exception('Số lượt đã thi của thí sinh '. $examinee->learnerCode . ' không hợp lệ');
				if(!empty($obj->anomaly))
					throw  new Exception('Thí sinh ' . $examinee->learnerCode . ' có bất thường');
				if(!empty($obj->stimulation_id))
					throw new Exception('Thí sinh ' . $examinee->learnerCode . ' có khuyến khích');
				if($obj->expired && $obj->moduleGrade=='F')
					throw new Exception('Thí sinh ' . $examinee->learnerCode . ' trượt và hết lượt');
				if(!$obj->expired && $obj->moduleGrade!='F')
					throw new Exception('Thí sinh ' . $examinee->learnerCode . ' đạt nhưng chưa hết lượt');

				$examinee->id = $obj->id;
				$examinee->classId = $obj->classId;
				if($examinee->moduleGrade=='F'){
					$examinee->conclusion = ExamHelper::CONCLUSION_FAILED;
					$examinee->expired=0;
				}
				else{
					$examinee->conclusion = ExamHelper::CONCLUSION_PASSED;
					$examinee->expired=1;
				}

				//Cập nhật thông tin môn thi
				$query = $db->getQuery(true)
					->update('#__eqa_exam_learner')
					->set([
						'mark_orig = ' . $examinee->origMark,
						'mark_final = ' . $examinee->finalMark,
						'module_mark = ' . $examinee->moduleMark,
						'module_grade = ' . $db->quote($examinee->moduleGrade),
						'conclusion = ' . $examinee->conclusion
					])
					->where('exam_id = ' . $examId . ' AND learner_id = ' . $examinee->id);
				$db->setQuery($query);
				if(!$db->execute())
					throw new Exception('Lỗi cập nhật môn thi của thí sinh ' . $examinee->learnerCode);

				//Cập nhật thông tin lớp học phần
				$query = $db->getQuery(true)
					->update('#__eqa_class_learner')
					->set('expired=' . $examinee->expired)
					->where('class_id = ' . $examinee->classId . ' AND learner_id = ' . $examinee->id);
				$db->setQuery($query);
				if(!$db->execute())
					throw new Exception('Lỗi cập nhật lớp học phần cho thí sinh ' . $examinee->learnerCode);
			}
			$db->transactionCommit();
			$msg = Text::sprintf("Đã sửa thông tin cho %d học viên", count($examinees));
			$this->setMessage($msg, 'success');

		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error ');
			$db->transactionRollback();
		}
	}
}