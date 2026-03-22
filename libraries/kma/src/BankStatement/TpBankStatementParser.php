<?php

namespace Kma\Library\Kma\BankStatement;

defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\IOHelper;

/**
 * Parser sao kê ngân hàng Tiên Phong (TP Bank).
 *
 * Cấu trúc file Excel xuất từ TP Bank Online:
 *   - Các row đầu  : thông tin tài khoản / tiêu đề (bỏ qua đến khi gặp header)
 *   - Row header   : chứa "Số tiền" hoặc tương tự (bỏ qua)
 *   - Row data     : mỗi giao dịch một dòng
 *   - Row cuối     : tổng kết (nhận biết qua cột ngày rỗng hoặc text "Tổng")
 *
 * Cột (0-index, dựa trên cấu trúc thực tế file TP Bank):
 *   0 = Ngày giao dịch
 *   1 = Mã giao dịch / Số bút toán
 *   2 = Nội dung
 *   3 = Phát sinh nợ  (Debit)
 *   4 = Phát sinh có  (Credit)
 *   5 = Số dư
 *
 * @since 2.1.0
 */
class TpBankStatementParser implements BankStatementParserInterface
{
	// Cột (0-indexed) trong file Excel TP Bank
	private const int COL_DATE        = 0;
	private const int COL_DESCRIPTION = 4;
	private const int COL_CREDIT      = 3;

	// Số dòng header bỏ qua từ đầu file
	private const int HEADER_ROWS = 6;

    /**
     * {@inheritDoc}
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception('Không tìm thấy file đã upload: ' . basename($filePath));
        }

        $spreadsheet = IOHelper::loadSpreadsheet($filePath);
        $data        = $spreadsheet->getActiveSheet()->toArray('', true, false);

        $transactions = [];

        // Bỏ qua các dòng header
        for ($i = self::HEADER_ROWS, $total = count($data); $i < $total; $i++) {
            $row = $data[$i];

            // Bỏ dòng trống
            if (empty(array_filter($row, static fn($v): bool => $v !== '' && $v !== null))) {
                continue;
            }

            // Bỏ dòng tổng kết cuối file
            $colDate = (string) ($row[self::COL_DATE] ?? '');
            if (str_contains($colDate, 'Tổng') || str_contains($colDate, 'Total')) {
                break;
            }

            // Chỉ lấy giao dịch Credit
            $credit = $row[self::COL_CREDIT] ?? '';
            if ($credit === '' || $credit === null || (float) $credit <= 0) {
                continue;
            }

            $description = trim((string) ($row[self::COL_DESCRIPTION] ?? ''));
            $description = trim(preg_replace('/\s+/', ' ', $description));

            if ($description === '') {
                continue;
            }

            $transactions[] = [
                'date'        => $colDate,
                'credit'      => (float) $credit,
                'description' => $description,
            ];
        }

        return $transactions;
    }

    /**
     * {@inheritDoc}
     */
    public function getBankName(): string
    {
        return 'TP Bank';
    }
}
