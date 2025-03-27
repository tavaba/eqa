<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Matrix\Exception;

abstract class CourseHelper{
    public const DEGREE_BACHELOR_OR_ENGINEER = 7;
    public const DEGREE_MASTER = 8;
    public const DEGREE_DOCTOR = 9;
    public const DEGREE_MULTI = 98;
    public const DEGREE_UNDEFINED = 99;
    protected static array $coures;

    /**
     * Hàm này dịch từ mã cấp học (lưu trong CSDL về khóa học, bảng #__eqa_courses) thành tên cấp học
     * @param int $degree   Hằng số quy ước cho cấp học (định nghĩa theo danh mục cấp IV của Bộ GD&ĐT)
     * @return string|null  Tên cấp học (dịch từ tập tin language) tương ứng với hằng số
     * @since 1.0
     */
    static public function Degree(int $degree): string|null
    {
        return match ($degree) {
            self::DEGREE_UNDEFINED => Text::_('COM_EQA_CONST_COURSE_DEGREE_UNDEFINED'),
            self::DEGREE_BACHELOR_OR_ENGINEER => Text::_('COM_EQA_CONST_COURSE_DEGREE_BACHELOR_AND_ENGINEER'),
            self::DEGREE_MASTER => Text::_('COM_EQA_CONST_COURSE_DEGREE_MASTER'),
            self::DEGREE_DOCTOR => Text::_('COM_EQA_CONST_COURSE_DEGREE_DOCTOR'),
            self::DEGREE_MULTI => Text::_('COM_EQA_CONST_COURSE_DEGREE_MULTI'),
            default => null,
        };
    }

    /**
     * Hàm này trả về mảng thông tin các bậc học trong đó $key là mã bậc học được lưu trong CSDL
     * ở bảng #__eqa_courses, còn $value là tên bậc học được dịch từ tập tin ngôn ngữ.
     * @return array    Mỗi phần tử $key=>$value ứng với $key là mã bậc học, $value là tên bậc học
     * @since 1.0
     */
    static public function Degrees(): array
    {
        $degrees = array();
        $degrees[self::DEGREE_BACHELOR_OR_ENGINEER] = self::Degree(self::DEGREE_BACHELOR_OR_ENGINEER);
        $degrees[self::DEGREE_MASTER] = self::Degree(self::DEGREE_MASTER);
        $degrees[self::DEGREE_DOCTOR] = self::Degree(self::DEGREE_DOCTOR);
        $degrees[self::DEGREE_MULTI] = self::Degree(self::DEGREE_MULTI);
        $degrees[self::DEGREE_UNDEFINED] = self::Degree(self::DEGREE_UNDEFINED);
        return $degrees;
    }

    /**
     * Return course code corresponding to the $courseId.
     * @param int $courseId
     * @return string
     * @throws Exception
     * @since 1.0
     */
    static public function getCourseCode(int $courseId):string{
        //Load danh sách nếu chưa có
        if(empty(self::$coures)){
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('id,code')
                ->from('#__eqa_courses');
            $db->setQuery($query);
            self::$coures = $db->loadAssocList('id','code');
        }

        //Trả kết quả
        return self::$coures[$courseId];
    }
}

