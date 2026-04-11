<?php

namespace Kma\Component\Eqa\Administrator\Enum;

use Kma\Library\Kma\Enum\EnumHelper;

defined('_JEXEC') or die();

/**
 * Bậc/hạng kết quả sát hạch (theo khung tham chiếu châu Âu CEFR và tương đương).
 *
 * Giá trị int được lưu vào cột `level` của bảng `#__eqa_assessment_learner`.
 * Thứ tự giá trị int thể hiện thứ bậc tăng dần (càng lớn càng cao).
 *
 * @since 2.0.5
 */
enum AssessmentResultLevel: int
{
    use EnumHelper;

    case BelowA1 = 0;           // Chưa đạt / Dưới chuẩn
    case A1 = 10;               // Bậc A1 (Sơ cấp)
	case A2 = 11;               // Bậc A2 (Cơ bản)
	case B1 = 12;               // Bậc B1 (Trung cấp)
	case B2 = 13;               // Bậc B2 (Trung-cao cấp)
	case C1 = 14;               // Bậc C1 (Cao cấp)
	case C2 = 15;               // Bậc C2 (Thành thạo)

    /**
     * Trả về nhãn hiển thị.
     *
     * @return string
     * @since 2.0.5
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::BelowA1 => 'Chưa đạt',
            self::A1      => 'A1',
            self::A2      => 'A2',
            self::B1      => 'B1',
            self::B2      => 'B2',
            self::C1      => 'C1',
            self::C2      => 'C2',
        };
    }

    /**
     * Trả về true nếu bậc này được coi là "đạt" (từ A1 trở lên).
     *
     * @return bool
     * @since 2.0.5
     */
    public function isPassed(): bool
    {
        return $this->value >= self::A1->value;
    }
}
