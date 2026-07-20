<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Extension\EqaComponent;
use Kma\Library\Kma\Helper\ComponentHelper;

/**
 * Supports an HTML select list of education degrees
 * Reference: https://www.abdulwaheed.pk/en/blog/41-information-technology/44-joomla/335-how-to-create-custom-form-field-for-custom-component-joomla-4.html
 * @since  1.6
 */
class BuildingField extends ListField
{
    protected $type = 'building';

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
		$columns = [
			$db->qn('b.id',     'id'),
			$db->qn('b.code',   'code'),
			$db->qn('c.code',   'campusCode'),
		];
        $query = $db->getQuery(true)
            ->select($columns)
            ->from('#__eqa_buildings AS b')
	        ->leftJoin('#__eqa_campuses AS c ON c.id = b.campus_id')
            ->where('b.published = 1')
            ->order('b.code');

	    /**
	     * Chỉ liệt kê tòa nhà thuộc các cơ sở người dùng được phép (2.1.6)
	     * @var EqaComponent $component
	     */
		$component = ComponentHelper::getComponent();
	    $campusService = $component->getCampusService();
	    $activeId   = $campusService->getActiveCampusId();
	    $allowedIds = $activeId > 0 ? [$activeId] : [];
	    $query->where(
		    $db->quoteName('b.campus_id') . ' IN (' . implode(',', array_map('intval', $allowedIds)) . ')'
	    );

		//Do the query
	    $db->setQuery($query);
        $buildings = $db->loadObjectList();
        $options = parent::getOptions();
        foreach ($buildings as $building)
        {
			$text = sprintf('%s (%s)', $building->code, $building->campusCode);
            $options[] = HTMLHelper::_('select.option', $building->id, $text);
        }
        return $options;
    }

}
