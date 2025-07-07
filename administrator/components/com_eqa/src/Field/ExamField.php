<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Collator;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use PhpMyAdmin\DatabaseInterface;
use stdClass;

class ExamField extends GroupedlistField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public $type = 'exam';

	/**
	 * Method to get the grouped options for the list input.
	 *
	 * @return  array  An array of option groups.
	 * @since   1.0.0
	 */
	protected function getGroups()
	{
		// Get the parent groups
		$groups = parent::getGroups();

		// Get a database connection.
		$db = DatabaseHelper::getDatabaseDriver();

		// Query for the exam seasons (the groups).
		$querySeasons = $db->getQuery(true)
			->select($db->quoteName(['id', 'name']))
			->from($db->quoteName('#__eqa_examseasons'))
			->order($db->quoteName('id') . ' DESC');

		// Query for the exams (the options).
		$queryExams = $db->getQuery(true)
			->select($db->quoteName(['id', 'name', 'examseason_id']))
			->from($db->quoteName('#__eqa_exams'))
			->order($db->quoteName('name') . ' ASC');

		//Init the groups
		$groups = parent::getGroups();

		// Execute queries and retrieve results
		$examseasons = $db->setQuery($querySeasons)->loadObjectList();
		$exams    = $db->setQuery($queryExams)->loadObjectList();

		if (!$examseasons || !$exams) return $groups;


		// Organize exams by season ID for efficient lookup
		$examsBySeason = [];
		foreach ($exams as $exam) {
			$examsBySeason[$exam->examseason_id][] = $exam;
		}

		foreach ($examseasons as $season) {
			// Only create a group if there are exams in this season
			if(empty($examsBySeason[$season->id]))
				continue;

			// 1. Init a group object with title and empty options array
			$groupTitle = $season->name;
			$groupOptions = [];

			// 2. Loop through the exams for this season
			foreach ($examsBySeason[$season->id] as $exam) {
				$groupOptions[] = HTMLHelper::_('select.option', $exam->id, $exam->name);
			}

			// Add the completed group object to the main groups array
			$groups[$groupTitle] = $groupOptions;
		}

		return $groups;
	}
}