<?php

namespace Kma\Component\Eqa\Administrator\Controller;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use Kma\Component\Eqa\Administrator\Model\ExamseasonsModel;
use Kma\Library\Kma\BankStatement\BankStatementHelper;
use Kma\Library\Kma\BankStatement\BankStatementImportResultHelper;
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
            $model  = ComponentHelper::createModel('SecondAttempts');
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
	 * Bổ sung các trường hợp mới vào danh sách thi lần hai (không xóa bản ghi cũ).
	 *
	 * @return void
	 * @since 2.0.5
	 */
	public function addNew(): void
	{
		$redirectUrl = Route::_('index.php?option=com_eqa&view=secondattempts', false);

		try {
			$this->checkToken();

			if (!$this->app->getIdentity()->authorise('core.create', $this->option)) {
				throw new Exception('Bạn không có quyền thực hiện chức năng này');
			}

			/** @var \Kma\Component\Eqa\Administrator\Model\SecondAttemptsModel $model */
			$model  = ComponentHelper::createModel('SecondAttempts');
			$result = $model->addNew();

			$added = $result['added'];
			if ($added === 0) {
				$this->setMessage('Không có trường hợp mới nào cần bổ sung.', 'info');
			} else {
				$this->setMessage(
					sprintf('Đã bổ sung <b>%d</b> trường hợp thi lần 2 mới.', $added),
					'success'
				);
			}
		} catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
		}

		$this->setRedirect($redirectUrl);
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

	// =========================================================================
	// importStatement — nhập sao kê ngân hàng phí thi lại
	// =========================================================================

	/**
	 * Nhận file sao kê Excel, đối chiếu payment_code với các bản ghi thi lần hai,
	 * cập nhật payment_completed cho những trường hợp hợp lệ.
	 *
	 * Sử dụng BankStatementImportResultHelper::buildMessage() để tạo thông báo
	 * thay cho method buildImportResultMessage() cục bộ đã bị xóa.
	 *
	 * POST params:
	 *   - napas_code     : string — Mã NAPAS ngân hàng
	 *   - bank_statement : file   — File .xlsx sao kê
	 *
	 * @since 2.0.7 (refactored)
	 */
	public function importStatement(): void
	{
		$listUrl = Route::_('index.php?option=com_eqa&view=secondattempts', false);
		$this->setRedirect($listUrl);

		try {
			$this->checkToken();

			if (!$this->app->getIdentity()->authorise('core.manage', $this->option)) {
				throw new Exception('Bạn không có quyền thực hiện chức năng này.');
			}

			// Kiểm tra ngân hàng
			$napasCode = trim($this->input->post->getString('napas_code', ''));
			if (empty($napasCode)) {
				throw new Exception('Vui lòng chọn ngân hàng.');
			}
			if (!BankStatementHelper::isSupported($napasCode)) {
				$supported = implode(', ', BankStatementHelper::getSupportedBankNames());
				throw new Exception(sprintf(
					'Ngân hàng này chưa được hỗ trợ đọc sao kê tự động. Các ngân hàng hỗ trợ: %s.',
					$supported
				));
			}

			// Kiểm tra file upload
			$uploadedFile = $this->input->files->get('bank_statement');
			if (empty($uploadedFile) || empty($uploadedFile['tmp_name'])) {
				throw new Exception('Vui lòng chọn file sao kê ngân hàng (.xlsx).');
			}
			if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
				throw new Exception('Lỗi upload file (mã lỗi: ' . $uploadedFile['error'] . ').');
			}
			if (strtolower(pathinfo($uploadedFile['name'] ?? '', PATHINFO_EXTENSION)) !== 'xlsx') {
				throw new Exception('Chỉ chấp nhận file Excel (.xlsx).');
			}

			// Lưu file vào thư mục tmp
			$tmpDir  = Factory::getApplication()->get('tmp_path');
			$tmpFile = $tmpDir . '/eqa_sa_stmt_' . uniqid('', true) . '.xlsx';
			if (!move_uploaded_file($uploadedFile['tmp_name'], $tmpFile)) {
				throw new Exception('Không thể lưu file upload. Vui lòng kiểm tra quyền ghi thư mục tmp.');
			}

			try {
				/** @var \Kma\Component\Eqa\Administrator\Model\SecondAttemptsModel $model */
				$model  = ComponentHelper::createModel('SecondAttempts');
				$result = $model->importBankStatement($tmpFile, $napasCode);
			} finally {
				if (file_exists($tmpFile)) {
					@unlink($tmpFile);
				}
			}

			$this->setMessage(
				BankStatementImportResultHelper::buildMessage($result, 'đã nộp phí thi lại'),
				BankStatementImportResultHelper::getMessageType($result)
			);

		} catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
		}
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
			$model  = ComponentHelper::createModel('SecondAttempts');
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
