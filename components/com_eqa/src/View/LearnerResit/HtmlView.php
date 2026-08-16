<?php

namespace Kma\Component\Eqa\Site\View\LearnerResit;

defined('_JEXEC') or die();

use Exception;
//use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Kma\Library\Kma\View\ItemHtmlView as BaseHtmlView;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Site\Model\LearnerresitModel;
use Kma\Library\Kma\Helper\DatetimeHelper;

/**
 * View front-end cho chức năng "Thi lại".
 *
 * Hiển thị danh sách các môn thi lại (thi lần hai) của người học đang
 * đăng nhập, kèm thông tin điểm số, lệ phí và QR code thanh toán VietQR.
 *
 * TỪ 2.1.8: thông tin tài khoản nhận phí (NAPAS code, số tài khoản, chủ tài
 * khoản), thời điểm mở/đóng cổng thu phí và mốc đối chiếu sao kê gần nhất được
 * đọc từ ĐỢT THI LẠI ĐANG KÍCH HOẠT mà người học có tên trong đó, thay vì từ
 * params của menu item. Nhờ vậy mỗi đợt có cấu hình thu phí riêng và người quản
 * trị không phải sửa menu mỗi khi mở một đợt mới; menu item chỉ còn nhiệm vụ
 * dẫn tới trang này.
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

	/**
	 * @var object|null  Đợt thi lại đang kích hoạt mà người học có tên trong đó.
	 * @since 2.1.8
	 */
    protected ?object $resit = null;

    // ── Thông tin VietQR — đọc từ đợt thi lại đang kích hoạt ─────────────────

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
            /** @var LearnerresitModel $model */
            $model       = $this->getModel();
            $this->items = $model->getResitList($this->learnerCode);
            $this->resit = $model->getActiveResit($this->learnerCode);

            // 4. Tiền xử lý items: dịch conclusion → nhãn
            foreach ($this->items as $item) {
                if (!empty($item->conclusion)) {
                    $item->conclusionLabel = Conclusion::from((int) $item->conclusion)->getLabel();
                } else {
                    $item->conclusionLabel = '—';
                }
            }

            // 5. Đọc cấu hình thu phí từ đợt thi lại đang kích hoạt
            $this->loadResitParams();
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Đọc các tham số cấu hình thu phí từ ĐỢT THI LẠI ĐANG KÍCH HOẠT.
     *
     * Thay thế cách cũ đọc từ params của menu item: từ 2.1.8 mỗi đợt thi lại
     * mang cấu hình thu phí của chính nó, nên người quản trị chỉ cần khai báo
     * một lần khi lập đợt.
     *
     * Xử lý thời gian (tuân thủ quy ước chung của hệ thống):
     *   - CSDL luôn lưu UTC (form nhập liệu ở back-end dùng filter="user_utc").
     *   - So sánh với thời điểm hiện tại được thực hiện trên trục UTC nên chính
     *     xác bất kể máy chủ chạy ở timezone nào.
     *   - Giá trị hiển thị cho người học được chuyển sang giờ địa phương.
     *
     * @return void
     * @throws Exception
     * @since 2.0.2
     */
    private function loadResitParams(): void
    {
        // Không có đợt đang kích hoạt (hoặc người học không thuộc đợt nào) →
        // giữ nguyên giá trị mặc định; template sẽ không hiển thị khối thanh toán.
        if ($this->resit === null) {
            $this->paymentGateOpen = false;

            return;
        }

        // ── Thông tin tài khoản nhận phí ────────────────────────────────────
        $this->bankNapasCode = (string) ($this->resit->bank_napas_code ?? '');
        $this->bankAccount   = (string) ($this->resit->bank_account_number ?? '');
        $this->recipientName = (string) ($this->resit->bank_account_owner ?? '');

        // ── Thời điểm bắt đầu thu phí ───────────────────────────────────────
        $openFromUtc = $this->normalizeUtc($this->resit->payment_open_from ?? null);

        if ($openFromUtc !== null) {
            $this->openFromLocal       = DatetimeHelper::convertToLocalTime($openFromUtc);
            $this->isBeforeOpeningTime = !DatetimeHelper::isTimeOver($openFromUtc, 'UTC');
        } else {
            $this->isBeforeOpeningTime = false;
        }

        // ── Hạn chót nộp phí ────────────────────────────────────────────────
        $deadlineUtc = $this->normalizeUtc($this->resit->payment_deadline ?? null);

        if ($deadlineUtc !== null) {
            $this->deadlineLocal    = DatetimeHelper::convertToLocalTime($deadlineUtc);
            $this->isDeadlinePassed = DatetimeHelper::isTimeOver($deadlineUtc, 'UTC');
        }

        // ── Trạng thái cổng thu phí ─────────────────────────────────────────
        $this->paymentGateOpen = (bool) ($this->resit->payment_gate_open ?? 0);

        // ── Thời điểm đối chiếu sao kê gần nhất ─────────────────────────────
        $lastUpdateUtc = $this->normalizeUtc($this->resit->last_statement_update ?? null);

        if ($lastUpdateUtc !== null) {
            $this->lastStatementUpdate      = $lastUpdateUtc;
            $this->lastStatementUpdateLocal = DatetimeHelper::convertToLocalTime($lastUpdateUtc);
        }
	}

	/**
	 * Chuẩn hóa một giá trị DATETIME đọc từ CSDL về chuỗi UTC dùng được, hoặc
	 * null nếu không có giá trị.
	 *
	 * Cần thiết vì cột DATETIME rỗng có thể về dưới dạng null, chuỗi rỗng hoặc
	 * 'null date' của CSDL ('0000-00-00 00:00:00').
	 *
	 * @param   string|null  $value
	 *
	 * @return  string|null
	 * @since   2.1.8
	 */
	private function normalizeUtc(?string $value): ?string
	{
		$value = trim((string) $value);

		if ($value === '' || str_starts_with($value, '0000-00-00')) {
			return null;
		}

		return $value;
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
