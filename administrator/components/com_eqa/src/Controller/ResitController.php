<?php

namespace Kma\Component\Eqa\Administrator\Controller;

defined('_JEXEC') or die();

require_once JPATH_ROOT . '/vendor/autoload.php';

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Enum\Action;
use Kma\Component\Eqa\Administrator\Enum\ObjectType;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use Kma\Component\Eqa\Administrator\Model\ResitModel;
use Kma\Library\Kma\BankStatement\BankStatementHelper;
use Kma\Library\Kma\BankStatement\BankStatementImportResultHelper;
use Kma\Library\Kma\Controller\FormController;
use Kma\Library\Kma\DataObject\LogEntry;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Controller của 'đợt thi lại' (resit).
 *
 * Ngoài các tác vụ form chuẩn (add/edit/save/cancel) do lớp cha cung cấp,
 * controller này còn chứa toàn bộ tác vụ tác động lên danh sách thí sinh của đợt
 * (#__eqa_resit_learner): thêm thí sinh, xóa thí sinh, nhập sao kê, cập nhật
 * trạng thái nộp phí, xuất dữ liệu. Cách tổ chức này theo đúng khuôn mẫu
 * ExamController đối với bảng #__eqa_exam_learner — bảng junction không có
 * controller riêng.
 *
 * $view_list được khai báo tường minh: cơ chế suy diễn dạng số nhiều của Joomla
 * chuyển tên về chữ thường, làm hỏng việc nạp lớp View trên hệ điều hành phân
 * biệt hoa/thường.
 *
 * @since 2.1.8
 */
class ResitController extends FormController
{
    /**
     * Tên view danh sách để quay về sau khi lưu/hủy.
     *
     * @var    string
     * @since  2.1.8
     */
    protected $view_list = 'Resits';

    /**
     * Mã đợt thi lại đọc từ request.
     *
     * Mọi tác vụ trên danh sách thí sinh đều làm việc trong phạm vi một đợt;
     * tham số được form danh sách gửi lên dưới dạng hidden field (2.1.8).
     *
     * @return  int  0 nếu request không mang tham số hợp lệ
     * @since   2.1.8
     */
    private function getResitIdFromRequest(): int
    {
        return (int) $this->input->getInt('resit_id');
    }

    /**
     * URL của màn hình danh sách thí sinh thuộc một đợt thi lại.
     *
     * Nếu không xác định được đợt thì quay về màn hình các đợt.
     *
     * @param   int  $resitId
     *
     * @return  string
     * @since   2.1.8
     */
    private function getResitUrl(int $resitId): string
    {
        $url = $resitId > 0
            ? 'index.php?option=com_eqa&view=ResitExaminees&resit_id=' . $resitId
            : 'index.php?option=com_eqa&view=Resits';

        return Route::_($url, false);
    }

    /**
     * Model của đợt thi lại — nơi chứa toàn bộ nghiệp vụ trên bảng thí sinh.
     *
     * @return  ResitModel
     * @throws  Exception  Nếu không khởi tạo được model.
     * @since   2.1.8
     */
    private function getResitModel(): ResitModel
    {
        $model = $this->getModel();

        if (!$model instanceof ResitModel) {
            throw new Exception('Không khởi tạo được model Resit.');
        }

        return $model;
    }

	/**
	 * "Thêm thí sinh": bổ sung các trường hợp đủ điều kiện thi lần 2 vào đợt
	 * đang mở, không xóa bản ghi nào đang có.
	 *
	 * @return void
	 * @since 2.0.5
	 */
	public function addNew(): void
	{
		$resitId = $this->getResitIdFromRequest();

		try {
			$this->checkToken();

			if (!$this->app->getIdentity()->authorise('core.create', $this->option)) {
				throw new Exception('Bạn không có quyền thực hiện chức năng này');
			}

			$result = $this->getResitModel()->addNew($resitId);

			$added = $result['added'];
			if ($added === 0) {
				$this->setMessage('Không có trường hợp mới nào cần bổ sung.', 'info');
			} else {
				$this->setMessage(
					sprintf(
						'Đã bổ sung <b>%d</b> trường hợp thi lần 2 mới vào danh sách <b>%s</b>.',
						$added,
						htmlspecialchars($result['resitName'])
					),
					'success'
				);
			}

			$this->writeLog(new LogEntry(
				action: Action::ADD_RESIT,
				objectType: ObjectType::Resit->value,
				isSuccess: true,
				objectId: $resitId,
				objectTitle: $result['resitName'],
				extraData: $result,
			));
		} catch (Exception $e) {
			$this->writeLog(new LogEntry(
				action: Action::ADD_RESIT,
				objectType: ObjectType::Resit->value,
				isSuccess: false,
				objectId: $resitId,
				errorMessage: $e->getMessage(),
			));
			$this->setMessage($e->getMessage(), 'error');
		}

		$this->setRedirect($this->getResitUrl($resitId));
	}

	/**
	 * "Xóa": loại các thí sinh được chọn khỏi đợt thi lại đang mở.
	 *
	 * Thí sinh ĐÃ ĐÓNG PHÍ không bị xóa (xem ResitModel::deleteExaminees());
	 * các trường hợp bị từ chối được liệt kê tường minh trong thông báo để quản
	 * trị viên biết mà xử lý.
	 *
	 * @return void
	 * @since 2.1.8
	 */
	public function deleteExaminees(): void
	{
		$resitId = $this->getResitIdFromRequest();

		try {
			$this->checkToken();

			if (!$this->app->getIdentity()->authorise('core.delete', $this->option)) {
				throw new Exception('Bạn không có quyền thực hiện chức năng này');
			}

			$ids = (array) $this->input->post->get('cid', [], 'int');

			$result = $this->getResitModel()->deleteExaminees($resitId, $ids);

			// Thông báo: phần đã xóa và phần bị từ chối
			$messages = [];

			if ($result['deleted'] > 0) {
				$messages[] = sprintf(
					'Đã xóa <b>%d</b> thí sinh khỏi danh sách <b>%s</b>.',
					$result['deleted'],
					htmlspecialchars($result['resitName'])
				);
			}

			if (!empty($result['paidCodes'])) {
				$messages[] = sprintf(
					'<b>%d</b> trường hợp KHÔNG được xóa vì đã đóng phí: %s.'
					. ' Muốn xóa, hãy chuyển trạng thái nộp phí về "Chưa nộp phí" trước.',
					count($result['paidCodes']),
					htmlspecialchars(implode(', ', $result['paidCodes']))
				);
			}

			if ($result['foreignCount'] > 0) {
				$messages[] = sprintf(
					'<b>%d</b> bản ghi bị bỏ qua vì không thuộc danh sách đang mở.',
					$result['foreignCount']
				);
			}

			$this->setMessage(
				implode('<br>', $messages),
				$result['deleted'] > 0 ? 'success' : 'warning'
			);

			$this->writeLog(new LogEntry(
				action: Action::REMOVE_EXAMINEE,
				objectType: ObjectType::Resit->value,
				isSuccess: true,
				objectId: $resitId,
				objectTitle: $result['resitName'],
				extraData: $result,
			));
		} catch (Exception $e) {
			$this->writeLog(new LogEntry(
				action: Action::REMOVE_EXAMINEE,
				objectType: ObjectType::Resit->value,
				isSuccess: false,
				objectId: $resitId,
				errorMessage: $e->getMessage(),
			));
			$this->setMessage($e->getMessage(), 'error');
		}

		$this->setRedirect($this->getResitUrl($resitId));
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
        $resitId = $this->getResitIdFromRequest();

        $this->setRedirect(
            Route::_(
                'index.php?option=com_eqa&view=ResitExaminees&layout=importstatement'
                . '&resit_id=' . $resitId,
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
		$resitId = $this->getResitIdFromRequest();
		$this->setRedirect($this->getResitUrl($resitId));

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
				$result = $this->getResitModel()->importBankStatement($resitId, $tmpFile, $napasCode);
			} finally {
				if (file_exists($tmpFile)) {
					@unlink($tmpFile);
				}
			}

			$this->writeLog(new LogEntry(
				action: Action::IMPORT_STATEMENT,
				objectType: ObjectType::Resit->value,
				isSuccess: true,
				objectId: $resitId,
				extraData: [
					'bank'   => $napasCode,
					'result' => (array) $result,
				],
			));

			$this->setMessage(
				BankStatementImportResultHelper::buildMessage($result, 'đã nộp phí thi lại'),
				BankStatementImportResultHelper::getMessageType($result)
			);

		} catch (Exception $e) {
			$this->writeLog(new LogEntry(
				action: Action::IMPORT_STATEMENT,
				objectType: ObjectType::Resit->value,
				isSuccess: false,
				objectId: $resitId,
				errorMessage: $e->getMessage(),
			));
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
		$resitId = $this->getResitIdFromRequest();

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
					'index.php?option=com_eqa&view=ResitExaminees&layout=setpayment'
					. '&id=' . $id . '&resit_id=' . $resitId,
					false
				)
			);
			return;

		} catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
		}

		$this->setRedirect($this->getResitUrl($resitId));
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
		$resitId = $this->getResitIdFromRequest();

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

			$result = $this->getResitModel()->savePaymentStatus($id, $paymentCompleted, $description);

			// Quay lại đúng đợt chứa bản ghi vừa cập nhật
			$resitId = $result['resitId'] ?: $resitId;

			$statusLabel = $result['paymentCompleted']
				? '<b>Đã nộp phí</b>'
				: '<b>Chưa nộp phí</b>';

			$msg = sprintf(
				'Đã cập nhật trạng thái nộp phí của <b>%s</b> thành %s.',
				htmlspecialchars($result['learnerCode']),
				$statusLabel
			);
			$this->setMessage($msg, 'success');

			$this->writeLog(new LogEntry(
				action: Action::SET_PAYMENT_STATUS,
				objectType: ObjectType::ResitExaminee->value,
				isSuccess: true,
				objectId: $id,
				extraData: [
					'resit_id'           => $resitId,
					'payment_completed' => $paymentCompleted,
					'description'       => $description,
				],
			));

		} catch (Exception $e) {
			$this->writeLog(new LogEntry(
				action: Action::SET_PAYMENT_STATUS,
				objectType: ObjectType::ResitExaminee->value,
				isSuccess: false,
				objectId: $id ?? 0,
				errorMessage: $e->getMessage(),
				extraData: ['resit_id' => $resitId],
			));
			$this->setMessage($e->getMessage(), 'error');
		}

		$this->setRedirect($this->getResitUrl($resitId));
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
		$resitId = $this->getResitIdFromRequest();

		try
		{
			// Lấy thí sinh chưa đạt (thi lại hoặc bảo lưu) của đợt đang mở
			$model     = $this->getResitModel();
			$resit     = $model->assertAccessible($resitId);
			$examinees = $model->loadExamineesForExport($resitId, $onlyFreeOrPaymentCompleted);
			if(empty($examinees))
				throw new Exception('Không có thí sinh thi lại, bảo lưu trong đợt này');

			//Write to Excel file
			$spreadsheet = new Spreadsheet();
			$spreadsheet->removeSheetByIndex(0);
			IOHelper::writeUnpassedExaminees($spreadsheet, $examinees);

			//Let user download the file
			$fileName = 'Danh sách thí sinh thi lại, bảo lưu. ' . $resit->name . '.xlsx';
			IOHelper::sendHttpXlsx($spreadsheet, $fileName);
			jexit();
		}
		catch (Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
			$this->setRedirect($this->getResitUrl($resitId));
			return;
		}
	}

}
