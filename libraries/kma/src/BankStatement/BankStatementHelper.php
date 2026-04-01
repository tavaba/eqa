<?php

namespace Kma\Library\Kma\BankStatement;

defined('_JEXEC') or die();

/**
 * Helper dùng chung cho chức năng nhập sao kê ngân hàng.
 *
 * Cung cấp:
 *   - getParser()   : factory trả về parser phù hợp theo NAPAS bank code.
 *   - reconcile()   : thuật toán đối chiếu payment_code giữa sao kê và DB,
 *                     hoàn toàn độc lập với bảng dữ liệu nguồn.
 *
 * Cách dùng điển hình trong một Model:
 * <code>
 *   $parser       = BankStatementHelper::getParser($napasCode);
 *   $transactions = $parser->parse($filePath);
 *   $result       = BankStatementHelper::reconcile($transactions, $dbRecords);
 *   // $result['matched']   : array của các cặp [record, transaction] đã đối chiếu
 *   // $result['duplicate'] : giao dịch khớp nhiều lần
 *   // $result['notFound']  : giao dịch không tìm thấy code trong DB
 * </code>
 *
 * @since 2.1.0
 */
abstract class BankStatementHelper
{
    /**
     * Map NAPAS bank code → parser class.
     * Bổ sung thêm ngân hàng mới tại đây.
     *
     * @var array<string, class-string<BankStatementParserInterface>>
     */
    private const PARSER_MAP = [
        '970423' => TpBankStatementParser::class,   // TP Bank
        '970422' => MbBankStatementParser::class,   // MB Bank
    ];

    // =========================================================================
    // Factory
    // =========================================================================

    /**
     * Trả về parser phù hợp với NAPAS bank code.
     *
     * @param  string  $napasCode  Mã NAPAS của ngân hàng (ví dụ: '970423').
     * @return BankStatementParserInterface
     * @throws \Exception  Nếu ngân hàng chưa được hỗ trợ.
     * @since 2.1.0
     */
    public static function getParser(string $napasCode): BankStatementParserInterface
    {
        $parserClass = self::PARSER_MAP[$napasCode] ?? null;

        if ($parserClass === null) {
            throw new \Exception(
                sprintf(
                    'Ngân hàng với mã NAPAS "%s" chưa được hỗ trợ đọc sao kê tự động.',
                    $napasCode
                )
            );
        }

        return new $parserClass();
    }

    /**
     * Kiểm tra xem một NAPAS bank code có được hỗ trợ không.
     *
     * @param  string  $napasCode
     * @return bool
     * @since 2.1.0
     */
    public static function isSupported(string $napasCode): bool
    {
        return isset(self::PARSER_MAP[$napasCode]);
    }

    /**
     * Trả về danh sách tên ngân hàng đang được hỗ trợ (dùng cho thông báo lỗi).
     *
     * @return string[]  Danh sách tên ngân hàng.
     * @since 2.1.0
     */
    public static function getSupportedBankNames(): array
    {
        $names = [];
        foreach (self::PARSER_MAP as $parserClass) {
            $names[] = (new $parserClass())->getBankName();
        }
        return $names;
    }

    // =========================================================================
    // Đối chiếu sao kê (reconcile)
    // =========================================================================

    /**
     * Đối chiếu danh sách giao dịch từ sao kê với danh sách bản ghi DB.
     *
     * Thuật toán:
     *   1. Với mỗi giao dịch, tìm payment_code nào xuất hiện trong nội dung (INSTR).
     *   2. Nếu một code khớp ≥ 2 giao dịch → đánh dấu duplicate.
     *   3. Nếu khớp đúng 1 giao dịch → đưa vào matched.
     *   4. Giao dịch không khớp code nào → notFound.
     *
     * Các bản ghi DB đầu vào PHẢI có các trường:
     *   - payment_code     (string)
     *   - payment_amount   (float|int)
     *   - payment_completed (bool|int)
     *   - learner_code     (string, dùng cho thông báo)
     *   - learner_lastname / learner_firstname (string, dùng cho thông báo)
     *
     * Kết quả trả về:
     * <code>
     * [
     *   'matched' => [
     *     // Các cặp hợp lệ (chưa trả tiền, số tiền khớp):
     *     ['record' => object, 'transaction' => array]
     *   ],
     *   'alreadyPaid'    => int,
     *   'amountMismatch' => [['payment_code'=>, 'learner_code'=>, 'expected'=>, 'actual'=>, 'description'=>]],
     *   'duplicate'      => [['payment_code'=>, 'learner_code'=>, 'count'=>, 'descriptions'=>[]]],
     *   'notFound'       => int,   // số giao dịch không khớp code nào
     * ]
     * </code>
     *
     * @param  array<int, array{date: string, credit: float, description: string}>  $transactions
     * @param  object[]  $dbRecords  Bản ghi DB, mỗi phần tử có các trường nêu trên.
     * @return array
     * @since 2.1.0
     */
    public static function reconcile(array $transactions, array $dbRecords): array
    {
        // Xây dựng map: payment_code (UPPER) → record
        $codeToRecord = [];
        foreach ($dbRecords as $rec) {
            $normalizedCode = strtoupper(trim($rec->payment_code));
            if ($normalizedCode !== '') {
                $codeToRecord[$normalizedCode] = $rec;
            }
        }

        // Đối chiếu từng giao dịch
        $codeMatchCount     = []; // code → số lần khớp
        $codeToTransactions = []; // code → danh sách giao dịch khớp

        foreach ($transactions as $tx) {
            $descUpper = strtoupper($tx['description']);
            foreach ($codeToRecord as $code => $rec) {
				if($code=='Q7DNBT8Y' || $code == 'SR1G0GKW')
					$x=1;
                if (str_contains($descUpper, $code)) {
                    $codeMatchCount[$code]      = ($codeMatchCount[$code] ?? 0) + 1;
                    $codeToTransactions[$code][] = $tx;
                }
            }
        }

        // Phân loại kết quả
        $result = [
            'matched'       => [],
            'alreadyPaid'   => 0,
            'amountMismatch'=> [],
            'duplicate'     => [],
            'notFound'      => 0,
        ];

        // Đếm giao dịch không khớp code nào
        foreach ($transactions as $tx) {
            $descUpper = strtoupper($tx['description']);
            $foundAny  = false;
            foreach (array_keys($codeToRecord) as $code) {
                if (str_contains($descUpper, $code)) {
                    $foundAny = true;
                    break;
                }
            }
            if (!$foundAny) {
                $result['notFound']++;
            }
        }

        // Xử lý duplicate (≥ 2 lần)
        $duplicateCodes = [];
        foreach ($codeMatchCount as $code => $count) {
            if ($count >= 2) {
                $duplicateCodes[$code] = true;
                $rec = $codeToRecord[$code];
                $result['duplicate'][] = [
                    'payment_code' => $code,
                    'learner_code' => $rec->learner_code ?? '',
                    'full_name'    => trim(($rec->learner_lastname ?? '') . ' ' . ($rec->learner_firstname ?? '')),
                    'count'        => $count,
                    'descriptions' => array_column($codeToTransactions[$code], 'description'),
                ];
            }
        }

        // Xử lý các code khớp đúng 1 lần
        foreach ($codeMatchCount as $code => $count) {
            if (isset($duplicateCodes[$code])) {
                continue; // đã xử lý ở trên
            }

            $rec = $codeToRecord[$code];
            $tx  = $codeToTransactions[$code][0];

            if ((bool) $rec->payment_completed) {
                $result['alreadyPaid']++;
                continue;
            }

            $expected = (float) $rec->payment_amount;
            $actual   = (float) $tx['credit'];
            if (abs($expected - $actual) > 1.0) {
                $result['amountMismatch'][] = [
                    'payment_code' => $code,
                    'learner_code' => $rec->learner_code ?? '',
                    'description'  => $tx['description'],
                    'expected'     => $expected,
                    'actual'       => $actual,
                ];
                continue;
            }

            // Hợp lệ
            $result['matched'][] = [
                'record'      => $rec,
                'transaction' => $tx,
            ];
        }

        return $result;
    }
}
