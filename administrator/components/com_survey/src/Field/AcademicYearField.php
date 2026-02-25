<?php
namespace Kma\Component\Survey\Administrator\Field;
defined('_JEXEC') or die();
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Survey\Administrator\Helper\LogHelper;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;

class AcademicYearField extends ListField
{
    protected $type = 'academicyear';
    protected $deltaBefore = 10; // Số năm trước hiện tại để hiển thị trong danh sách
    protected $deltaAfter = 1;  // Số năm sau hiện tại để hiển thị trong danh sách
    protected function getOptions(): array
    {
        $options = [];
        $options[] = HTMLHelper::_('select.option', '', '- Năm học -');


        $currentYear = intval(date('Y'));
        $startYear = $currentYear - $this->deltaBefore;
        $endYear   = $currentYear + $this->deltaAfter;
        for ($year=$endYear; $year>=$startYear; $year--)
        {
            $code = $year;
            $text = DatetimeHelper::decodeAcademicYear($code);
            $options[] = HTMLHelper::_('select.option', $code, $text);
        }
        return $options;
    }

}