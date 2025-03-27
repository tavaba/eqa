<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\RoomHelper;

/**
 * Supports an HTML select list of exam season types
 * Reference: https://www.abdulwaheed.pk/en/blog/41-information-technology/44-joomla/335-how-to-create-custom-form-field-for-custom-component-joomla-4.html
 * @since  1.6
 */
class RoomField extends ListField
{
    protected $type = 'room';

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
            array('a.id', 'b.code',   'a.code', 'a.maxcapacity', 'a.capacity', 'a.type'),
            array('id',   'building', 'code',   'maxcapacity',   'capacity',   'type')
        );
        $query = $db->getQuery(true)
            ->select($columns)
            ->from('#__eqa_rooms AS a')
            ->leftJoin('#__eqa_buildings AS b', 'a.building_id = b.id')
            ->where('b.published=1 AND a.published=1')
            ->order('building ASC')
            ->order('code ASC');
        $db->setQuery($query);
        $rooms = $db->loadObjectList();

        $options = parent::getOptions();
        foreach ($rooms as $room)
        {
            $option = $room->building . '-' . $room->code . ' (' . $room->capacity . ', ' . RoomHelper::roomType($room->type) . ')';
            $options[] = HTMLHelper::_('select.option', $room->id, $option);
        }
        return $options;
    }

}
