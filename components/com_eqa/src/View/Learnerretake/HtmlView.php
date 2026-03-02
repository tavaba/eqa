<?php

namespace Kma\Component\Eqa\Site\View\Learnerretake;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Service\ConfigService;
use Kma\Component\Eqa\Site\Model\LearnerretakeModel;

/**
 * View front-end cho chức năng "Thi lại".
 *
 * Hiển thị danh sách các môn thi lại (thi lần hai) của người học đang
 * đăng nhập, kèm thông tin điểm số, lệ phí và QR code thanh toán VietQR.
 *
 * @since 2.1.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var string|null  Mã người học đang đăng nhập; null nếu không phải người học. */
    protected ?string $learnerCode = null;

    /** @var object|null  Thông tin người học (LearnerInfo). */
    protected ?object $learner = null;

    /** @var object[]  Danh sách môn thi lại (đã tính phí, kết luận). */
    protected array $items = [];

    /** @var string|null  Thông báo lỗi (nếu có). */
    protected ?string $errorMessage = null;

    // Thông tin VietQR — được truyền sang template
    /** @var string  Mã Napas của ngân hàng nhận. */
    protected string $bankNapasCode = '';

    /** @var string  Số tài khoản nhận. */
    protected string $bankAccount = '';

    /**
     * {@inheritdoc}
     */
    public function display($tpl = null): void
    {
        $this->prepareData();
        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * Chuẩn bị toàn bộ dữ liệu cần thiết cho template.
     *
     * @return void
     * @since 2.1.0
     */
    private function prepareData(): void
    {
        try {
            // 1. Xác định learner code từ tài khoản đang đăng nhập
            $this->learnerCode = GeneralHelper::getSignedInLearnerCode();
            if ($this->learnerCode === null) {
                return; // Template sẽ hiển thị thông báo "chỉ dành cho sinh viên"
            }

            // 2. Lấy thông tin người học
            $this->learner = DatabaseHelper::getLearnerInfo($this->learnerCode);
            if (empty($this->learner)) {
                throw new Exception('Không tìm thấy thông tin người học với mã: ' . $this->learnerCode);
            }

            // 3. Lấy danh sách môn thi lại
            /** @var LearnerretakeModel $model */
            $model       = $this->getModel();
            $this->items = $model->getRetakeList($this->learnerCode);

            // 4. Tiền xử lý items: dịch conclusion → nhãn
            foreach ($this->items as $item) {
                if (!empty($item->conclusion)) {
                    $item->conclusionLabel = Conclusion::from((int) $item->conclusion)->getLabel();
                } else {
                    $item->conclusionLabel = '—';
                }
            }

            // 5. Đọc thông tin VietQR từ cấu hình
            $config              = new ConfigService();
            $this->bankNapasCode = $config->getBenificiaryBankNapasCode();
            $this->bankAccount   = $config->getBenificiaryBankAccount();

        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Thiết lập toolbar front-end.
     *
     * @return void
     * @since 2.1.0
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title('Thi lại');
    }
}
