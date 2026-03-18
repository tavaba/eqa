<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use Joomla\CMS\Factory;
use Kma\Component\Eqa\Administrator\Helper\TermHelper;
use Kma\Library\Kma\Model\AdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

defined('_JEXEC') or die();

class ConductModel extends AdminModel
{
	public function importItem(int $academicyearCode, int $term, object $item, bool $importMark, bool $importCredits, bool $updateExisting=true):void
	{
		$db = DatabaseHelper::getDatabaseDriver();
		$userId = (int)Factory::getApplication()->getIdentity()->id;

		//Load learner id
		$quotedLearnerCode = $db->quote($item->learnerCode);
		$db->setQuery("SELECT `id` FROM #__eqa_learners WHERE `code`={$quotedLearnerCode} LIMIT 1");
		$learnerId = (int)$db->loadResult();
		if (!$learnerId)
			throw new Exception('Không tìm thấy HVSV với mã: '.htmlspecialchars($item->learnerCode));

		//Check if the record (learner_id, academicyear, term) exists in database.
		$db->setQuery("SELECT * FROM #__eqa_conducts WHERE `learner_id`={$learnerId} AND `academicyear`={$academicyearCode} AND `term`={$term} LIMIT 1");
		$obj = $db->loadObject();
		if ($obj)
		{
			if(!$updateExisting)
				return;

			//Update
			$quotedNote = empty($item->note) ? $db->quote('') : $db->quote($item->note);
			$setClause = [];
			$setClause[] = 'excused_absence_count = ' . $item->excusedAbsenceCount;
			$setClause[] = 'unexcused_absence_count = ' . $item->unexcusedAbsenceCount;
			$setClause[] = 'award_count = ' . $item->awardCount;
			$setClause[] = 'disciplinary_action_count = '. $item->disciplinaryCount;
			$setClause[] = 'conduct_score = '.$item->conductScore;
			$setClause[] = 'conduct_rating = '. $db->quote($item->conductRating);
			$setClause[] = 'note = '. $quotedNote;
			$setClause[] = 'modified_by=' . $userId;
			$setClause[] = 'modified_at = NOW()';
			if ($importMark)
			{
				$setClause[] = 'academic_score = '.$item->academicScore;
				$setClause[] = 'academic_rating = '.$item->academicRating;
				$setClause[] = 'resit_count = ' . $item->resitCount;
				$setClause[] = 'retake_count = ' . $item->retakeCount;
			}
			if($importCredits)
			{
				$setClause[] = 'total_credits = ' . $item->totalCredits;
			}
			$query = $db->getQuery(true)
				->update('#__eqa_conducts')
				->set($setClause)
				->where('id='.$obj->id);
			$db->setQuery($query);
			if (!$db->execute())
			{
				$msg = sprintf('Có lỗi xảy ra khi cập nhật dữ liệu cho HVSV có mã <b>%s</b>.', htmlspecialchars($item->learnerCode));
				throw new Exception($msg);
			}
			return;
		}

		//Insert
		$quotedNote = empty($item->note) ? $db->quote('') : $db->quote($item->note);
		$columns = ['learner_id','academicyear','term','excused_absence_count','unexcused_absence_count',
			'award_count','disciplinary_action_count','conduct_score','conduct_rating','note', 'created_at', 'created_by'];
		$values = [$learnerId,$academicyearCode,$term,
			$item->excusedAbsenceCount,$item->unexcusedAbsenceCount,
			$item->awardCount,$item->disciplinaryCount,
			$item->conductScore,$item->conductRating,$quotedNote,
			$db->quote(date('Y-m-d H:i:s')), $userId
		];

		if($importMark)
		{
			$columns[] = 'academic_score';
			$columns[] = 'academic_rating';
			$values[] = $item->academicScore;
			$values[] = $item->academicRating;
		}
		if($importCredits)
		{
			$columns[] = 'total_credits';
			$values[] = $item->totalCredits;
		}

		$query = $db->getQuery(true)
				->insert('#__eqa_conducts')
				->columns($columns)
				->values(implode(',', $values));
		$db->setQuery($query);
		if (!$db->execute())
		{
			$msg = sprintf('Có lỗi xảy ra khi thêm mới dữ liệu cho HVSV có mã <b>%s</b>.', htmlspecialchars($item->learnerCode));
			throw new Exception($msg);
		}
	}
	public function getLearnerExams(int $learnerId, int $academicyearCode, int $term): array
	{
		$db      = DatabaseHelper::getDatabaseDriver();
		$columns = [
			'a.exam_id              AS      examId',
			'b.subject_id           AS      subjectId',
			'd.credits              AS      creditNumber',
			'b.is_pass_fail         AS      isPassFail',
			'a.conclusion           AS      conclusion',
			'a.module_mark          AS      moduleMark',
			'a.module_base4_mark    AS      moduleBase4Mark',
			'a.module_grade         AS      moduleGrade',
			'a.anomaly              AS      anomaly',
		];
		$query   = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_exams AS b', 'a.exam_id=b.id')
			->leftJoin('#__eqa_examseasons AS c', 'c.id=b.examseason_id')
			->leftJoin('#__eqa_subjects AS d', 'd.id=b.subject_id')
			->where([
				'a.learner_id=' . $learnerId,
				'c.academicyear=' . $academicyearCode
			]);
		if($term != TermHelper::TERM_NONE)
			$query->where('c.term=' . $term);
		$db->setQuery($query);
		return $db->loadObjectList();
	}
	public function updateAcademicResults(int $id, int $countResits, int $countRetakes, float $termMark, int $termRating, int $totalCredits):bool
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->update('#__eqa_conducts')
			->set([
				'resit_count='.$countResits,
				'retake_count='.$countRetakes,
				'total_credits='.$totalCredits,
				'academic_score='.$termMark,
				'academic_rating='.$termRating])
			->where('id='.$id);
		$db->setQuery($query);
		return $db->execute();
	}
}
