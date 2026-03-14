<?php

namespace Kma\Component\Eqa\Administrator\View\AssessmentLearners;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Enum\Anomaly;
use Kma\Component\Eqa\Administrator\Enum\AssessmentResultLevel;
use Kma\Component\Eqa\Administrator\Enum\AssessmentResultType;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

/**
 * View danh sách thí sinh (người học) của một kỳ sát hạch.
 *
 * URL truy cập: index.php?option=com_eqa&view=assessmentlearners&assessment_id=X
 *
 * @since 2.0.5
 */
class HtmlView extends ItemsHtmlView
{
    /** @var object|null Thông tin kỳ sát hạch (header). */
    protected ?object $assessment = null;

    /** @var object|null Số liệu thống kê tổng hợp. */
    protected ?object $statistics = null;

    // =========================================================================
    // Cấu hình cột
    // =========================================================================

    /**
     * Cấu hình các cột hiển thị trong bảng danh sách.
     * Các cột kết quả (score, level, passed) được xác định linh hoạt
     * dựa trên result_type của kỳ sát hạch — xem prepareDataForLayoutDefault().
     *
     * @since 2.0.5
     */
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $fields = new ListLayoutItemFields();

        $fields->sequence = ListLayoutItemFields::defaultFieldSequence();
        // Không có check vì view này không có tác vụ batch

        $fields->customFieldset1 = [];

        // Số báo danh
        $fields->customFieldset1[] = new ListLayoutItemFieldOption(
            'examinee_code', 'SBD', false, false, 'text-center'
        );

        // Thông tin người học
        $fields->customFieldset1[] = new ListLayoutItemFieldOption(
            'learner_code', 'Mã HVSV', true, false, 'text-center font-monospace'
        );
        $fields->customFieldset1[] = new ListLayoutItemFieldOption(
            'learner_fullname', 'Họ và tên'
        );

        // Phí
        $f = new ListLayoutItemFieldOption('payment_amount_html', 'Phí', false, false, 'text-end');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

        $f = new ListLayoutItemFieldOption('payment_completed_html', 'Nộp phí', false, false, 'text-center');
        $f->printRaw = true;
        $fields->customFieldset1[] = $f;

        // Bất thường
        $fields->customFieldset1[] = new ListLayoutItemFieldOption(
            'anomaly_label', 'Bất thường', false, false, 'text-center'
        );

        // Cột kết quả — sẽ được bổ sung động trong prepareDataForLayoutDefault()
        // tuỳ theo result_type của kỳ sát hạch

        $this->itemFields = $fields;
    }

    // =========================================================================
    // Chuẩn bị dữ liệu
    // =========================================================================

    /**
     * @since 2.0.5
     */
    protected function prepareDataForLayoutDefault(): void
    {
        // 1. Xác định assessment_id từ URL
        $assessmentId = Factory::getApplication()->input->getInt('assessment_id');
        if (empty($assessmentId)) {
            die('Không xác định được kỳ sát hạch.');
        }

        // 2. Load thông tin kỳ sát hạch (dùng AssessmentModel)
        $assessmentModel  = ComponentHelper::createModel('Assessment');
        $this->assessment = $assessmentModel->getItem($assessmentId);
        if (empty($this->assessment)) {
            die('Không tìm thấy kỳ sát hạch có id = ' . $assessmentId . '.');
        }

        // 3. Set filter assessment_id cho list model TRƯỚC khi gọi parent
        $listModel = $this->getModel();
        $listModel->setState('filter.assessment_id', $assessmentId);

        // 4. Gọi parent để nạp items, pagination, filterForm, activeFilters
        parent::prepareDataForLayoutDefault();

        // 5. Số liệu thống kê (tính sau khi state đã được set)
        $this->statistics = $listModel->getStatistics();

        // 6. Ghim assessment_id vào formActionParams để pagination/filter giữ đúng context
        $this->layoutData->formActionParams = [
            'view'          => 'assessmentlearners',
            'assessment_id' => $assessmentId,
        ];

        // 7. Bổ sung cột kết quả động theo result_type
        $this->addResultColumns((int) $this->assessment->result_type);

        // 8. Preprocessing từng item
        if (!empty($this->layoutData->items)) {
            foreach ($this->layoutData->items as &$item) {
                // Họ và tên đầy đủ
                $item->learner_fullname = trim(
                    ($item->learner_lastname ?? '') . ' ' . ($item->learner_firstname ?? '')
                ) ?: '—';

                // Phí
                $amount = (int) $item->payment_amount;
                if ($amount <= 0) {
                    $item->payment_amount_html    = '<span class="badge bg-secondary">Miễn phí</span>';
                    $item->payment_completed_html = '';
                } else {
                    $item->payment_amount_html = '<span class="badge bg-warning text-dark">'
                        . number_format($amount, 0, ',', '.') . ' đ</span>';

                    $item->payment_completed_html = $item->payment_completed
                        ? '<span class="icon-check text-success" aria-label="Đã nộp"></span>'
                        : '<span class="icon-times text-danger" aria-label="Chưa nộp"></span>';
                }

                // Bất thường
                $item->anomaly_label =  Anomaly::from($item->anomaly)->getLabel();

                // Kết quả — score
                if (isset($item->score) && $item->score !== null) {
                    $item->score_display = number_format((float) $item->score, 2);
                } else {
                    $item->score_display = '—';
                }

                // Kết quả — level
                if (isset($item->level) && $item->level !== null) {
                    $levelEnum         = AssessmentResultLevel::tryFrom((int) $item->level);
                    $item->level_label = $levelEnum?->getLabel() ?? '—';
                } else {
                    $item->level_label = '—';
                }

                // Kết quả — passed
                if ($item->passed === null) {
                    $item->passed_html = '<span class="text-muted">—</span>';
                } elseif ($item->passed) {
                    $item->passed_html = '<span class="badge bg-success">Đạt</span>';
                } else {
                    $item->passed_html = '<span class="badge bg-danger">Không đạt</span>';
                }
            }
            unset($item);
        }
    }

    // =========================================================================
    // Toolbar
    // =========================================================================

    /**
     * @since 2.0.5
     */
    protected function addToolbarForLayoutDefault(): void
    {
        $title = 'Danh sách thí sinh';
        if (!empty($this->assessment->title)) {
            $title .= ' — ' . $this->assessment->title;
        }

        ToolbarHelper::title($title);
        ToolbarHelper::appendGoHome();

        // Nút quay về danh sách kỳ sát hạch
        $backUrl = Route::_('index.php?option=com_eqa&view=assessments', false);
        ToolbarHelper::appendLink('core.manage', $backUrl, 'Kỳ sát hạch', 'arrow-up-2');
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Bổ sung các cột kết quả vào $this->itemFields dựa trên result_type.
     *
     * @param  int  $resultType  Giá trị của AssessmentResultType enum.
     * @since 2.0.5
     */
    private function addResultColumns(int $resultType): void
    {
        $fields = $this->itemFields;

        $resultTypeEnum = AssessmentResultType::tryFrom($resultType);

        switch ($resultTypeEnum) {
            case AssessmentResultType::Score:
                $fields->customFieldset1[] = new ListLayoutItemFieldOption(
                    'score_display', 'Điểm', false, false, 'text-center'
                );
                $f = new ListLayoutItemFieldOption('passed_html', 'Kết quả', false, false, 'text-center');
                $f->printRaw = true;
                $fields->customFieldset1[] = $f;
                break;

            case AssessmentResultType::Level:
                $fields->customFieldset1[] = new ListLayoutItemFieldOption(
                    'level_label', 'Bậc/Hạng', false, false, 'text-center'
                );
                $f = new ListLayoutItemFieldOption('passed_html', 'Kết quả', false, false, 'text-center');
                $f->printRaw = true;
                $fields->customFieldset1[] = $f;
                break;

            case AssessmentResultType::ScoreAndLevel:
                $fields->customFieldset1[] = new ListLayoutItemFieldOption(
                    'score_display', 'Điểm', false, false, 'text-center'
                );
                $fields->customFieldset1[] = new ListLayoutItemFieldOption(
                    'level_label', 'Bậc/Hạng', false, false, 'text-center'
                );
                $f = new ListLayoutItemFieldOption('passed_html', 'Kết quả', false, false, 'text-center');
                $f->printRaw = true;
                $fields->customFieldset1[] = $f;
                break;

            case AssessmentResultType::PassFail:
            default:
                $f = new ListLayoutItemFieldOption('passed_html', 'Kết quả', false, false, 'text-center');
                $f->printRaw = true;
                $fields->customFieldset1[] = $f;
                break;
        }
    }
}
