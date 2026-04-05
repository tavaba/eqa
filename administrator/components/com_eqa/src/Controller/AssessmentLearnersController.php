<?php

namespace Kma\Component\Eqa\Administrator\Controller;

defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Helper\IOHelper;
use Kma\Library\Kma\BankStatement\BankStatementHelper;
use Kma\Library\Kma\BankStatement\BankStatementImportResultHelper;
use Kma\Library\Kma\Controller\AdminController;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Component\Eqa\Administrator\Model\AssessmentLearnersModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Items Controller cho danh sách thí sinh sát hạch.
 *
 * Xử lý các tác vụ:
 *   - addLearners         : POST — lưu danh sách thí sinh thêm thủ công.
 *   - setPaymentInfo      : POST 1 — nhận id từ checkbox, redirect sang layout setpayment.
 *   - savePaymentInfo     : POST 2 — lưu thông tin thanh toán đã nhập.
 *
 * @since 2.0.5
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
     * @since 2.0.5
     */
    public function addLearners(): void
    {
        $this->checkToken();

        $assessmentId = $this->input->getInt('assessment_id');
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
            $model = ComponentHelper::createModel('AssessmentLearners');

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
	// removeLearners — xóa thí sinh khỏi kỳ sát hạch
	// =========================================================================

	/**
	 * Xóa hẳn các thí sinh được chọn khỏi kỳ sát hạch.
	 * Yêu cầu chọn ít nhất 1 bản ghi (listCheck = true).
	 *
	 * @since 2.0.5
	 */
	public function removeLearners(): void
	{
		$assessmentId = $this->input->getInt('assessment_id', 0);
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

			$ids = array_values(array_filter(
				(array) $this->input->post->get('cid', [], 'int')
			));

			if (empty($ids)) {
				throw new Exception('Vui lòng chọn ít nhất một thí sinh để xóa.');
			}

			/** @var AssessmentLearnersModel $model */
			$model = ComponentHelper::createModel('AssessmentLearners');

			if (!$model->isAssessmentEditable($assessmentId)) {
				throw new Exception(
					'Kỳ sát hạch đã kết thúc hoặc đã được đánh dấu hoàn tất — không thể xóa thí sinh.'
				);
			}

			$operatorId = (int) $this->app->getIdentity()->id;
			$deleted    = $model->removeLearners($assessmentId, $ids, $operatorId);

			$this->setMessage(
				sprintf('Đã xóa <b>%d</b> thí sinh khỏi kỳ sát hạch.', $deleted),
				'success'
			);

		} catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
		}
	}
	public function delete()
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

			if ($assessmentId <= 0) {
				throw new Exception('Kỳ sát hạch không hợp lệ.');
			}

			$ids = array_values(array_filter(
				(array) $this->input->post->get('cid', [], 'int')
			));

			if (empty($ids)) {
				throw new Exception('Vui lòng chọn ít nhất một thí sinh để xóa.');
			}

			/** @var AssessmentLearnersModel $model */
			$model = ComponentHelper::createModel('AssessmentLearners');

			if (!$model->isAssessmentEditable($assessmentId)) {
				throw new Exception(
					'Kỳ sát hạch đã kết thúc hoặc đã được đánh dấu hoàn tất — không thể xóa thí sinh.'
				);
			}

			$operatorId = (int) $this->app->getIdentity()->id;
			$deleted    = $model->removeLearners($assessmentId, $ids, $operatorId);

			$this->setMessage(
				sprintf('Đã xóa <b>%d</b> thí sinh khỏi kỳ sát hạch.', $deleted),
				'success'
			);

		} catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
			if($assessmentId<=0)
			{
				$listUrl = Route::_('index.php?option=com_eqa&view=assessments',false);
				$this->setRedirect($listUrl);
			}
		}
	}

	// =========================================================================
    // setPaymentInfo — POST 1: nhận checkbox, redirect sang layout setpayment
    // =========================================================================

    /**
     * POST 1: Nhận danh sách id được chọn, lấy id đầu tiên,
     * redirect đến layout 'setpayment'.
     *
     * @since 2.0.5
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
            $model = ComponentHelper::createModel('AssessmentLearners');
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
	// distributeRooms — chia phòng thi (2 phase)
	// =========================================================================

	/**
	 * Phase 1: Nhận cid[] từ form list, lưu vào session, redirect sang layout distributerooms.
	 * Phase 2: Nhận jform từ layout distributerooms, gọi model, redirect về list.
	 *
	 * Phase được xác định qua hidden field 'phase':
	 *   - Không có / giá trị khác 'getdata' → phase 1 (showform)
	 *   - 'getdata'                           → phase 2 (process)
	 *
	 * @since 2.0.5
	 */
	public function distributeRooms(): void
	{
		$assessmentId = $this->input->getInt('assessment_id', 0);
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

			/** @var AssessmentLearnersModel $model */
			$model = ComponentHelper::createModel('AssessmentLearners');

			if (!$model->isAssessmentEditable($assessmentId)) {
				throw new Exception(
					'Kỳ sát hạch đã kết thúc hoặc đã được đánh dấu hoàn tất — không thể chia phòng thi.'
				);
			}

			$phase = $this->input->getAlnum('phase', '');

			// ------------------------------------------------------------------
			// PHASE 1: lưu selectedIds vào session, redirect sang layout
			// ------------------------------------------------------------------
			if ($phase !== 'getdata') {
				$selectedIds = array_values(array_filter(
					(array) $this->input->post->get('cid', [], 'int')
				));

				// Lưu vào session (key gắn assessment_id để tránh xung đột)
				$this->app->setUserState(
					'com_eqa.assessmentlearners.distributerooms.selectedIds.' . $assessmentId,
					$selectedIds
				);

				$this->setRedirect(Route::_(
					'index.php?option=com_eqa&view=assessmentlearners' .
					'&layout=distributerooms&assessment_id=' . $assessmentId,
					false
				));
				return;
			}

			// ------------------------------------------------------------------
			// PHASE 2: xử lý form, gọi model
			// ------------------------------------------------------------------
			$this->checkToken();

			$data = $this->input->get('jform', [], 'array');

			// Lấy selectedIds từ session (đã được lưu ở phase 1)
			$selectedIds = array_values(array_filter(array_map(
				'intval',
				(array) $this->app->getUserState(
					'com_eqa.assessmentlearners.distributerooms.selectedIds.' . $assessmentId,
					[]
				)
			)));

			// Xóa session sau khi đã lấy
			$this->app->setUserState(
				'com_eqa.assessmentlearners.distributerooms.selectedIds.' . $assessmentId,
				null
			);

			$operatorId = (int) $this->app->getIdentity()->id;
			$model->setState('filter.assessment_id', $assessmentId);
			$model->distributeAssessmentLearners($assessmentId, $data, $selectedIds, $operatorId);

			$scopeLabel = empty($selectedIds)
				? 'toàn bộ thí sinh'
				: count($selectedIds) . ' thí sinh được chọn';

			$this->setMessage(
				sprintf('Đã chia phòng thi và đánh số báo danh thành công cho %s.', $scopeLabel),
				'success'
			);

		} catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
		}
	}

	// =========================================================================
	// distributeUnassignedRooms — chia phòng cho thí sinh chưa được chia phòng
	// =========================================================================

	/**
	 * Tương tự distributeRooms, nhưng phạm vi thí sinh là toàn bộ thí sinh
	 * chưa được chia phòng (examroom_id IS NULL) của kỳ sát hạch.
	 * Không phụ thuộc vào checkbox người dùng chọn trước khi bấm nút.
	 *
	 * Phase 1: Query lấy danh sách id thí sinh chưa chia phòng, lưu session,
	 *           redirect sang layout distributerooms.
	 * Phase 2: Giống hệt distributeRooms phase 2.
	 *
	 * @since 2.0.5
	 */
	public function distributeUnassignedRooms(): void
	{
		$assessmentId = $this->input->getInt('assessment_id', 0);
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

			/** @var AssessmentLearnersModel $model */
			$model = ComponentHelper::createModel('AssessmentLearners');

			if (!$model->isAssessmentEditable($assessmentId)) {
				throw new Exception(
					'Kỳ sát hạch đã kết thúc hoặc đã được đánh dấu hoàn tất — không thể chia phòng thi.'
				);
			}

			$phase = $this->input->getAlnum('phase', '');

			// ------------------------------------------------------------------
			// PHASE 1: lấy danh sách thí sinh chưa chia phòng, lưu session, redirect
			// ------------------------------------------------------------------
			if ($phase !== 'getdata') {
				$unassignedIds = $model->getUnassignedIds($assessmentId);

				if (empty($unassignedIds)) {
					throw new Exception('Tất cả thí sinh của kỳ sát hạch này đã được chia phòng thi.');
				}

				// Lưu vào session (dùng cùng key với distributeRooms để tái dụng layout)
				$this->app->setUserState(
					'com_eqa.assessmentlearners.distributerooms.selectedIds.' . $assessmentId,
					$unassignedIds
				);

				$this->setRedirect(Route::_(
					'index.php?option=com_eqa&view=assessmentlearners' .
					'&layout=distributerooms&assessment_id=' . $assessmentId,
					false
				));
				return;
			}

			// ------------------------------------------------------------------
			// PHASE 2: xử lý form (giống distributeRooms)
			// ------------------------------------------------------------------
			$this->checkToken();

			$data = $this->input->get('jform', [], 'array');

			$selectedIds = array_values(array_filter(array_map(
				'intval',
				(array) $this->app->getUserState(
					'com_eqa.assessmentlearners.distributerooms.selectedIds.' . $assessmentId,
					[]
				)
			)));

			$this->app->setUserState(
				'com_eqa.assessmentlearners.distributerooms.selectedIds.' . $assessmentId,
				null
			);

			$operatorId = (int) $this->app->getIdentity()->id;
			$model->setState('filter.assessment_id', $assessmentId);
			$model->distributeAssessmentLearners($assessmentId, $data, $selectedIds, $operatorId);

			$this->setMessage(
				sprintf(
					'Đã chia phòng thi và đánh số báo danh thành công cho %d thí sinh chưa được chia phòng.',
					count($selectedIds)
				),
				'success'
			);

		} catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
		}
	}

	// =========================================================================
	// clearRoomAssignments — xóa thông tin chia phòng thi
	// =========================================================================

	/**
	 * Xóa thông tin chia phòng thi (examroom_id, code) của thí sinh.
	 * - Nếu có thí sinh được chọn (cid[]) → chỉ xóa thí sinh được chọn.
	 * - Nếu không có thí sinh được chọn → xóa toàn bộ thí sinh của kỳ sát hạch.
	 * Sau khi xóa, tự động dọn các phòng thi rỗng trong bảng #__eqa_examrooms.
	 *
	 * @since 2.0.5
	 */
	public function clearRoomAssignments(): void
	{
		$assessmentId = $this->input->getInt('assessment_id', 0);
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
			$model = ComponentHelper::createModel('AssessmentLearners');

			if (!$model->isAssessmentEditable($assessmentId)) {
				throw new Exception(
					'Kỳ sát hạch đã kết thúc hoặc đã được đánh dấu hoàn tất — không thể xóa thông tin chia phòng thi.'
				);
			}

			// Lấy danh sách id được chọn (nếu có)
			$scopeIds = array_values(array_filter(
				(array) $this->input->post->get('cid', [], 'int')
			));

			$operatorId = (int) $this->app->getIdentity()->id;
			$result     = $model->clearRoomAssignments($assessmentId, $scopeIds, $operatorId);

			$scopeLabel = empty($scopeIds)
				? 'toàn bộ thí sinh'
				: count($scopeIds) . ' thí sinh được chọn';

			$msg = sprintf(
				'Đã xóa thông tin chia phòng thi của %s (%d bản ghi được cập nhật).',
				$scopeLabel,
				$result['cleared']
			);

			if ($result['roomsDeleted'] > 0) {
				$msg .= sprintf(
					' Đã xóa %d phòng thi rỗng.',
					$result['roomsDeleted']
				);
			}

			$this->setMessage($msg, 'success');

		} catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
		}
	}

	// =========================================================================
    // savePaymentInfo — POST 2: lưu thông tin thanh toán
    // =========================================================================

    /**
     * POST 2: Tiếp nhận dữ liệu từ form layout 'setpayment',
     * gọi model cập nhật DB, redirect về list view với thông báo.
     *
     * @since 2.0.5
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
            $model = ComponentHelper::createModel('AssessmentLearners');

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
	 * @since 2.0.5
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
			$model = ComponentHelper::createModel('AssessmentLearners');
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
				BankStatementImportResultHelper::buildMessage($result, 'đã nộp phí sát hạch'),
				BankStatementImportResultHelper::getMessageType($result)
			);

		} catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
		}
	}

// =========================================================================
// exportItest — xuất ca thi iTest cho kỳ sát hạch
// =========================================================================

	/**
	 * Xuất file Excel "Ca thi iTest" cho một kỳ sát hạch.
	 *
	 * Phạm vi: tất cả thí sinh đã được đánh SBD (cancelled = 0,
	 * examroom_id IS NOT NULL, code IS NOT NULL) của kỳ sát hạch.
	 *
	 * Cấu trúc file giống hệt chức năng xuất ca iTest của ExamExaminees,
	 * dùng chung IOHelper::writeITestSheet().
	 *
	 * @since 2.0.5
	 */
	public function exportItest(): void
	{
		// 1. Kiểm tra CSRF token
		$this->checkToken();

		// 2. Kiểm tra quyền truy cập
		if (!$this->app->getIdentity()->authorise('core.manage', $this->option)) {
			$this->app->enqueueMessage('Bạn không có quyền thực hiện chức năng này.', 'error');
			$this->setRedirect(Route::_('index.php?option=com_eqa&view=assessments', false));
			return;
		}

		// 3. Xác định assessment_id
		$assessmentId = $this->input->getInt('assessment_id', 0);
		$listUrl      = Route::_(
			'index.php?option=com_eqa&view=assessmentlearners&assessment_id=' . $assessmentId,
			false
		);
		$this->setRedirect($listUrl);

		if ($assessmentId <= 0) {
			$this->setMessage('Không xác định được kỳ sát hạch.', 'error');
			return;
		}

		try {
			// 4. Lấy thông tin kỳ sát hạch (để đặt tên file)
			/** @var \Kma\Component\Eqa\Administrator\Model\AssessmentModel $assessmentModel */
			$assessmentModel = ComponentHelper::createModel('Assessment');
			$assessment      = $assessmentModel->getItem($assessmentId);

			if (empty($assessment)) {
				throw new Exception('Không tìm thấy kỳ sát hạch có id = ' . $assessmentId . '.');
			}

			// 5. Lấy dữ liệu thí sinh từ model
			/** @var AssessmentLearnersModel $model */
			$model = ComponentHelper::createModel('AssessmentLearners');
			$items = $model->getITestData($assessmentId);

			if (empty($items)) {
				throw new Exception('Không có thí sinh nào đã được đánh số báo danh trong kỳ sát hạch này.');
			}

			// 6. Tạo spreadsheet và ghi dữ liệu
			$spreadsheet = new Spreadsheet();
			$sheet       = $spreadsheet->getSheet(0);
			IOHelper::writeITestSheet($sheet, $items);

			// 7. Gửi file cho người dùng
			$fileName = 'Ca thi iTest. ' . ($assessment->title ?? ('assessment-' . $assessmentId)) . '.xlsx';
			IOHelper::sendHttpXlsx($spreadsheet, $fileName);
			$this->app->close();

		} catch (Exception $e) {
			$this->setMessage($e->getMessage(), 'error');
		}
	}
}
