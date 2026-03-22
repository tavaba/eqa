<?php

namespace Kma\Library\Kma\BankStatement;

defined('_JEXEC') or die();

use Kma\Library\Kma\Helper\IOHelper;

/**
 * Parser sao kê ngân hàng Quân Đội (MB Bank).
 *
 * Cấu trúc file Excel xuất từ MB Bank:
 *   - Row 1–5  : thông tin tài khoản / tiêu đề (bỏ qua)
 *   - Row 6    : header cột (bỏ qua)
 *   - Row 7+   : data; dòng cuối chứa "Tổng phát sinh" (dừng)
 *
 * Cột (0-index):
 *   0 = Ngày giao dịch
 *   1 = Số bút toán
 *   2 = Phát sinh nợ  (Debit)
 *   3 = Phát sinh có  (Credit)
 *   4 = Nội dung chuyển khoản
 *
 * @since 2.1.0
 */
class MbBankStatementParser implements BankStatementParserInterface
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

        for ($i = self::HEADER_ROWS, $total = count($data); $i < $total; $i++) {
            $row = $data[$i];

            if (empty(array_filter($row, static fn($v): bool => $v !== '' && $v !== null))) {
                continue;
            }

            $colDate = (string) ($row[self::COL_DATE] ?? '');
            if (str_contains($colDate, 'Tổng') || str_contains($colDate, 'Total')) {
                break;
            }

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
        return 'MB Bank';
    }
}
