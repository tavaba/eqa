<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
class CohortField extends ListField
{
    protected $type = 'cohort';

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
        $query = $db->getQuery(true)
            ->from('#__eqa_cohorts')
            ->select('id, code, name')
            ->where('published = 1')
            ->order('id DESC');
        $db->setQuery($query);
        $cohorts = $db->loadObjectList();
        $options = parent::getOptions();
        foreach ($cohorts as $cohort)
        {
			$value = $cohort->id;
			$text = $cohort->code . ' - ' . htmlspecialchars($cohort->name);
            $options[] = HTMLHelper::_('select.option', $value, $text);
        }
        return $options;
    }

}
