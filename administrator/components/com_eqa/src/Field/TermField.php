<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;

class TermField extends ListField
{
    protected const TERM_FIRST=1;
    protected const TERM_LAST=3;
    protected $type = 'term';

    /**
     * Method to get a list of options for a list input.
     *
     * @return	array		An array of JHtml options.
     *
     * @since   1.0
     */
    protected function getOptions()
    {
        $options = parent::getOptions();
        for($term = self::TERM_FIRST; $term<=self::TERM_LAST; $term++)
            $options[] = HTMLHelper::_('select.option',$term,$term);
        return $options;
    }

}
