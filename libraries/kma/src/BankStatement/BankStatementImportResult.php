<?php

namespace Kma\Library\Kma\BankStatement;

/**
 * Kết quả đối chiếu sao kê ngân hàng với danh sách yêu cầu thanh toán.
 *
 * Object này được trả về bởi các method importBankStatement() trong:
 *   - RegradingsModel          (phí phúc khảo)
 *   - SecondAttemptsModel      (phí thi lại)
 *   - AssessmentLearnersModel  (phí sát hạch)
 *
 * Cấu trúc dữ liệu của $amountMismatch — mỗi phần tử là array:
 *   [
 *     'payment_code' => string,   // Mã nộp tiền
 *     'learner_code' => string,   // Mã người học
 *     'expected'     => int,      // Số tiền cần nộp (theo DB)
 *     'actual'       => int,      // Số tiền thực nhận (trong sao kê)
 *   ]
 *
 * Cấu trúc dữ liệu của $duplicate — mỗi phần tử là array:
 *   [
 *     'payment_code' => string,   // Mã nộp tiền bị trùng
 *     'learner_code' => string,   // Mã người học
 *     'count'        => int,      // Số lần xuất hiện trong sao kê
 *   ]
 *
 * @since 2.0.7
 */
class BankStatementImportResult
{
    /**
     * Số bản ghi được cập nhật thành công (payment_completed = 1).
     *
     * @var int
     */
    public int $updated = 0;

    /**
     * Số bản ghi đã nộp phí từ trước (bỏ qua, không cập nhật lại).
     *
     * @var int
     */
    public int $alreadyPaid = 0;

    /**
     * Số giao dịch trong sao kê không tìm thấy payment_code tương ứng trong DB.
     *
     * @var int
     */
    public int $notFound = 0;

    /**
     * Danh sách các trường hợp số tiền không khớp (chưa cập nhật).
     *
     * @var array<int, array{payment_code: string, learner_code: string, expected: int, actual: int}>
     */
    public array $amountMismatch = [];

    /**
     * Danh sách các payment_code xuất hiện nhiều hơn một lần trong sao kê
     * (không thể tự động đối soát, cần xử lý thủ công).
     *
     * @var array<int, array{payment_code: string, learner_code: string, count: int}>
     */
    public array $duplicate = [];

    /**
     * Danh sách mã người học (learner_code) đã được cập nhật thành công,
     * dùng để hiển thị trong thông báo kết quả.
     *
     * @var string[]
     */
    public array $updatedCodes = [];

    /**
     * Cờ cho biết có bất kỳ bản ghi nào được cập nhật không.
     * Shorthand cho ($this->updated > 0).
     *
     * @return bool
     */
    public function hasUpdates(): bool
    {
        return $this->updated > 0;
    }

    /**
     * Cờ cho biết có cảnh báo nào cần chú ý không
     * (sai số tiền hoặc mã trùng lặp).
     *
     * @return bool
     */
    public function hasWarnings(): bool
    {
        return !empty($this->amountMismatch) || !empty($this->duplicate);
    }

    /**
     * Tạo object từ mảng kết quả legacy (tương thích ngược với code cũ trả về array).
     *
     * @param  array{
     *     updated:        int,
     *     alreadyPaid:    int,
     *     notFound:       int,
     *     amountMismatch: array,
     *     duplicate:      array,
     *     updatedCodes:   string[]
     * } $data
     *
     * @return self
     * @since 2.0.7
     */
    public static function fromArray(array $data): self
    {
        $obj               = new self();
        $obj->updated       = (int)   ($data['updated']       ?? 0);
        $obj->alreadyPaid   = (int)   ($data['alreadyPaid']   ?? 0);
        $obj->notFound      = (int)   ($data['notFound']       ?? 0);
        $obj->amountMismatch = (array) ($data['amountMismatch'] ?? []);
        $obj->duplicate      = (array) ($data['duplicate']      ?? []);
        $obj->updatedCodes   = (array) ($data['updatedCodes']   ?? []);
        return $obj;
    }
}
