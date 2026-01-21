<?php
namespace Kma\Component\Eqa\Administrator\Model;
defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Kma\Component\Eqa\Administrator\Base\EqaListModel;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;

class SecondAttemptsModel extends EqaListModel{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields']=array('id');
        parent::__construct($config, $factory);
    }
    protected function populateState($ordering = 'id', $direction = 'desc')
    {
        parent::populateState($ordering, $direction);
    }
    public function getListQuery()
    {
        $db = $this->getDatabase();
        $query =  $db->getQuery(true)
            ->from('#__eqa_secondattempts')
            ->select('*');
        $orderingCol = $query->db->escape($this->getState('list.ordering','id'));
        $orderingDir = $query->db->escape($this->getState('list.direction','desc'));
        $query->order($db->quoteName($orderingCol).' '.$orderingDir);

        return $query;
    }

	public function cleanup():int
	{
		return 0;
	}

	/**
	 * Search for all the learners in all the classes who are to take second attempts,
	 * and then insert them into the table, avoiding duplicates.
	 *
	 * @since version 1.2.4
	 */
	public function load():int
	{
		/**
		 * STEPS TO BE DONE:
		 * 1. Search over the talbes 'eqa_class_learner' and 'eqa_exam_learner' for those
		 *    tuples (class_id, learner_id, conclusion) that correspond to the learners
		 *    who have taken first attempt (ntaken>0) but still have right to take
		 *    another attempt (expired=0) after the last try (exam_id=max).
		 * 2. Insert these tuples into the table 'eqa_secondattempts', ignoring existing
		 *    pairs of (class_id, learner_id). If 'conclusion' of the tuple is equal to
		 *    'deferred' (ExamHelper::CONCLUSION_DEFERRED), set the column 'paid' to true (1),
		 *    otherwise set to false (0).
		 */
		$db = DatabaseHelper::getDatabaseDriver();

		/**
		 * 1. Search over the talbes 'eqa_class_learner' and 'eqa_exam_learner' for those
		 *    tuples (class_id, learner_id, conclusion) that correspond to the learners
		 *    who have taken first attempt (ntaken>0) but still have right to take
		 *    another attempt (expired=0) after the last try (exam_id=max).
		 */
		$subQuery = $db->getQuery(true);
		$subQuery->select('MAX(' . $db->quoteName('exam_id') . ')')
			->from($db->quoteName('#__eqa_exam_learner', 'el2'))
			->where([
				$db->quoteName('el2.class_id') . ' = ' . $db->quoteName('el.class_id'),
				$db->quoteName('el2.learner_id') . ' = ' . $db->quoteName('el.learner_id')
			]);
		$query = $db->getQuery(true)
			->select([
				'el.class_id',
				'el.learner_id',
				'el.conclusion'
			])
			->from($db->quoteName('#__eqa_exam_learner', 'el'))
			->innerJoin(
				$db->quoteName('#__eqa_class_learner', 'cl') . ' ON ' .
				$db->quoteName('cl.class_id') . ' = ' . $db->quoteName('el.class_id') .
				' AND ' . $db->quoteName('cl.learner_id') . ' = ' . $db->quoteName('el.learner_id')
			)
			->where([
				$db->quoteName('cl.ntaken') . ' > 0',
				$db->quoteName('cl.expired') . ' = 0',
				$db->quoteName('el.exam_id') . ' = (' . $subQuery . ')'
			]);
		$db->setQuery($query);
		$secondAttempts = $db->loadObjectList();
		if(empty($secondAttempts))
			return 0;

		/**
		 * 2. Insert these tuples into the table 'eqa_secondattempts', ignoring existing
		 *    pairs of (class_id, learner_id). If 'conclusion' of the tuple is equal to
		 *    'deferred' (ExamHelper::CONCLUSION_DEFERRED), set the column 'paid' to true (1),
		 *    otherwise set to false (0).
		 */
		$values = [];
		foreach ($secondAttempts as $row) {
			$paid = ($row->conclusion == 123) ? 1 : 0;
			$values[] = '(' . (int)$row->class_id . ', ' . (int)$row->learner_id . ', ' . $paid . ')';
		}
		$insertQuery = 'INSERT IGNORE INTO ' . $db->quoteName('#__eqa_secondattempts') .
			' (' . $db->quoteName('class_id') . ', ' .
			$db->quoteName('learner_id') . ', ' .
			$db->quoteName('paid') . ') VALUES ' .
			implode(', ', $values);
		$db->setQuery($insertQuery);
		$db->execute();

		return $db->getAffectedRows();
	}
}