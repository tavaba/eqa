<?php

namespace Kma\Component\Eqa\Administrator\Enum;

use Kma\Library\Kma\Enum\EnumHelper;

defined('_JEXEC') or die();

/**
 * Kiểu kết quả của bài sát hạch.
 *
 * Xác định cách thức biểu diễn kết quả sát hạch của thí sinh.
 * Giá trị int được lưu vào cột `result_type` của bảng `#__eqa_assessments`.
 *
 * Tương ứng với cách AssessmentGraderInterface::calculate() trả kết quả:
 *   - PassFail  → AssessmentResult::$passed (bool)
 *   - Score     → AssessmentResult::$score  (float)
 *   - Level     → AssessmentResult::$level  (AssessmentResultLevel)
 *   - ScoreAndLevel → cả $score lẫn $level
 *
 * @since 2.0.5
 */
enum AssessmentResultType: int
{
    use EnumHelper;

    case PassFail = 1;          // Kết quả là Đạt/Không đạt
	case Score = 2;             // Kết quả là điểm số (thang 10 hoặc tùy quy định)
	case Level = 3;             // Kết quả là bậc/hạng (A1, A2, B1...)
	case ScoreAndLevel = 21;    // Kết quả bao gồm cả điểm số và bậc/hạng

    /**
     * Trả về nhãn hiển thị tiếng Việt.
     *
     * @return string
     * @since 2.0.5
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::PassFail      => 'Đạt/Không đạt',
            self::Score         => 'Điểm số',
            self::Level         => 'Thang bậc',
            self::ScoreAndLevel => 'Điểm số và Thang bậc',
        };
    }

}
