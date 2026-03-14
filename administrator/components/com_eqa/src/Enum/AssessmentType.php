<?php

namespace Kma\Component\Eqa\Administrator\Enum;

defined('_JEXEC') or die();

/**
 * Loại bài sát hạch.
 *
 * Mỗi loại tương ứng với một nội dung/mục tiêu sát hạch cụ thể.
 * Giá trị int được lưu vào cột `type` của bảng `#__eqa_assessments`.
 *
 * @since 2.0.5
 */
enum AssessmentType: int
{
    use EnumHelper;

	case EnglishEntry = 10;             //Sát hạch tiếng Anh đầu vào
	case EnglishExit = 20;              //Sát hạch tiếng Anh đầu ra
	case InformationTechnology = 30;    //Sát hạch tin học
	case Other = 99;                    //Loại khác (dự phòng cho các loại sát hạch mới)

    /**
     * Trả về nhãn hiển thị tiếng Việt của loại sát hạch.
     *
     * @return string
     * @since 2.0.5
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::EnglishEntry          => 'Sát hạch tiếng Anh đầu vào',
            self::EnglishExit           => 'Sát hạch tiếng Anh đầu ra',
            self::InformationTechnology => 'Sát hạch tin học',
            self::Other                 => 'Khác',
        };
    }

}
