<?php

namespace Kma\Component\Eqa\Administrator\Interface;


use Kma\Component\Eqa\Administrator\DataObject\AssessmentResult;

defined('_JEXEC') or die();

/**
 * Interface chung cho tất cả các bộ tính điểm sát hạch.
 *
 * Mỗi loại kỳ sát hạch (tiếng Anh đầu ra, tin học...) có cách tính điểm
 * riêng. Mỗi cách tính được cài đặt trong một class implement interface này.
 *
 * Ví dụ sử dụng:
 * <code>
 *   $grader = new EnglishExitGrader();
 *   $rawData = ['listening' => 7.5, 'reading' => 6.0, 'writing' => 8.0, 'speaking' => 7.0];
 *   $result  = $grader->calculate($rawData);
 *   // $result->score  → điểm trung bình
 *   // $result->level  → AssessmentResultLevel tương ứng
 *   // $result->passed → true/false
 * </code>
 *
 * @since 2.0.5
 */
interface AssessmentGraderInterface
{
    /**
     * Tính toán kết quả sát hạch từ dữ liệu thô.
     *
     * @param  array<string, mixed>  $rawData  Dữ liệu đầu vào: mảng key-value chứa
     *                                          điểm/kết quả các thành phần thi.
     *                                          Cấu trúc cụ thể do từng implementation quy định.
     * @return AssessmentResult                 Value object chứa đầy đủ kết quả đã tính.
     * @since 2.0.5
     */
    public function calculate(array $rawData): AssessmentResult;

    /**
     * Trả về danh sách các key hợp lệ trong $rawData mà grader này kỳ vọng.
     *
     * Dùng để validate input trước khi gọi calculate(), và để sinh form nhập liệu
     * điểm thành phần trong admin.
     *
     * Mỗi phần tử là một mảng mô tả một thành phần:
     * <code>
     * [
     *   'key'   => 'listening',   // key trong rawData
     *   'label' => 'Nghe',        // nhãn hiển thị
     *   'min'   => 0.0,           // giá trị tối thiểu
     *   'max'   => 10.0,          // giá trị tối đa
     * ]
     * </code>
     *
     * @return array<int, array{key: string, label: string, min: float, max: float}>
     * @since 2.0.5
     */
    public function getComponentDefinitions(): array;
}
