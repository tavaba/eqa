<?php

namespace Kma\Library\Kma\BankStatement;

defined('_JEXEC') or die();

/**
 * Interface cho các parser sao kê ngân hàng.
 *
 * Mỗi ngân hàng có cấu trúc file Excel khác nhau.
 * Mỗi implementation parse file theo đặc tả riêng của ngân hàng đó
 * và trả về danh sách giao dịch theo định dạng chuẩn.
 *
 * Để hỗ trợ thêm một ngân hàng mới, chỉ cần:
 *   1. Tạo class mới implement interface này.
 *   2. Đăng ký vào BankStatementHelper::getParser().
 *
 * @since 2.0.5
 */
interface BankStatementParserInterface
{
    /**
     * Parse file Excel sao kê ngân hàng, trả về danh sách giao dịch Credit.
     *
     * Chỉ lấy các giao dịch có tiền vào (Credit > 0).
     * Bỏ qua các dòng tiêu đề, dòng tổng kết, dòng Debit.
     *
     * @param  string  $filePath  Đường dẫn tuyệt đối đến file .xlsx.
     * @return array<int, array{date: string, credit: float, description: string}>
     *         Danh sách giao dịch Credit, mỗi phần tử gồm:
     *           - date        : ngày giao dịch (chuỗi, format tùy ngân hàng)
     *           - credit      : số tiền vào (float, VNĐ)
     *           - description : nội dung chuyển khoản (đã normalize whitespace)
     * @throws \Exception  Nếu file không tồn tại hoặc không đọc được.
     * @since 2.0.5
     */
    public function parse(string $filePath): array;

    /**
     * Trả về tên định danh của ngân hàng (dùng để log/thông báo).
     *
     * Ví dụ: 'TP Bank', 'MB Bank', 'Vietcombank'.
     *
     * @return string
     * @since 2.0.5
     */
    public function getBankName(): string;
}
