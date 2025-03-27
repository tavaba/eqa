<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;

abstract class UnitHelper{
    public const UNIT_TYPE_EDUCATION = 1;
    public const UNIT_TYPE_ADMINISTRATIVE = 2;
    public const UNIT_TYPE_RESEARCH = 3;
    public const UNIT_TYPE_OTHER = 0;

    /**
     * Hàm này dịch từ mã NHÓM ĐƠN VỊ (lưu trong CSDL về khóa học, bảng #__eqa_units) thành tên nhóm đơn vị
     * @param int $unit_type   Hằng số quy ước cho NHÓM ĐƠN VỊ
     * @return string  Tên nhóm đơn vị
     * @since 1.0
     */
    static public function UnitType(int $unit_type): string
    {
        return match ($unit_type) {
            self::UNIT_TYPE_EDUCATION => Text::_('COM_EQA_GENERAL_UNIT_TYPE_EDUCATION'),
            self::UNIT_TYPE_ADMINISTRATIVE => Text::_('COM_EQA_GENERAL_UNIT_TYPE_ADMINISTRATIVE'),
            self::UNIT_TYPE_RESEARCH => Text::_('COM_EQA_GENERAL_UNIT_TYPE_RESEARCH'),
            self::UNIT_TYPE_OTHER => Text::_('COM_EQA_GENERAL_UNIT_TYPE_OTHER')
        };
    }

    /**
     * Hàm này trả về mảng thông tin các NHÓM ĐƠN VỊ, trong đó $key là mã nhóm được lưu trong CSDL
     * ở bảng #__eqa_units, còn $value là tên NHÓM ĐƠN VỊ được dịch từ tập tin ngôn ngữ.
     * @return array    Mỗi phần tử $key=>$value ứng với $key là mã nhóm, $value là tên nhóm
     * @since 1.0
     */
    static public function UnitTypes(): array
    {
        $degrees = array();
        $degrees[self::UNIT_TYPE_EDUCATION] = self::UnitType(self::UNIT_TYPE_EDUCATION);
        $degrees[self::UNIT_TYPE_ADMINISTRATIVE] = self::UnitType(self::UNIT_TYPE_ADMINISTRATIVE);
        $degrees[self::UNIT_TYPE_RESEARCH] = self::UnitType(self::UNIT_TYPE_RESEARCH);
        $degrees[self::UNIT_TYPE_OTHER] = self::UnitType(self::UNIT_TYPE_OTHER);
        return $degrees;
    }
}

