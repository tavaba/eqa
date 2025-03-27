<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use DateTime;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

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
        $db = $this->getDatabase();
        $columns = $db->quoteName(
            array('a.id', 'a.start', 'a.name'),
            array('id',   'start',   'name')
        );
        $query = $db->getQuery(true)
            ->select($columns)
            ->from('#__eqa_examsessions AS a')
            ->leftJoin('#__eqa_examseasons AS b', 'a.examseason_id=b.id')
            ->where('b.completed=0')
            ->order('`start` DESC');
        $db->setQuery($query);
        $items = $db->loadObjectList();
        $options = parent::getOptions();
        foreach ($items as $item)
        {
            $datetime = new DateTime($item->start);
            $date = new Date($item->start);
            $dayofweek = $date->format('l',true);
            $dayofmonth = $datetime->format('d') . '/' . $datetime->format('m');
            $time = $datetime->format('H') . ':' . $datetime->format('i');
            $option = $dayofweek . ' (' . $dayofmonth . ' ' . $time . ') - ' . $item->name;
            $options[] = HTMLHelper::_('select.option', $item->id, $option);
        }
        return $options;
    }

}
