<?php

namespace Kma\Component\Eqa\Administrator\Controller;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use Kma\Component\Eqa\Administrator\Model\ExamseasonsModel;
use Kma\Library\Kma\Controller\AdminController;
use Kma\Component\Eqa\Administrator\Model\SecondAttemptsModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class SecondAttemptsController extends AdminController
{
    /**
     * Làm mới danh sách thí sinh thi lần hai.
     *
     * @return void
     * @since 2.0.2
     */
    public function refresh(): void
    {
        try {
            $this->checkToken();

            if (!$this->app->getIdentity()->authorise('core.edit', $this->option)) {
                throw new Exception('Bạn không có quyền thực hiện chức năng này');
            }

            /** @var SecondAttemptsModel $model */
            $model  = ComponentHelper::getMVCFactory()->createModel('SecondAttempts');
            $result = $model->refresh();

            $msg = sprintf(
                'Làm mới thành công! Đã xóa %d trường hợp lỗi thời, thêm mới %d trường hợp.',
                $result['removed'],
                $result['added']
            );
            $this->setMessage($msg, 'success');

        } catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(
            Route::_('index.php?option=com_eqa&view=secondattempts', false)
        );
    }

    /**
     * Hiển thị form upload bản sao kê ngân hàng.
     *
     * Không thực hiện xử lý — chỉ redirect đến layout 'importstatement'.
     * Joomla MVC sẽ tự gọi HtmlView::prepareDataForLayoutImportstatement().
     *
     * @return void
     * @since 2.0.3
     */
    public function showImportStatement(): void
    {
        $this->setRedirect(
            Route::_(
                'index.php?option=com_eqa&view=secondattempts&layout=importstatement',
                false
            )
        );
    }

    /**
     * Nhận file sao kê, đối chiếu mã thanh toán và cập nhật trạng thái nộp phí.
     *
     * Flow:
     *   1. Validate token + quyền.
     *   2. Validate file upload (có file, đúng extension .xlsx, không lỗi upload).
     *   3. Lưu file tạm vào thư mục tmp của Joomla.
     *   4. Gọi SecondAttemptsModel::importBankStatement().
     *   5. Build message tổng hợp kết quả.
     *   6. Xóa file tạm.
     *   7. Redirect về list view.
     *
     * @return void
     * @since 2.0.3
     */
    public function importStatement(): void
    {
        $redirectUrl = Route::_('index.php?option=com_eqa&view=secondattempts', false);

        try {
            $this->checkToken();

            if (!$this->app->getIdentity()->authorise('core.edit', $this->option)) {
                throw new Exception('Bạn không có quyền thực hiện chức năng này');
            }

            // ── Validate file upload ─────────────────────────────────────────
            $uploadedFile = $this->input->files->get('bank_statement');

            if (empty($uploadedFile) || empty($uploadedFile['tmp_name'])) {
                throw new Exception('Vui lòng chọn file bản sao kê ngân hàng (.xlsx).');
            }

            if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                throw new Exception(
                    'Lỗi upload file (mã lỗi: ' . $uploadedFile['error'] . '). ' .
                    'Vui lòng thử lại.'
                );
            }

            $originalName = $uploadedFile['name'] ?? '';
            $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($ext !== 'xlsx') {
                throw new Exception(
                    'Định dạng file không hợp lệ. Chỉ chấp nhận file Excel (.xlsx) ' .
                    'bản sao kê MB Bank.'
                );
            }

            // ── Lưu file tạm ─────────────────────────────────────────────────
            $tmpDir  = Factory::getApplication()->get('tmp_path');
            $tmpFile = $tmpDir . '/eqa_statement_' . uniqid('', true) . '.xlsx';

            if (!move_uploaded_file($uploadedFile['tmp_name'], $tmpFile)) {
                throw new Exception('Không thể lưu file upload. Vui lòng kiểm tra quyền ghi thư mục tmp.');
            }

            try {
                // ── Gọi model xử lý đối chiếu ────────────────────────────────
                /** @var SecondAttemptsModel $model */
                $model  = ComponentHelper::getMVCFactory()->createModel('SecondAttempts');
                $result = $model->importBankStatement($tmpFile);

            } finally {
                // Luôn xóa file tạm dù thành công hay thất bại
                if (file_exists($tmpFile)) {
                    @unlink($tmpFile);
                }
            }

            // ── Build thông báo kết quả ───────────────────────────────────────
            $this->setMessage(
                $this->buildImportResultMessage($result),
                ($result['updated'] > 0 || empty($result['amountMismatch'])) ? 'success' : 'warning'
            );

        } catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }

        $this->setRedirect($redirectUrl);
    }

	// =========================================================================
	// Quản lý trạng thái thanh toán (thủ công) — POST → REDIRECT → POST
	// =========================================================================

	/**
	 * POST 1: Tiếp nhận danh sách id được chọn, lấy id đầu tiên,
	 * redirect đến layout 'setpayment' để người dùng nhập thông tin.
	 *
	 * @return void
	 * @since 2.0.4
	 */
	public function setPaymentStatus(): void
	{
		$listUrl = Route::_('index.php?option=com_eqa&view=secondattempts', false);

		try {
			$this->checkToken();

			if (!$this->app->getIdentity()->authorise('core.edit', $this->option)) {
				throw new Exception('Bạn không có quyền thực hiện chức năng này');
			}

			$ids = (array) $this->input->post->get('cid', [], 'int');
			$ids = array_values(array_filter($ids));

			if (empty($ids)) {
				throw new Exception('Không có trường hợp nào được chọn');
			}

			// Chỉ xử lý trường hợp đầu tiên trong danh sách được chọn
			$id = (int) $ids[0];

			$this->setRedirect(
				Route::_(
					'index.php?option=com_eqa&view=secondattempts&layout=setpayment&id=' . $id,
					false
				)
			);
			return;

		} catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
		}

		$this->setRedirect($listUrl);
	}

	/**
	 * POST 2: Tiếp nhận dữ liệu từ form layout 'setpayment',
	 * gọi model cập nhật DB, redirect về list view với thông báo kết quả.
	 *
	 * @return void
	 * @since 2.0.4
	 */
	public function savePaymentStatus(): void
	{
		$listUrl = Route::_('index.php?option=com_eqa&view=secondattempts', false);

		try {
			$this->checkToken();

			if (!$this->app->getIdentity()->authorise('core.edit', $this->option)) {
				throw new Exception('Bạn không có quyền thực hiện chức năng này');
			}

			$id               = $this->input->post->getInt('id');
			$paymentCompleted = (bool) $this->input->post->getInt('payment_completed', 1);
			$descriptionRaw   = $this->input->post->getString('description', '');
			$description      = trim($descriptionRaw) !== '' ? trim($descriptionRaw) : null;

			if ($id <= 0) {
				throw new Exception('ID bản ghi không hợp lệ');
			}

			/** @var SecondAttemptsModel $model */
			$model  = ComponentHelper::getMVCFactory()->createModel('SecondAttempts');
			$result = $model->savePaymentStatus($id, $paymentCompleted, $description);

			$statusLabel = $result['paymentCompleted']
				? '<b>Đã nộp phí</b>'
				: '<b>Chưa nộp phí</b>';

			$msg = sprintf(
				'Đã cập nhật trạng thái nộp phí của <b>%s</b> thành %s.',
				htmlspecialchars($result['learnerCode']),
				$statusLabel
			);
			$this->setMessage($msg, 'success');

		} catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
		}

		$this->setRedirect($listUrl);
	}

    /**
     * Tạo thông báo HTML tổng hợp kết quả đối chiếu sao kê.
     *
     * @param  array  $result  Kết quả trả về từ importBankStatement().
     * @return string
     * @since 2.0.3
     */
    private function buildImportResultMessage(array $result): string
    {
        $lines = [];

        // Kết quả cập nhật thành công
        if ($result['updated'] > 0) {
            $codes  = implode(', ', $result['updatedCodes']);
            $lines[] = sprintf(
                '✅ Đã ghi nhận <b>%d</b> trường hợp nộp phí thành công: %s',
                $result['updated'],
                $codes
            );
        } else {
            $lines[] = 'ℹ️ Không có trường hợp nào được cập nhật trạng thái thanh toán.';
        }

        // Đã thanh toán từ trước
        if ($result['alreadyPaid'] > 0) {
            $lines[] = sprintf(
                'ℹ️ <b>%d</b> trường hợp đã được ghi nhận thanh toán từ trước (bỏ qua).',
                $result['alreadyPaid']
            );
        }

        // Sai số tiền
        if (!empty($result['amountMismatch'])) {
            $lines[] = sprintf(
                '⚠️ <b>%d</b> trường hợp <b>sai số tiền</b>, chưa được cập nhật:',
                count($result['amountMismatch'])
            );
            foreach ($result['amountMismatch'] as $item) {
                $lines[] = sprintf(
                    '&nbsp;&nbsp;• <code>%s</code> (HVSV: %s) — Cần: <b>%s đ</b>, Thực nhận: <b>%s đ</b> | Nội dung: %s',
                    htmlspecialchars($item['payment_code']),
                    htmlspecialchars($item['learner_code']),
                    number_format($item['expected'], 0, ',', '.'),
                    number_format($item['actual'], 0, ',', '.'),
                    htmlspecialchars(mb_substr($item['description'], 0, 80))
                );
            }
        }

        // Thanh toán 2 lần
        if (!empty($result['duplicate'])) {
            $lines[] = sprintf(
                '🔴 <b>%d</b> mã thanh toán xuất hiện <b>nhiều lần</b> trong sao kê (chưa xử lý, cần kiểm tra thủ công):',
                count($result['duplicate'])
            );
            foreach ($result['duplicate'] as $item) {
                $lines[] = sprintf(
                    '&nbsp;&nbsp;• <code>%s</code> — HVSV: <b>%s</b> (%s) — xuất hiện <b>%d lần</b>',
                    htmlspecialchars($item['payment_code']),
                    htmlspecialchars($item['full_name']),
                    htmlspecialchars($item['learner_code']),
                    $item['count']
                );
                foreach ($item['descriptions'] as $desc) {
                    $lines[] = sprintf(
                        '&nbsp;&nbsp;&nbsp;&nbsp;↳ %s',
                        htmlspecialchars(mb_substr($desc, 0, 100))
                    );
                }
            }
        }

        return implode('<br>', $lines);
    }


	// =========================================================================
	// Xuất danh sách thí sinh thi lại ra Excel
	// =========================================================================
	public function exportFullList()
	{
		$this->exportList(false);
	}
	public function exportPaidList()
	{
		$this->exportList(true);
	}
	private function exportList(bool $onlyFreeOrPaymentCompleted): void
	{
		try
		{
			/**
			 * Get unpassed (failed or deferred) examinees
			 * @var SecondAttemptsModel $model
			 */
			$model = $this->getModel('SecondAttempts');
			$examinees = $model->loadListForExport($onlyFreeOrPaymentCompleted);
			if(empty($examinees))
				throw new Exception('Không có thí sinh thi lại, bảo lưu');

			//Write to Excel file
			$spreadsheet = new Spreadsheet();
			$spreadsheet->removeSheetByIndex(0);
			IOHelper::writeUnpassedExaminees($spreadsheet, $examinees);

			//Let user download the file
			$fileName = 'Danh sách thí sinh thi lại, bảo lưu.xlsx';
			IOHelper::sendHttpXlsx($spreadsheet, $fileName);
			jexit();
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=secondattempts', false));
			return;
		}
	}

}
