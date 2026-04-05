<?php

namespace Kma\Library\Kma\BankStatement;

/**
 * Helper tạo thông báo HTML tổng hợp kết quả đối chiếu sao kê ngân hàng.
 *
 * Tách ra khỏi controller để tái sử dụng cho nhiều luồng:
 *   - RegradingsController::importStatement()
 *   - SecondAttemptsController::importStatement()
 *   - AssessmentLearnersController::importStatement()
 *
 * Sử dụng:
 * <code>
 *   $result = new BankStatementImportResult(...);
 *   $html   = BankStatementImportResultHelper::buildMessage($result);
 *   $type   = BankStatementImportResultHelper::getMessageType($result);
 *   $app->enqueueMessage($html, $type);
 * </code>
 *
 * Hoặc với context label tùy chỉnh khi ghi nhận cập nhật:
 * <code>
 *   $html = BankStatementImportResultHelper::buildMessage(
 *       $result,
 *       'đã nộp phí thi lại'        // mô tả hành động khi updated > 0
 *   );
 * </code>
 *
 * @since 2.0.7
 */
class BankStatementImportResultHelper
{
    /**
     * Xây dựng chuỗi HTML tổng hợp kết quả đối chiếu sao kê.
     *
     * Các dòng thông báo được nối bằng `<br>` để hiển thị trong
     * Joomla message queue (enqueueMessage).
     *
     * @param  BankStatementImportResult  $result       Kết quả đối chiếu.
     * @param  string                     $updatedLabel Nhãn mô tả hành động khi có bản ghi được cập nhật.
     *                                                  Mặc định: 'đã nộp phí'.
     *
     * @return string  HTML thông báo.
     * @since  2.0.7
     */
    public static function buildMessage(
        BankStatementImportResult $result,
        string $updatedLabel = 'đã nộp phí'
    ): string {
        $lines = [];

        // ── Kết quả cập nhật ─────────────────────────────────────────────────
        if ($result->updated > 0) {
            $codes   = array_map('htmlspecialchars', $result->updatedCodes);
            $lines[] = sprintf(
                '✅ Đã ghi nhận <b>%d</b> trường hợp %s: %s',
                $result->updated,
                htmlspecialchars($updatedLabel),
                implode(', ', $codes)
            );
        } else {
            $lines[] = 'ℹ️ Không có trường hợp nào được cập nhật.';
        }

        // ── Đã nộp từ trước ──────────────────────────────────────────────────
        if ($result->alreadyPaid > 0) {
            $lines[] = sprintf(
                'ℹ️ <b>%d</b> trường hợp đã nộp phí từ trước (bỏ qua).',
                $result->alreadyPaid
            );
        }

        // ── Không tìm thấy mã ────────────────────────────────────────────────
        if ($result->notFound > 0) {
            $lines[] = sprintf(
                'ℹ️ <b>%d</b> giao dịch không tìm thấy mã nộp tiền tương ứng.',
                $result->notFound
            );
        }

        // ── Sai số tiền ──────────────────────────────────────────────────────
        if (!empty($result->amountMismatch)) {
            $lines[] = sprintf(
                '⚠️ <b>%d</b> trường hợp <b>sai số tiền</b>, chưa cập nhật:',
                count($result->amountMismatch)
            );
            foreach ($result->amountMismatch as $item) {
                $lines[] = sprintf(
                    '&nbsp;&nbsp;• <code>%s</code> (%s) — Cần: <b>%s đ</b>, Thực nhận: <b>%s đ</b>',
                    htmlspecialchars($item['payment_code']),
                    htmlspecialchars($item['learner_code']),
                    number_format((int) $item['expected'], 0, ',', '.'),
                    number_format((int) $item['actual'],   0, ',', '.')
                );
            }
        }

        // ── Mã trùng lặp ─────────────────────────────────────────────────────
        if (!empty($result->duplicate)) {
            $lines[] = sprintf(
                '⚠️ <b>%d</b> mã nộp tiền xuất hiện nhiều lần trong sao kê (cần xử lý thủ công):',
                count($result->duplicate)
            );
            foreach ($result->duplicate as $item) {
                $lines[] = sprintf(
                    '&nbsp;&nbsp;• <code>%s</code> (%s) — %d lần',
                    htmlspecialchars($item['payment_code']),
                    htmlspecialchars($item['learner_code']),
                    (int) $item['count']
                );
            }
        }

        return implode('<br>', $lines);
    }

    /**
     * Xác định loại thông báo Joomla phù hợp với kết quả đối chiếu.
     *
     * Trả về:
     *   - 'success' : có ít nhất 1 bản ghi được cập nhật và không có cảnh báo.
     *   - 'warning' : có cập nhật nhưng đồng thời có cảnh báo; hoặc không có
     *                 cập nhật nào (cần admin kiểm tra thủ công).
     *   - 'error'   : không dùng ở đây (exception đã được throw trước khi đến đây).
     *
     * @param  BankStatementImportResult  $result
     * @return string  'success' | 'warning'
     * @since  2.0.7
     */
    public static function getMessageType(BankStatementImportResult $result): string
    {
        if ($result->updated > 0 && !$result->hasWarnings()) {
            return 'success';
        }
        return 'warning';
    }
}
