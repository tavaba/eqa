<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
class PassyearField extends ListField
{
    protected $type = 'passyear';
    protected static int $LENGTH = 50;

    /**
     * Method to get a list of options for a list input.
     *
     * @return	array		An array of JHtml options.
     *
     * @since   1.0
     */
    protected function getOptions()
    {
        $current_year = date('Y');
        $options = parent::getOptions();

        //Không xác định năm
        $year=0;
        $text= Text::_('COM_EQA_GENERAL_UNDEFINED');
        $options[] = HTMLHelper::_('select.option', $year, $text);

        //Danh sách các năm theo thứ tự giảm dần
        for($i=0; $i<self::$LENGTH; $i++){
            $year=$current_year-$i;
            $options[] = HTMLHelper::_('select.option', $year, $year);
        }
        return $options;
    }

}
