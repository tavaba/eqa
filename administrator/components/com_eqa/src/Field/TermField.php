<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\DatetimeHelper;

class TermField extends ListField
{
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
        $options = [];
	    $options[] = HTMLHelper::_('select.option','','- Học kỳ -');
		foreach (DatetimeHelper::getTerms() as $term=>$label)
			$options[] = HTMLHelper::_('select.option',$term,$label);
		return $options;
    }

}
