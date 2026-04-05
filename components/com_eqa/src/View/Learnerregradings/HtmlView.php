<?php

namespace Kma\Component\Eqa\Site\View\Learnerregradings;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Kma\Component\Eqa\Administrator\DataObject\ExamseasonInfo;
use Kma\Component\Eqa\Administrator\DataObject\LearnerInfo;
use Kma\Component\Eqa\Administrator\Enum\PpaaStatus;
use Kma\Component\Eqa\Administrator\Model\RegradingsModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

/**
 * View front-end danh sách yêu cầu phúc khảo của người học.
 *
 * Kể từ 2.0.7: RegradingsModel::initListQuery() đã join sẵn #__eqa_examseasons
 * và SELECT các cột payment_amount, payment_code, payment_completed,
 * bank_napas_code, bank_account_number, bank_account_owner — View chỉ cần
 * sử dụng trực tiếp mà không cần query thêm.
 *
 * @since 2.0.7
 */
class HtmlView extends ItemsHtmlView
{
    /** @var ExamseasonInfo|null */
    protected ?ExamseasonInfo $examseason = null;

    /** @var LearnerInfo|null */
    protected ?LearnerInfo $learner = null;

    /** @var string|null */
    protected ?string $errorMessage = null;

    // =========================================================================
    // Cấu hình cột
    // =========================================================================

    protected function configureItemFieldsForLayoutDefault(): void
    {
        $option = new ListLayoutItemFields();

        $option->sequence        = ListLayoutItemFields::defaultFieldSequence();
        $option->check           = ListLayoutItemFields::defaultFieldCheck();
        $option->customFieldset1 = [];

        $option->customFieldset1[] = new ListLayoutItemFieldOption('examName',  'Môn thi');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('origMark',  'Điểm gốc', false, false, 'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('ppaaMark',  'Điểm PK',  false, false, 'text-center');

        // Cột phí — raw HTML
        $feeField           = new ListLayoutItemFieldOption('paymentAmountHtml', 'Phí phúc khảo', false, false, 'text-center');
        $feeField->printRaw = true;
        $option->customFieldset1[] = $feeField;

        // Cột trạng thái nộp phí — raw HTML (badge + nút QR)
        $payField           = new ListLayoutItemFieldOption('paymentStatusHtml', 'Nộp phí', false, false, 'text-center');
        $payField->printRaw = true;
        $option->customFieldset1[] = $payField;

        $option->customFieldset1[] = new ListLayoutItemFieldOption('statusText',  'Trạng thái PK');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('description', 'Ghi chú');

        $this->itemFields = $option;
    }

    // =========================================================================
    // Chuẩn bị dữ liệu
    // =========================================================================

    protected function prepareDataForLayoutDefault(): void
    {
        try {
            $app = Factory::getApplication();

            /** @var RegradingsModel $model */
            $model = ComponentHelper::createModel('Regradings', 'Administrator');
            $this->setModel($model, true);

            // Xác định người học
            $learnerId = $app->input->getInt('learnerId');
            if (empty($learnerId)) {
                $learnerId = GeneralHelper::getSignedInLearnerId();
            }
            if (empty($learnerId)) {
                throw new Exception('Không xác định được thí sinh');
            }
            $model->setState('filter.learner_id', $learnerId);

            // Kiểm tra quyền
            if (!$model->canViewList()) {
                throw new Exception('Bạn không có quyền xem thông tin này');
            }

            // Gọi phương thức lớp cha (load items)
            parent::prepareDataForLayoutDefault();
            $this->layoutData->formHiddenFields['learnerId'] = $learnerId;

            // Lấy thông tin người học và kỳ thi
            $this->learner    = DatabaseHelper::getLearnerInfo($learnerId);
            $examseasonId     = $model->getSelectedExamseasonId();
            if (!empty($examseasonId)) {
                $this->examseason = DatabaseHelper::getExamseasonInfo($examseasonId);
            }

            // Xóa filter
            $model->setState('filter.learner_id', null);

            // ─── Tiền xử lý từng item ─────────────────────────────────────────
            if (!empty($this->layoutData) && !empty($this->layoutData->items)) {
                foreach ($this->layoutData->items as &$item) {

                    // ── Trạng thái phúc khảo ──────────────────────────────────
                    $status           = PpaaStatus::from($item->statusCode);
                    $item->statusText = $status->getLabel();
                    switch ($status) {
                        case PpaaStatus::Accepted:
                            $item->optionRowCssClass = 'table-primary';
                            break;
                        case PpaaStatus::Rejected:
                            $item->optionRowCssClass = 'table-danger';
                            break;
                        case PpaaStatus::Done:
                            $item->optionRowCssClass = 'table-success';
                            break;
                    }

                    // ── Thông tin thanh toán — đã có sẵn từ model ─────────────
                    $paymentAmount    = (int)  ($item->paymentAmount    ?? 0);
                    $paymentCompleted = (bool) ($item->paymentCompleted ?? false);
                    $paymentCode      = $item->paymentCode ?? null;

                    // Bank info — đã SELECT sẵn từ model (join #__eqa_examseasons)
                    $hasBankInfo = !empty($item->bankNapasCode)
                        && !empty($item->bankAccountNumber);

                    // Cột "Phí phúc khảo"
                    if ($paymentAmount <= 0) {
                        $item->paymentAmountHtml = '<span class="text-success fw-semibold">Miễn phí</span>';
                    } else {
                        $item->paymentAmountHtml = '<span class="text-danger fw-semibold">'
                            . htmlspecialchars(number_format($paymentAmount, 0, ',', '.'))
                            . '&nbsp;đ</span>';
                    }

                    // Cột "Nộp phí"
                    $modalId = 'qr-modal-regrading-' . (int) $item->id;

                    if ($paymentAmount <= 0 || $paymentCompleted) {
                        $item->paymentStatusHtml = '<span class="badge bg-success">'
                            . '<span class="icon-check me-1" aria-hidden="true"></span>'
                            . ($paymentCompleted ? 'Đã nộp' : 'Miễn phí')
                            . '</span>';
                    } elseif (!$hasBankInfo) {
                        $item->paymentStatusHtml = '<span class="badge bg-secondary">Chưa có TK ngân hàng</span>';
                    } else {
                        $item->paymentStatusHtml = '<button'
                            . ' type="button"'
                            . ' class="btn btn-sm btn-warning"'
                            . ' data-bs-toggle="modal"'
                            . ' data-bs-target="#' . $modalId . '">'
                            . '<span class="icon-credit-card me-1" aria-hidden="true"></span>'
                            . 'Nộp phí'
                            . '</button>';
                    }

                    // Đính kèm dữ liệu QR vào item để template render modal.
                    // Bank fields đã là property trực tiếp của $item từ model:
                    // $item->bankNapasCode, $item->bankAccountNumber, $item->bankAccountOwner
                    $item->_qrModalId        = $modalId;
                    $item->_paymentAmount    = $paymentAmount;
                    $item->_paymentCompleted = $paymentCompleted;
                    $item->_paymentCode      = $paymentCode;
                    $item->_hasBankInfo      = $hasBankInfo;
                }
                unset($item);
            }

			//Load QRCode JS
	        $this->wa->useScript('qrcode.script');

        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    // =========================================================================
    // Toolbar
    // =========================================================================

    protected function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title('Thông tin phúc khảo');
        if (!empty($this->errorMessage)) {
            return;
        }
        ToolbarHelper::deleteList('Bạn có chắc muốn xóa không?', 'learnerregradings.delete', 'Xóa yêu cầu');
        ToolbarHelper::render();
    }
}
