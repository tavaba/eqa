<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Reference: https://www.abdulwaheed.pk/en/blog/41-information-technology/44-joomla/335-how-to-create-custom-form-field-for-custom-component-joomla-4.html
 * @since  1.6
 */
class ExamseasonField extends ListField
{
    protected $type = 'examseason';

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
            ->select('id, name')
            ->from('#__eqa_examseasons')
            ->where('completed = 0 AND published>0')
            ->order('id DESC');
        $db->setQuery($query);
        $items = $db->loadObjectList();
        $options = parent::getOptions();
        foreach ($items as $item)
        {
            $options[] = HTMLHelper::_('select.option', $item->id, $item->name);
        }
        return $options;
    }

}
