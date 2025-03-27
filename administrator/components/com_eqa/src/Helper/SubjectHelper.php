<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;

abstract class SubjectHelper{
    public const TEST_TYPE_UNKNOWN=0;
    public const TEST_TYPE_PAPER = 10;
    public const TEST_TYPE_PROJECT = 11;
    public const TEST_TYPE_THESIS = 12;
    public const TEST_TYPE_PRACTICE=13;
    public const TEST_TYPE_DIALOG = 14;
    public const TEST_TYPE_MACHINE_OBJECTIVE = 20;
    public const TEST_TYPE_MACHINE_HYBRID = 21;
    public const TEST_TYPE_COMBO_OBJECTIVE_PRACTICE = 30;

    static public function TestType(int $testType): string|null
    {
        return match ($testType) {
            self::TEST_TYPE_UNKNOWN => Text::_('COM_EQA_GENERAL_TEST_TYPE_UNKNOWN'),
            self::TEST_TYPE_PAPER => Text::_('COM_EQA_GENERAL_TEST_TYPE_PAPER'),
            self::TEST_TYPE_PROJECT => Text::_('COM_EQA_GENERAL_TEST_TYPE_PROJECT'),
            self::TEST_TYPE_THESIS => Text::_('COM_EQA_GENERAL_TEST_TYPE_THESIS'),
            self::TEST_TYPE_PRACTICE => Text::_('COM_EQA_GENERAL_TEST_TYPE_PRACTICE'),
            self::TEST_TYPE_DIALOG => Text::_('COM_EQA_GENERAL_TEST_TYPE_DIALOG'),
            self::TEST_TYPE_MACHINE_OBJECTIVE => Text::_('COM_EQA_GENERAL_TEST_TYPE_MACHINE_OBJECTIVE'),
            self::TEST_TYPE_MACHINE_HYBRID => Text::_('COM_EQA_GENERAL_TEST_TYPE_MACHINE_HYBRID'),
            self::TEST_TYPE_COMBO_OBJECTIVE_PRACTICE => Text::_('COM_EQA_GENERAL_TEST_TYPE_COMBO_OBJECTIVE_PRACTICE'),
            default => null,
        };
    }
    static public function TestTypeAbbr(int $testType): string|null
    {
        return match ($testType) {
            self::TEST_TYPE_UNKNOWN => Text::_('COM_EQA_GENERAL_TEST_TYPE_UNKNOWN_ABBR'),
            self::TEST_TYPE_PAPER => Text::_('COM_EQA_GENERAL_TEST_TYPE_PAPER_ABBR'),
            self::TEST_TYPE_PROJECT => Text::_('COM_EQA_GENERAL_TEST_TYPE_PROJECT_ABBR'),
            self::TEST_TYPE_THESIS => Text::_('COM_EQA_GENERAL_TEST_TYPE_THESIS_ABBR'),
            self::TEST_TYPE_PRACTICE => Text::_('COM_EQA_GENERAL_TEST_TYPE_PRACTICE_ABBR'),
            self::TEST_TYPE_DIALOG => Text::_('COM_EQA_GENERAL_TEST_TYPE_DIALOG_ABBR'),
            self::TEST_TYPE_MACHINE_OBJECTIVE => Text::_('COM_EQA_GENERAL_TEST_TYPE_MACHINE_OBJECTIVE_ABBR'),
            self::TEST_TYPE_MACHINE_HYBRID => Text::_('COM_EQA_GENERAL_TEST_TYPE_MACHINE_HYBRID_ABBR'),
            self::TEST_TYPE_COMBO_OBJECTIVE_PRACTICE => Text::_('COM_EQA_GENERAL_TEST_TYPE_COMBO_OBJECTIVE_PRACTICE_ABBR'),
            default => null,
        };
    }
    static public function TestTypes(): array
    {
        $testtypes = array();
        $testtypes[self::TEST_TYPE_UNKNOWN] = self::TestType(self::TEST_TYPE_UNKNOWN);
        $testtypes[self::TEST_TYPE_PAPER] = self::TestType(self::TEST_TYPE_PAPER);
        $testtypes[self::TEST_TYPE_PROJECT] = self::TestType(self::TEST_TYPE_PROJECT);
        $testtypes[self::TEST_TYPE_THESIS] = self::TestType(self::TEST_TYPE_THESIS);
        $testtypes[self::TEST_TYPE_PRACTICE] = self::TestType(self::TEST_TYPE_PRACTICE);
        $testtypes[self::TEST_TYPE_DIALOG] = self::TestType(self::TEST_TYPE_DIALOG);
        $testtypes[self::TEST_TYPE_MACHINE_OBJECTIVE] = self::TestType(self::TEST_TYPE_MACHINE_OBJECTIVE);
        $testtypes[self::TEST_TYPE_MACHINE_HYBRID] = self::TestType(self::TEST_TYPE_MACHINE_HYBRID);
        $testtypes[self::TEST_TYPE_COMBO_OBJECTIVE_PRACTICE] = self::TestType(self::TEST_TYPE_COMBO_OBJECTIVE_PRACTICE);
        return $testtypes;
    }
}

