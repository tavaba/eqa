<?php

namespace Kma\Component\Eqa\Site\View\AssessmentPortal;

defined('_JEXEC') or die();

use Exception;
use Kma\Library\Kma\View\ItemHtmlView as BaseHtmlView;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Site\Model\AssessmentPortalModel;

/**
 * View front-end trang Thi sát hạch.
 *
 * Hiển thị cho người học đang đăng nhập:
 *   - Các kỳ sát hạch "Đang/Sắp diễn ra": đăng ký, nộp phí, hủy đăng ký.
 *   - Các kỳ sát hạch "Đã tham gia": kết quả.
 *
 * @since 2.0.5
 */
class HtmlView extends BaseHtmlView
{
    /** @var string|null Mã người học đang đăng nhập (null nếu chưa đăng nhập hoặc không phải HVSV). */
    protected ?string $learnerCode = null;

    /** @var object|null Thông tin người học. */
    protected ?object $learner = null;

    /** @var object[]  Kỳ sát hạch đang/sắp diễn ra. */
    protected array $activeAssessments = [];

    /** @var object[]  Kỳ sát hạch đã tham gia. */
    protected array $pastAssessments = [];

    /** @var string|null Thông báo lỗi (nếu có). */
    protected ?string $errorMessage = null;

    // =========================================================================
    // Display
    // =========================================================================

    public function display($tpl = null): void
    {
	    $this->wa->useScript('qrcode.script');
	    $this->prepareData();
        parent::display($tpl);
    }

    // =========================================================================
    // Chuẩn bị dữ liệu
    // =========================================================================

    private function prepareData(): void
    {
        // 1. Xác định người học đang đăng nhập
        $this->learnerCode = GeneralHelper::getSignedInLearnerCode();
        if ($this->learnerCode === null) {
            return; // Template hiển thị thông báo đăng nhập
        }

        try {
            // 2. Thông tin cơ bản người học
            $this->learner = DatabaseHelper::getLearnerInfo($this->learnerCode);
            if (empty($this->learner)) {
                throw new Exception('Không tìm thấy thông tin người học: ' . $this->learnerCode);
            }

            // 3. Gọi Model lấy danh sách kỳ sát hạch
            /** @var AssessmentPortalModel $model */
            $model  = $this->getModel();
            $result = $model->getAssessmentsForLearner($this->learnerCode);

            $this->activeAssessments = $result->active;
            $this->pastAssessments   = $result->past;

        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }
}
