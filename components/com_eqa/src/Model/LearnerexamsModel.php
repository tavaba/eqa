<?php
namespace Kma\Component\Eqa\Site\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class LearnerexamsModel extends EqaListModel{
	public function __construct($config = [], MVCFactoryInterface $factory = null)
	{
		$config['filter_fields']=array('academicyear', 'term', 'examseason', 'exam');
		parent::__construct($config, $factory);
	}

	protected function populateState($ordering = 'exam', $direction = 'ASC')
	{
		parent::populateState($ordering, $direction);
	}

	public function getListQuery()
	{
		//Xác định HVSV
		$learnerId = (int)$this->getState('learner_id',-1);

		//Xây dựng truy vấn
		$db = DatabaseHelper::getDatabaseDriver();
		$columns = array(
			'a.exam_id AS id',
			'a.learner_id AS learner_id',
			'c.name AS examseason',
			'e.code AS academicyear',
			'c.term AS term',
			'a.exam_id AS exam_id',
			'b.name AS exam',
			'b.testtype AS testtype',
			'd.pam1 AS pam1',
			'd.pam2 AS pam2',
			'd.pam AS pam',
			'a.attempt AS attempt',
			'a.mark_orig AS mark_orig',
			'a.mark_final AS mark_final',
			'a.module_mark AS module_mark',
			'a.module_grade AS module_grade',
			'a.conclusion AS conclusion',
			'c.ppaa_req_enabled AS ppaa_req_enabled',
			'c.ppaa_req_deadline AS ppaa_req_deadline'
		);
		/*
		$columns = $db->quoteName(
			array('a.learner_id', 'c.name',     'a.exam_id', 'b.name', 'd.pam1', 'd.pam2', 'd.pam', 'a.attempt', 'a.mark_final', 'a.module_mark', 'a.module_grade', 'a.conclusion'),
			array('learner_id',   'examseason', 'exam_id',   'exam',   'pam1',   'pam2',   'pam',   'attempt',   'mark_final',   'module_mark',   'module_grade',   'conclusion')
		);
		*/

		$query = $db->getQuery(true)
			->select($columns)
			->from('#__eqa_exam_learner AS a')
			->leftJoin('#__eqa_exams AS b', 'b.id = a.exam_id')
			->leftJoin('#__eqa_examseasons AS c', 'c.id = b.examseason_id')
			->leftJoin('#__eqa_class_learner AS d', 'd.class_id = a.class_id AND d.learner_id = a.learner_id')
			->leftJoin('#__eqa_academicyears AS e', 'e.id=c.academicyear_id')
			->where('a.learner_id=' . $learnerId);

		// Filter by search in title.
		$search = $this->getState('filter.search');
		if (!empty($search))
		{
			$search = $db->quote('%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%'));
			$query->where('(b.name LIKE ' . $search . ')');
		}

		$examseasonId = $this->getState('filter.examseason_id', null);
		if(is_numeric($examseasonId))
			$query->where('c.id='.$examseasonId);
		else if($examseasonId==="default" || is_null($examseasonId))
			$query->where('`c`.`default`=1');

		// Add the list ordering clause.
		$orderCol  = $this->getState('list.ordering', 'exam');
		$orderDirn = $this->getState('list.direction', 'ASC');

		$query->order($db->escape($orderCol) . ' ' . $db->escape($orderDirn));

		return $query;
	}

	public function getStoreId($id = '')
	{
		$id .= ':' . $this->getState('filter.examseason_id');
		return parent::getStoreId($id);
	}
}
