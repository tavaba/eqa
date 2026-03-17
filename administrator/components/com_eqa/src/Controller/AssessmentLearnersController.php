<?php

namespace Kma\Component\Eqa\Administrator\Controller;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Library\Kma\BankStatement\BankStatementHelper;
use Kma\Library\Kma\Controller\AdminController;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Component\Eqa\Administrator\Model\AssessmentLearnersModel;

/**
 * Items Controller cho danh sách thí sinh sát hạch.
 *
 * Xử lý các tác vụ:
 *   - addLearners         : POST — lưu danh sách thí sinh thêm thủ công.
 *   - setPaymentInfo      : POST 1 — nhận id từ checkbox, redirect sang layout setpayment.
 *   - savePaymentInfo     : POST 2 — lưu thông tin thanh toán đã nhập.
 *
 * @since 2.1.0
 */
class AssessmentLearnersController extends AdminController
{
    // =========================================================================
    // addLearners — nhận form nhập mã HVSV, ghi DB, redirect về list
    // =========================================================================

    /**
     * Tiếp nhận danh sách mã HVSV từ form layout 'addlearners',
     * thêm vào bảng #__eqa_assessment_learner, redirect về list view với thông báo.
     *
     * @since 2.1.0
     */
    public function addLearners(): void
    {
        $this->checkToken();

        $assessmentId = $this->input->post->getInt('assessment_id');
        $listUrl      = Route::_(
            'index.php?option=com_eqa&view=assessmentlearners&assessment_id=' . $assessmentId,
            false
        );
        $this->setRedirect($listUrl);

        try {
            if (!$this->app->getIdentity()->authorise('core.edit', $this->option)) {
                throw new Exception('Bạn không có quyền thực hiện chức năng này.');
            }

            if ($assessmentId <= 0) {
                throw new Exception('Kỳ sát hạch không hợp lệ.');
            }

            $rawCodes   = $this->input->post->getString('learner_codes', '');
            $operatorId = (int) $this->app->getIdentity()->id;

            /** @var AssessmentLearnersModel $model */
            $model = ComponentHelper::getMVCFactory()->createModel('AssessmentLearners');

            if (!$model->isAssessmentEditable($assessmentId)) {
                throw new Exception('Kỳ sát hạch đã kết thúc hoặc đã được đánh dấu hoàn tất — không thể chỉnh sửa danh sách thí sinh.');
            }

            $result = $model->addLearners($assessmentId, $rawCodes, $operatorId);

            // Thông báo tổng hợp
            if (!empty($result['added'])) {
                $this->setMessage(
                    sprintf('Đã thêm %d thí sinh: <b>%s</b>.', count($result['added']), implode(', ', $result['added'])),
                    'success'
                );
            }
            if (!empty($result['skipped'])) {
                $this->setMessage(
                    sprintf(
                        'Bỏ qua %d thí sinh đã có trong danh sách: <b>%s</b>.',
                        count($result['skipped']),
                        implode(', ', $result['skipped'])
                    ),
                    'warning'
                );
            }
            if (!empty($result['notFound'])) {
                $this->setMessage(
                    sprintf(
                        'Không tìm thấy %d mã HVSV: <b>%s</b>.',
                        count($result['notFound']),
                        implode(', ', array_map('htmlspecialchars', $result['notFound']))
                    ),
                    'error'
                );
            }

        } catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }
    }

    // =========================================================================
    // setPaymentInfo — POST 1: nhận checkbox, redirect sang layout setpayment
    // =========================================================================

    /**
     * POST 1: Nhận danh sách id được chọn, lấy id đầu tiên,
     * redirect đến layout 'setpayment'.
     *
     * @since 2.1.0
     */
    public function setPaymentInfo(): void
    {
        $assessmentId = $this->input->post->getInt('assessment_id', 0);
        $listUrl      = Route::_(
            'index.php?option=com_eqa&view=assessmentlearners&assessment_id=' . $assessmentId,
            false
        );

        try {
            $this->checkToken();

            if (!$this->app->getIdentity()->authorise('core.edit', $this->option)) {
                throw new Exception('Bạn không có quyền thực hiện chức năng này.');
            }

            $ids = array_values(array_filter(
                (array) $this->input->post->get('cid', [], 'int')
            ));

            if (empty($ids)) {
                throw new Exception('Không có thí sinh nào được chọn.');
            }

            // Lấy assessment_id từ bản ghi đầu tiên để kiểm tra điều kiện
            $id = (int) $ids[0];
            /** @var AssessmentLearnersModel $model */
            $model = ComponentHelper::getMVCFactory()->createModel('AssessmentLearners');
            $item  = $model->getItemById($id);

            if (!$model->isAssessmentEditable((int) $item->assessment_id)) {
                throw new Exception('Kỳ sát hạch đã kết thúc hoặc đã được đánh dấu hoàn tất — không thể đổi trạng thái nộp phí.');
            }

            $this->setRedirect(Route::_(
                'index.php?option=com_eqa&view=assessmentlearners&layout=setpayment&id=' . $id,
                false
            ));
            return;

        } catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }

        $this->setRedirect($listUrl);
    }

    // =========================================================================
    // savePaymentInfo — POST 2: lưu thông tin thanh toán
    // =========================================================================

    /**
     * POST 2: Tiếp nhận dữ liệu từ form layout 'setpayment',
     * gọi model cập nhật DB, redirect về list view với thông báo.
     *
     * @since 2.1.0
     */
    public function savePaymentInfo(): void
    {
        $assessmentId = $this->input->post->getInt('assessment_id', 0);
        $listUrl      = Route::_(
            'index.php?option=com_eqa&view=assessmentlearners&assessment_id=' . $assessmentId,
            false
        );
        $this->setRedirect($listUrl);

        try {
            $this->checkToken();

            if (!$this->app->getIdentity()->authorise('core.edit', $this->option)) {
                throw new Exception('Bạn không có quyền thực hiện chức năng này.');
            }

            $id               = $this->input->post->getInt('id');
            $paymentAmount    = $this->input->post->getInt('payment_amount', 0);
            $paymentCompleted = (bool) $this->input->post->getInt('payment_completed', 0);
            $noteRaw          = $this->input->post->getString('note', '');
            $note             = trim($noteRaw) !== '' ? trim($noteRaw) : null;
            $operatorId       = (int) $this->app->getIdentity()->id;

            if ($id <= 0) {
                throw new Exception('ID bản ghi không hợp lệ.');
            }

            /** @var AssessmentLearnersModel $model */
            $model = ComponentHelper::getMVCFactory()->createModel('AssessmentLearners');

            // Lấy assessment_id từ bản ghi để kiểm tra điều kiện
            $item = $model->getItemById($id);
            if (!$model->isAssessmentEditable((int) $item->assessment_id)) {
                throw new Exception('Kỳ sát hạch đã kết thúc hoặc đã được đánh dấu hoàn tất — không thể đổi trạng thái nộp phí.');
            }

            $learnerCode = $model->savePaymentInfo($id, $paymentAmount, $paymentCompleted, $note, $operatorId);

            $statusLabel = $paymentCompleted ? '<b>Đã nộp phí</b>' : '<b>Chưa nộp phí</b>';
            $this->setMessage(
                sprintf('Đã cập nhật thông tin thanh toán của <b>%s</b> thành %s.', htmlspecialchars($learnerCode), $statusLabel),
                'success'
            );

        } catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
        }
    }

	// =========================================================================
	// importStatement — nhập sao kê ngân hàng
	// =========================================================================

	/**
	 * Nhận file sao kê, đối chiếu payment_code và cập nhật trạng thái nộp phí.
	 *
	 * @since 2.1.0
	 */
	public function importStatement(): void
	{
		$assessmentId = $this->input->post->getInt('assessment_id');
		$listUrl      = Route::_(
			'index.php?option=com_eqa&view=assessmentlearners&assessment_id=' . $assessmentId,
			false
		);
		$this->setRedirect($listUrl);

		try {
			$this->checkToken();

			if (!$this->app->getIdentity()->authorise('core.edit', $this->option)) {
				throw new Exception('Bạn không có quyền thực hiện chức năng này.');
			}

			if ($assessmentId <= 0) {
				throw new Exception('Kỳ sát hạch không hợp lệ.');
			}

			/** @var AssessmentLearnersModel $model */
			$model = ComponentHelper::getMVCFactory()->createModel('AssessmentLearners');
			if (!$model->isAssessmentEditable($assessmentId)) {
				throw new Exception('Kỳ sát hạch đã kết thúc hoặc đã hoàn tất — không thể cập nhật.');
			}

			$napasCode = trim($this->input->post->getString('napas_code', ''));
			if (empty($napasCode)) {
				throw new Exception('Vui lòng chọn ngân hàng.');
			}
			if (!BankStatementHelper::isSupported($napasCode)) {
				$supported = implode(', ', BankStatementHelper::getSupportedBankNames());
				throw new Exception(
					sprintf('Ngân hàng này chưa được hỗ trợ đọc sao kê tự động. Các ngân hàng hỗ trợ: %s.', $supported)
				);
			}

			$uploadedFile = $this->input->files->get('bank_statement');
			if (empty($uploadedFile) || empty($uploadedFile['tmp_name'])) {
				throw new Exception('Vui lòng chọn file bản sao kê ngân hàng (.xlsx).');
			}
			if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
				throw new Exception('Lỗi upload file (mã lỗi: ' . $uploadedFile['error'] . ').');
			}
			if (strtolower(pathinfo($uploadedFile['name'] ?? '', PATHINFO_EXTENSION)) !== 'xlsx') {
				throw new Exception('Chỉ chấp nhận file Excel (.xlsx).');
			}

			$tmpDir  = Factory::getApplication()->get('tmp_path');
			$tmpFile = $tmpDir . '/eqa_statement_' . uniqid('', true) . '.xlsx';
			if (!move_uploaded_file($uploadedFile['tmp_name'], $tmpFile)) {
				throw new Exception('Không thể lưu file upload. Vui lòng kiểm tra quyền ghi thư mục tmp.');
			}

			try {
				$operatorId = (int) $this->app->getIdentity()->id;
				$result     = $model->importBankStatement($tmpFile, $napasCode, $assessmentId, $operatorId);
			} finally {
				if (file_exists($tmpFile)) {
					@unlink($tmpFile);
				}
			}

			$this->setMessage(
				$this->buildImportResultMessage($result),
				($result['updated'] > 0) ? 'success' : 'warning'
			);

		} catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
		}
	}

	/**
	 * Tạo thông báo HTML tổng hợp kết quả đối chiếu sao kê.
	 *
	 * @since 2.1.0
	 */
	private function buildImportResultMessage(array $result): string
	{
		$lines = [];

		if ($result['updated'] > 0) {
			$lines[] = sprintf(
				'✅ Đã ghi nhận <b>%d</b> trường hợp nộp phí: %s',
				$result['updated'],
				implode(', ', $result['updatedCodes'])
			);
		} else {
			$lines[] = 'ℹ️ Không có trường hợp nào được cập nhật.';
		}
		if ($result['alreadyPaid'] > 0) {
			$lines[] = sprintf('ℹ️ <b>%d</b> trường hợp đã nộp phí từ trước (bỏ qua).', $result['alreadyPaid']);
		}
		if ($result['notFound'] > 0) {
			$lines[] = sprintf('ℹ️ <b>%d</b> giao dịch không tìm thấy mã nộp tiền tương ứng.', $result['notFound']);
		}
		if (!empty($result['amountMismatch'])) {
			$lines[] = sprintf('⚠️ <b>%d</b> trường hợp <b>sai số tiền</b>, chưa cập nhật:', count($result['amountMismatch']));
			foreach ($result['amountMismatch'] as $item) {
				$lines[] = sprintf(
					'&nbsp;&nbsp;• <code>%s</code> (%s) — Cần: <b>%s đ</b>, Thực nhận: <b>%s đ</b>',
					htmlspecialchars($item['payment_code']),
					htmlspecialchars($item['learner_code']),
					number_format($item['expected'], 0, ',', '.'),
					number_format($item['actual'], 0, ',', '.')
				);
			}
		}
		if (!empty($result['duplicate'])) {
			$lines[] = sprintf('⚠️ <b>%d</b> mã nộp tiền xuất hiện nhiều lần (cần xử lý thủ công):', count($result['duplicate']));
			foreach ($result['duplicate'] as $item) {
				$lines[] = sprintf(
					'&nbsp;&nbsp;• <code>%s</code> (%s) — %d lần',
					htmlspecialchars($item['payment_code']),
					htmlspecialchars($item['learner_code']),
					$item['count']
				);
			}
		}

		return implode('<br>', $lines);
	}

}
