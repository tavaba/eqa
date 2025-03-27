<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;

/**
 * Reference: https://www.abdulwaheed.pk/en/blog/41-information-technology/44-joomla/335-how-to-create-custom-form-field-for-custom-component-joomla-4.html
 * @since  1.6
 */
class ExamtimeField extends ListField
{
    protected $type = 'examtime';
    protected const START_HOUR=07;
    protected const START_MIN=00;
    protected const END_HOUR=21;
    protected const END_MIN=00;
    protected const STEP=15;        //must be devided by 60

    static protected function toString(int $hour, int $min){
        return sprintf("%02d:%02d",$hour, $min);
    }

    static protected function next(int &$hour, int &$min){
        $min += self::STEP;
        if($min >= 60)
        {
            $min = 0;
            $hour++;
        }
    }

    static protected function over(int $hour, int $min){
        if ($hour>self::END_HOUR)
            return true;
        if ($hour<self::END_HOUR)
            return false;
        //$hour === END_HOUR
        if($min>self::END_MIN)
            return true;
        return false;
    }

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
        $hour = self::START_HOUR;
        $min = self::START_MIN;
        while(!self::over($hour, $min))
        {
            $stamp = self::toString($hour,$min);
            $options[] = HTMLHelper::_('select.option', $stamp, $stamp);
            self::next($hour,$min);
        }
        return $options;
    }

}
