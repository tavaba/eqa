<?php
namespace Kma\Component\Eqa\Administrator\Model;
use Exception;
use Kma\Component\Eqa\Administrator\Base\EqaAdminModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\StimulationHelper;

defined('_JEXEC') or die();

class FixerModel extends EqaAdminModel {
	public function fixNextAttemptLimitation(int $examseasonId, $fix=false)
	{
		$db = DatabaseHelper::getDatabaseDriver();

		//1. Get all exams of the current exam season
		$db->setQuery("SELECT id FROM #__eqa_exams WHERE examseason_id=".$examseasonId);
		$examIds = $db->loadColumn();
		if(count($examIds)==0)
			throw new Exception('Không tìm thấy kỳ thi nào trong mùa thi này.');

		//2. Get all student works of the current exam season
		//   that have 'attempt'>1,  'mark_orig'>6.9, 'final_mark'=6.9 and 'admissionyear'>=2021
		$columns = $db->quoteName(
			array('a.exam_id', 'a.class_id', 'a.learner_id', 'b.code',      'b.lastname', 'b.firstname', 'c.code', 'a.attempt', 'a.anomaly', 'e.pam1', 'e.pam2', 'e.pam', 'a.mark_orig'),
			array('exam_id',    'class_id',    'learnerId',  'learner_code', 'lastname',   'firstname',  'group',  'attempt',   'anomaly',  'pam1',     'pam2',  'pam',  'mark_orig')
		);
		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_learners AS b', 'b.id=a.learner_id')
			->leftJoin('#__eqa_groups AS c', 'c.id=b.group_id')
			->leftJoin('#__eqa_courses AS d', 'd.id=c.course_id')
			->leftJoin('#__eqa_class_learner AS e', 'e.class_id=a.class_id AND e.learner_id=a.learner_id')
			->where('a.attempt>1')
			->where('a.mark_orig>6.9')
			->where('a.mark_final=6.9')
			->where('d.admissionyear<=2020')
			->where('a.exam_id IN (' . implode(',', $examIds) . ')');
		$db->setQuery($query);
		$items = $db->loadObjectList();
		if(empty($items))
			throw new Exception('Không có học sinh nào cần sửa.');

		//Process
		foreach ($items as $item) {
			$item->stimulation_type= null;
			$item->mark_final = ExamHelper::calculateFinalMark($item->mark_orig, $item->anomaly, $item->attempt, 0, 2020 );
			$item->module_mark = ExamHelper::calculateModuleMark(0, $item->pam, $item->mark_orig, $item->attempt , 2020);
			$item->conclusion = ExamHelper::conclude($item->module_mark, $item->mark_orig, $item->anomaly, $item->attempt);
			$item->module_grade = ExamHelper::calculateModuleGrade($item->module_mark, $item->conclusion);

			if(!$fix)
				continue;
			$query = $db->getQuery(true)
				->update('#__eqa_exam_learner')
				->set([
					'mark_final=' . $item->mark_final,
					'module_mark=' . $item->module_mark,
					'module_grade=' . $db->quote($item->module_grade),
				])
				->where('exam_id='.$item->exam_id)
				->where('class_id='.$item->class_id);
			$db->setQuery($query);
			if (!$db->execute())
				throw new Exception('Lỗi cập nhật điểm');
		}


		//Group $items by 'exam_id' and return
		$data = [];
		foreach ($items as $item) {
			if(!isset($data[$item->exam_id]))
				$data[$item->exam_id]=[];
			$data[$item->exam_id][]=$item;
		}
		return $data;
	}
}
