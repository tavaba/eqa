<?php

namespace Kma\Component\Eqa\Site\View\Learnerretake;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
//use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Kma\Library\Kma\View\ItemHtmlView as BaseHtmlView;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Site\Model\LearnerretakeModel;
use Kma\Library\Kma\Helper\DatetimeHelper;

/**
 * View front-end cho chức năng "Thi lại".
 *
 * Hiển thị danh sách các môn thi lại (thi lần hai) của người học đang
 * đăng nhập, kèm thông tin điểm số, lệ phí và QR code thanh toán VietQR.
 *
 * Thông tin tài khoản nhận phí (NAPAS code, số tài khoản, tên người nhận)
 * và hạn chót nộp phí (deadline) được đọc từ params của menu item hiện tại
 * thay vì từ ConfigService toàn cục, cho phép mỗi menu item cấu hình riêng.
 *
 * @since 2.0.2
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * @var string|null  Mã người học đang đăng nhập; null nếu không phải người học.
	 * @since 2.0.3
	 */
    protected ?string $learnerCode = null;

	/**
	 * @var object|null  Thông tin người học (LearnerInfo).
	 * @since 2.0.3
	 */
    protected ?object $learner = null;

	/**
	 * @var object[]  Danh sách môn thi lại (đã tính phí, kết luận).
	 * @since 2.0.3
	 */
    protected array $items = [];

	/**
	 * @var string|null  Thông báo lỗi (nếu có).
	 * @since 2.0.3
	 */
    protected ?string $errorMessage = null;

    // ── Thông tin VietQR — đọc từ params menu item ──────────────────────────

	/**
	 * @var string  Mã NAPAS của ngân hàng nhận.
	 * @since 2.0.3
	 */
    protected string $bankNapasCode = '';

	/**
	 * @var string  Số tài khoản nhận.
	 * @since 2.0.3
	 */
    protected string $bankAccount = '';

	/**
	 * @var string  Tên người nhận tiền.
	 * @since 2.0.3
	 */
    protected string $recipientName = '';

    // ── Thông tin deadline ──────────────────────────────────────────────────
	/**
	 * Thời điểm bắt đầu thu phí dạng local time (để hiển thị).
	 * Null nếu menu item không cấu hình open_from.
	 *
	 * @var string|null
	 * @since 2.0.3
	 */
	protected ?string $openFromLocal = null;

	/**
	 * Cờ cho biết hiện tại chưa đến thời điểm bắt đầu thu phí.
	 * False nếu đã qua thời điểm bắt đầu hoặc không cấu hình open_from.
	 *
	 * @var bool
	 * @since 2.0.3
	 */
	protected bool $isBeforeOpeningTime = false;

    /**
     * Hạn chót nộp phí dạng local time (để hiển thị cho người học).
     * Null nếu menu item không cấu hình deadline.
     *
     * @var string|null
     * @since 2.0.3
     */
    protected ?string $deadlineLocal = null;

    /**
     * Cờ cho biết hiện tại đã quá hạn chót nộp phí hay chưa.
     * False nếu chưa quá hạn hoặc không cấu hình deadline.
     *
     * @var bool
     * @since 2.0.3
     */
    protected bool $isDeadlinePassed = false;

	/**
	 * Cờ cho biết cổng thu phí có đang mở không.
	 * True theo mặc định (mở), False khi admin tắt cổng thu phí.
	 *
	 * @var bool
	 * @since 2.0.3
	 */
	protected bool $paymentGateOpen = true;

	/**
	 * Thời điểm cập nhật sao kê gần nhất dạng UTC.
	 * Null nếu chưa được cấu hình.
	 *
	 * @var string|null
	 * @since 2.0.3
	 */
	protected ?string $lastStatementUpdate = null;

	/**
	 * Thời điểm cập nhật sao kê gần nhất dạng local time (để hiển thị).
	 * Null nếu chưa được cấu hình.
	 *
	 * @var string|null
	 * @since 2.0.3
	 */
	protected ?string $lastStatementUpdateLocal = null;

    public function display($tpl = null): void
    {
	    $this->wa->useScript('qrcode.script');
        $this->prepareData();
        $this->addToolbar();
	    parent::display($tpl);
    }

    /**
     * Chuẩn bị toàn bộ dữ liệu cần thiết cho template.
     *
     * @return void
     * @since 2.0.2
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

            // 5. Đọc cấu hình từ params của menu item hiện tại
            $this->loadMenuItemParams();
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Đọc các tham số cấu hình từ params của menu item đang active.
     *
     * Thay thế cách cũ dùng ConfigService() để lấy thông tin tài khoản nhận phí,
     * cho phép mỗi menu item "Thi lại" có cấu hình tài khoản và deadline riêng.
     *
     * Xử lý deadline:
     *   - Người quản trị nhập deadline theo giờ địa phương (local OS time) trong form menu.
     *   - Giá trị được lưu trong params JSON dưới dạng local time string.
     *   - Tại đây ta dùng DatetimeHelper::toUtc() để chuyển sang UTC và lưu vào
     *     $this->deadlineUtc để so sánh chính xác bất kể timezone máy chủ.
     *   - Đồng thời dùng DatetimeHelper::fromUtc() để tạo $this->deadlineLocal
     *     hiển thị ngược lại cho người học theo giờ địa phương.
     *
     * @return void
     * @throws Exception
     * @since 2.0.2
     */
    private function loadMenuItemParams(): void
    {
		$app = Factory::getApplication();
        $menuItem = $app->getMenu()->getActive();
		$userTimezone = $app->getIdentity()->getTimezone();

        if ($menuItem === null) {
            return;
        }

        $params = $menuItem->getParams();

        // ── Thông tin tài khoản nhận phí ────────────────────────────────────
        $this->bankNapasCode = (string) ($params->get('napas_code', ''));
        $this->bankAccount   = (string) ($params->get('account_number', ''));
        $this->recipientName = (string) ($params->get('recipient_name', ''));

        // ── Xử lý deadline ──────────────────────────────────────────────────
	    // Người dùng nhập deadline theo local time (User timezone).
	    // Chấp nhận: áp dụng bình đẳng cho mọi user, dù họ ở timezone nào
	    // Chuyển UTC ngược lại sang local time để hiển thị cho người học.
	    // (Đảm bảo hiển thị đúng dù máy chủ có thể chạy ở timezone khác.)
        $deadlineRaw = trim((string) $params->get('deadline', ''));
        if (!empty($deadlineRaw)) {
	        $this->deadlineLocal = $deadlineRaw;
	        $this->isDeadlinePassed = DatetimeHelper::isTimeOver($this->deadlineLocal, $userTimezone);
        }

		// ── Xử lý open_from (thời điểm bắt đầu thu phí) ────────────────────────
	    $openFromRaw = trim((string) $params->get('open_from', ''));
	    if (!empty($openFromRaw)) {
		    $this->openFromLocal       = $openFromRaw;
		    $this->isBeforeOpeningTime = !DatetimeHelper::isTimeOver($this->openFromLocal,$userTimezone);
	    }
		else
			$this->isBeforeOpeningTime = false;

		// ── Trạng thái cổng thu phí ─────────────────────────────────────────────
	    $this->paymentGateOpen = (bool) $params->get('payment_gate_open', 1);

	    // ── Thời điểm cập nhật sao kê gần nhất ──────────────────────────────────
	    $this->lastStatementUpdate = trim((string) $params->get('last_statement_update', ''));
	}

    /**
     * Thiết lập toolbar front-end.
     *
     * @return void
     * @since 2.0.2
     */
    private function addToolbar(): void
    {
        ToolbarHelper::title('Thi lại');
    }
}
