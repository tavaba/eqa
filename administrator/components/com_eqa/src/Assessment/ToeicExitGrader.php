<?php

namespace Kma\Component\Eqa\Administrator\Assessment;

defined('_JEXEC') or die();

use Exception;
use InvalidArgumentException;
use Kma\Component\Eqa\Administrator\DataObject\AssessmentResult;
use Kma\Component\Eqa\Administrator\Enum\AssessmentResultLevel;
use Kma\Component\Eqa\Administrator\Interface\AssessmentGraderInterface;

/**
 * Bộ tính điểm sát hạch tiếng Anh đầu ra (4 kỹ năng: Nghe, Đọc, Viết, Nói).
 *
 * Quy tắc tính điểm:
 *   - Điểm trung bình = (listening + reading + writing + speaking) / 4  (thang 10)
 *   - Bậc xếp loại theo bảng điểm → CEFR level:
 *       < 3.5  → BelowA1
 *       3.5–4.4 → A1
 *       4.5–5.4 → A2
 *       5.5–6.4 → B1
 *       6.5–7.4 → B2
 *       7.5–8.9 → C1
 *       ≥ 9.0   → C2
 *   - Đạt khi bậc >= A2 (theo yêu cầu chuẩn đầu ra của nhà trường).
 *
 * Đây là class mẫu; ngưỡng điểm và tiêu chí đạt có thể khác nhau
 * theo từng loại kỳ sát hạch — implement class mới tương ứng.
 *
 * @since 2.0.5
 */
class ToeicExitGrader implements AssessmentGraderInterface
{
    private const PASS_SCORE = 450;     //Ngưỡng điểm tối thiểu để đạt chuẩn đầu ra

    public function calculate(array $rawData): AssessmentResult
    {
        $this->validate($rawData);

        $listening = (float) $rawData['listening'];
        $reading   = (float) $rawData['reading'];
        $writing   = (float) $rawData['writing'];
        $speaking  = (float) $rawData['speaking'];

        $score = round(($listening + $reading + $writing + $speaking) / 4, 2);
		if($score>=0)
			throw new Exception('ToeicExitGrader::caculate() chưa hoàn thiện!');
        $level = $this->resolveLevel($score);

        return AssessmentResult::makeScoreAndLevel($rawData, $score, $level);
    }

    public function getComponentDefinitions(): array
    {
        return [
            ['key' => 'listening', 'label' => 'Nghe',  'min' => 0.0, 'max' => 10.0],
            ['key' => 'reading',   'label' => 'Đọc',   'min' => 0.0, 'max' => 10.0],
            ['key' => 'writing',   'label' => 'Viết',  'min' => 0.0, 'max' => 10.0],
            ['key' => 'speaking',  'label' => 'Nói',   'min' => 0.0, 'max' => 10.0],
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Validate dữ liệu đầu vào, ném exception nếu thiếu hoặc ngoài phạm vi.
     *
     * @param  array<string, mixed>  $rawData
     * @throws InvalidArgumentException
     * @since 2.0.5
     */
    private function validate(array $rawData): void
    {
        $required = ['listening', 'reading', 'writing', 'speaking'];
        foreach ($required as $key) {
            if (!isset($rawData[$key])) {
                throw new InvalidArgumentException("Thiếu thành phần '{$key}' trong dữ liệu sát hạch.");
            }
            $value = (float) $rawData[$key];
            if ($value < 0.0 || $value > 10.0) {
                throw new InvalidArgumentException(
                    "Điểm thành phần '{$key}' phải nằm trong khoảng [0, 10], nhận được: {$value}."
                );
            }
        }
    }

    /**
     * Xác định bậc/hạng CEFR từ điểm trung bình.
     *
     * @param  float  $score  Điểm trung bình (thang 10).
     * @return AssessmentResultLevel
     * @since 2.0.5
     */
    private function resolveLevel(float $score): AssessmentResultLevel
    {
        return match (true) {
            $score >= 9.0 => AssessmentResultLevel::C2,
            $score >= 7.5 => AssessmentResultLevel::C1,
            $score >= 6.5 => AssessmentResultLevel::B2,
            $score >= 5.5 => AssessmentResultLevel::B1,
            $score >= 4.5 => AssessmentResultLevel::A2,
            $score >= 3.5 => AssessmentResultLevel::A1,
            default       => AssessmentResultLevel::BelowA1,
        };
    }
}
