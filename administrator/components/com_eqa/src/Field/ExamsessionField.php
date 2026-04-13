<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use DateTime;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Library\Kma\Helper\DatabaseHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;

/**
 * Reference: https://www.abdulwaheed.pk/en/blog/41-information-technology/44-joomla/335-how-to-create-custom-form-field-for-custom-component-joomla-4.html
 * @since  1.6
 */
class ExamsessionField extends ListField
{
    protected $type = 'examsession';

    /**
     * Method to get a list of options for a list input.
     *
     * @return	array		An array of JHtml options.
     *
     * @since   1.0
     */
    protected function getOptions()
    {
        $db = DatabaseHelper::getDatabaseDriver();
        $columns = $db->quoteName(
            array('a.id', 'a.start', 'a.name'),
            array('id',   'start',   'name')
        );
        $query = $db->getQuery(true)
            ->select($columns)
            ->from('#__eqa_examsessions AS a')
	        ->leftJoin('#__eqa_examseasons AS b', 'a.examseason_id=b.id')
	        ->leftJoin('#__eqa_assessments AS c', 'a.assessment_id=c.id')
            ->where([
	            'b.completed IS NULL OR b.completed=0',
	            'c.completed IS NULL OR c.completed=0',
            ])
            ->order('`start` DESC');
        $db->setQuery($query);
        $items = $db->loadObjectList();
        $options = parent::getOptions();
        foreach ($items as $item)
        {
			$time = DatetimeHelper::convertToLocalTime($item->start);
			$dayofweek = DatetimeHelper::getDayOfWeek($time);
			$dayAndTime = DatetimeHelper::getDayAndTime($time);
            $option = $dayofweek . ' (' . $dayAndTime . ') - ' . $item->name;
            $options[] = HTMLHelper::_('select.option', $item->id, $option);
        }
        return $options;
    }

}
