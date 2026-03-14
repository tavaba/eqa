<?php

namespace Kma\Component\Eqa\Administrator\DataObject;

defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Enum\AssessmentResultLevel;

/**
 * Value Object chứa kết quả tính toán của một bài sát hạch.
 *
 * Đối tượng này được trả về bởi AssessmentGraderInterface::calculate().
 * Tùy theo AssessmentResultType của kỳ sát hạch, các thuộc tính
 * khác nhau sẽ được sử dụng:
 *
 *   - PassFail      → $passed
 *   - Score         → $score, $passed
 *   - Level         → $level, $passed
 *   - ScoreAndLevel → $score, $level, $passed
 *
 * Trường $rawData lưu lại dữ liệu thô (điểm thành phần) để serialize
 * xuống cột `raw_result` (JSON) trong bảng `#__eqa_assessment_learner`.
 *
 * @since 2.0.5
 */
final class AssessmentResult
{
    /**
     * @param array<string, mixed>          $rawData  Điểm/dữ liệu thành phần gốc.
     * @param float|null                    $score    Điểm quy đổi (null nếu không dùng).
     * @param AssessmentResultLevel|null    $level    Bậc/hạng quy đổi (null nếu không dùng).
     * @param bool|null                     $passed   Kết luận đạt/không đạt (null nếu chưa xác định).
     * @param string|null                   $note     Ghi chú thêm (nếu có).
     * @since 2.0.5
     */
    public function __construct(
        public readonly array $rawData,
        public readonly ?float $score,
        public readonly ?AssessmentResultLevel $level,
        public readonly ?bool $passed,
        public readonly ?string $note = null,
    ) {
    }

    /**
     * Tạo đối tượng kết quả kiểu PassFail.
     *
     * @param  array<string, mixed>  $rawData
     * @param  bool                  $passed
     * @param  string|null           $note
     * @return self
     * @since 2.0.5
     */
    public static function makePassFail(array $rawData, bool $passed, ?string $note = null): self
    {
        return new self(
            rawData: $rawData,
            score:   null,
            level:   null,
            passed:  $passed,
            note:    $note,
        );
    }

    /**
     * Tạo đối tượng kết quả kiểu Score.
     *
     * @param  array<string, mixed>  $rawData
     * @param  float                 $score
     * @param  bool                  $passed    Xác định từ ngưỡng đạt của từng kỳ sát hạch.
     * @param  string|null           $note
     * @return self
     * @since 2.0.5
     */
    public static function makeScore(array $rawData, float $score, bool $passed, ?string $note = null): self
    {
        return new self(
            rawData: $rawData,
            score:   $score,
            level:   null,
            passed:  $passed,
            note:    $note,
        );
    }

    /**
     * Tạo đối tượng kết quả kiểu Level.
     *
     * @param  array<string, mixed>   $rawData
     * @param  AssessmentResultLevel  $level
     * @param  string|null            $note
     * @return self
     * @since 2.0.5
     */
    public static function makeLevel(array $rawData, AssessmentResultLevel $level, ?string $note = null): self
    {
        return new self(
            rawData: $rawData,
            score:   null,
            level:   $level,
            passed:  $level->isPassed(),
            note:    $note,
        );
    }

    /**
     * Tạo đối tượng kết quả kiểu ScoreAndLevel.
     *
     * @param  array<string, mixed>   $rawData
     * @param  float                  $score
     * @param  AssessmentResultLevel  $level
     * @param  string|null            $note
     * @return self
     * @since 2.0.5
     */
    public static function makeScoreAndLevel(
        array $rawData,
        float $score,
        AssessmentResultLevel $level,
        ?string $note = null,
    ): self {
        return new self(
            rawData: $rawData,
            score:   $score,
            level:   $level,
            passed:  $level->isPassed(),
            note:    $note,
        );
    }
}
