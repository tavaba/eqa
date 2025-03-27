<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;

abstract class ProgramHelper{
    protected const FORMAT_ORIGINAL = 10;   //Đầy đủ
    protected const FORMAT_TRANSFER = 11;   //Liên thông
    protected const FORMAT_SECOND = 20;     //Văn bằng 2
    protected const FORMAT_SHORT = 30;      //Ngắn hạn
    protected const FORMAT_OTHER = 99;      //Khác

    protected const APPROACH_OFFICIAL = 10;     //Chính quy
    protected const APPROACH_INSERVICE = 20;    //Vừa làm vừa học
    protected const APPROACH_DISTANCE=30;       //Từ xa

    /**
     * Hàm này dịch từ mã loại chương trình (lưu trong CSDL về khóa học, bảng #__eqa_programs) thành tên loại hình
     * @param int $format   Hằng số quy ước cho cấp học (định nghĩa theo danh mục cấp IV của Bộ GD&ĐT)
     * @return string|null  Tên cấp học (dịch từ tập tin language) tương ứng với hằng số
     * @since 1.0
     */
    static public function format(int $format): string|null
    {
        return match ($format) {
            self::FORMAT_ORIGINAL => Text::_('COM_EQA_CONST_PROGRAM_FORMAT_ORIGINAL'),
            self::FORMAT_TRANSFER => Text::_('COM_EQA_CONST_PROGRAM_FORMATTRANSFER'),
            self::FORMAT_SECOND => Text::_('COM_EQA_CONST_PROGRAM_FORMAT_SECOND'),
            self::FORMAT_SHORT => Text::_('COM_EQA_CONST_PROGRAM_FORMAT_SHORT'),
            self::FORMAT_OTHER => Text::_('COM_EQA_CONST_PROGRAM_FORMAT_OTHER'),
            default => null,
        };
    }

    /**
     * Hàm này trả về mảng thông tin các loại hình chương trình đào tạo,
     * trong đó $key là mã loại hình chương trình đào tạo được lưu trong CSDL
     * ở bảng #__eqa_programs, còn $value là tên tương ứng được dịch từ tập tin ngôn ngữ.
     * @return array    Mỗi phần tử $key=>$value ứng với $key là mã, $value là tên loại hình chương trình đào tạo
     * @since 1.0
     */
    static public function formats(): array
    {
        $formats = array();
        $formats[self::FORMAT_ORIGINAL] = self::format(self::FORMAT_ORIGINAL);
        $formats[self::FORMAT_TRANSFER] = self::format(self::FORMAT_TRANSFER);
        $formats[self::FORMAT_SECOND] = self::format(self::FORMAT_SECOND);
        $formats[self::FORMAT_SHORT] = self::format(self::FORMAT_SHORT);
        $formats[self::FORMAT_OTHER] = self::format(self::FORMAT_OTHER);
        return $formats;
    }

    /**
     * Hàm này dịch từ mã loại chương trình (lưu trong CSDL về khóa học, bảng #__eqa_programs) thành tên loại hình
     * @param int $approach   Hằng số quy ước cho cấp học (định nghĩa theo danh mục cấp IV của Bộ GD&ĐT)
     * @return string|null  Tên cấp học (dịch từ tập tin language) tương ứng với hằng số
     * @since 1.0
     */
    static public function approach(int $approach): string|null
    {
        return match ($approach) {
            self::APPROACH_OFFICIAL => Text::_('COM_EQA_CONST_PROGRAM_APPROACH_OFFICIAL'),
            self::APPROACH_INSERVICE => Text::_('COM_EQA_CONST_PROGRAM_APPROACH_INSERVICE'),
            self::APPROACH_DISTANCE => Text::_('COM_EQA_CONST_PROGRAM_APPROACH_DISTANCE'),
            default => null,
        };
    }

    /**
     * Hàm này trả về mảng thông tin các hình thức tổ chức chương trình đào tạo,
     * trong đó $key là mã hình thức tổ chức chương trình đào tạo được lưu trong CSDL
     * ở bảng #__eqa_programs, còn $value là tên tương ứng được dịch từ tập tin ngôn ngữ.
     * @return array    Mỗi phần tử $key=>$value ứng với $key là mã, $value là tên hình thức tổ chức CTĐT
     * @since 1.0
     */
    static public function approachs(): array
    {
        $approachs = array();
        $approachs[self::APPROACH_OFFICIAL] = self::approach(self::APPROACH_OFFICIAL);
        $approachs[self::APPROACH_INSERVICE] = self::approach(self::APPROACH_INSERVICE);
        $approachs[self::APPROACH_DISTANCE] = self::approach(self::APPROACH_DISTANCE);
        return $approachs;
    }
}

