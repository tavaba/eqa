<?php

namespace Kma\Component\Eqa\Administrator\View\ResitExaminees;

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Enum\Anomaly;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Model\ResitModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\FormHelper;
use Kma\Library\Kma\Helper\StateHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

/**
 * View HTML cho danh sách thí sinh thi lần hai của MỘT đợt thi lại.
 *
 * Đợt được xác định bởi tham số 'resit_id' của request; tham số này được truyền
 * tiếp qua form (formActionParams/formHiddenFields) để mọi tác vụ trên thanh
 * công cụ đều biết mình đang làm việc với đợt nào.
 *
 * Nghiệp vụ nằm ở ResitModel (model của chính đợt thi lại); model của view này
 * chỉ lo phần truy vấn danh sách.
 *
 * @since 1.0
 */
class HtmlView extends ItemsHtmlView
{
	protected ?string $listModelName = 'ResitExaminees';

    /** @var object Số liệu thống kê tổng hợp */
    protected object $statistics;

    /**
     * Mã đợt thi lại đang mở.
     *
     * @var    int
     * @since  2.1.8
     */
    protected int $resitId = 0;

    /**
     * Thông tin đợt thi lại đang mở.
     *
     * @var    object|null
     * @since  2.1.8
     */
    protected ?object $resit = null;

    /**
     * Đợt đang mở có được phép thay đổi danh sách thí sinh hay không.
     *
     * Điều kiện: vừa ĐANG KÍCH HOẠT (active = 1) vừa ĐANG DÙNG
     * (state = published). Các nút làm thay đổi dữ liệu trên thanh công cụ chỉ
     * hiển thị khi cờ này bật (2.1.8).
     *
     * @var    bool
     * @since  2.1.8
     */
    protected bool $isModifiableResit = false;

    /**
     * Model của đợt thi lại — nơi chứa nghiệp vụ trên bảng thí sinh.
     *
     * @var    ResitModel|null
     * @since  2.1.8
     */
    private ?ResitModel $resitModel = null;

    protected function configureItemFieldsForLayoutDefault(): void
    {
        $option = new ListLayoutItemFields();

		$option->check = ListLayoutItemFields::defaultFieldCheck();
        $option->sequence = ListLayoutItemFields::defaultFieldSequence();

        $option->customFieldset1 = [];

        $f = new ListLayoutItemFieldOption('learner_code', 'Mã HVSV', true, false, 'text-center text-nowrap');
        $option->customFieldset1[] = $f;

        $option->customFieldset1[] = new ListLayoutItemFieldOption('learner_lastname', 'Họ đệm');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('learner_firstname', 'Tên', true);
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('subject_code', 'Mã môn', true);
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('subject_name', 'Tên môn');
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('academicyear', 'Năm học', false, false, 'text-center text-nowrap');
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('term', 'Học kỳ', false, false, 'text-center');
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('last_attempt', 'Đã thi', false, false, 'text-center');

	    $f = new ListLayoutItemFieldOption('is_debtor_label', 'Nợ phí', false, false, 'text-center');
		$f->printRaw = true; // Để in HTML badge
		$option->customFieldset1[] = $f;

	    $option->customFieldset1[] = new ListLayoutItemFieldOption('last_anomaly_label', 'Bất thường');
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('conclusion_label', 'Kết luận', false, false, 'text-center');

        $f = new ListLayoutItemFieldOption('payment_amount_label', 'Phí', false, false, 'text-center');
		$f->printRaw = true; // Để in HTML badge
        $option->customFieldset1[] = $f;

	    $option->customFieldset1[] = new ListLayoutItemFieldOption('payment_code', 'Mã CK', false, false, 'text-center text-nowrap font-monospace');

        // Cột "Đã nộp phí": giá trị đã được tiền xử lý thành HTML icon trong prepareData
        $f = new ListLayoutItemFieldOption('payment_completed_html', 'Đã nộp', false, false, 'text-center');
		$f->printRaw = true; // Để in HTML icon
        $option->customFieldset1[] = $f;

	    $f = new ListLayoutItemFieldOption('description', 'Mô tả');
        $option->customFieldset1[] = $f;
        $this->itemFields = $option;
    }

    protected function prepareDataForLayoutDefault(): void
    {
        // Xác định đợt thi lại đang mở, chốt chặn quyền, rồi đặt vào state của
        // list model TRƯỚC khi lớp cha nạp items (2.1.8)
        $this->resitId = $this->resolveResitId();
        $this->resit   = $this->getResitModel()->assertAccessible($this->resitId);

        // Chỉ đợt vừa đang kích hoạt vừa đang dùng mới cho phép thay đổi dữ liệu
        $this->isModifiableResit = !empty($this->resit->active)
            && (int) $this->resit->state === StateHelper::STATE_PUBLISHED;

        $this->getModel()->setState('filter.resit_id', $this->resitId);

        // Gọi phương thức lớp cha để nạp items, pagination, filterForm, activeFilters
        parent::prepareDataForLayoutDefault();

        // Giữ tham số đợt khi phân trang, sắp xếp, lọc và khi bấm các nút
        // trên thanh công cụ (các nút này submit chính form danh sách)
        $this->layoutData->formActionParams = [
            'view'    => 'ResitExaminees',
            'resit_id' => $this->resitId,
        ];
        $this->layoutData->formHiddenFields = ['resit_id' => $this->resitId];

        // Nạp số liệu thống kê
        $this->statistics = $this->getResitModel()->getStatistics($this->resitId);

        // Tiền xử lý từng bản ghi
        if (!empty($this->layoutData->items)) {
            foreach ($this->layoutData->items as $item) {
				$item->academicyear = DatetimeHelper::decodeAcademicYear($item->academicyear);
				// Nhãn "Nợ phí" từ Enum
	            $item->is_debtor_label = $item->is_debtor ? '<span class="badge bg-danger">Có</span>' : '<span class="badge bg-success">Không</span>';

				//Nhãn "Bất thường" từ Enum
	            $item->last_anomaly_label =
		            $item->last_anomaly==Anomaly::None->value
			            ? ''
			            : Anomaly::from($item->last_anomaly)->getLabel();

                // Nhãn kết luận từ Enum
                $conclusion             = Conclusion::from((int) $item->last_conclusion);
                $item->conclusion_label = $conclusion->getLabel();

                // Nhãn "Lệ phí": hiển thị số tiền hoặc "Miễn phí"
                $amount = (float) $item->payment_amount;
                if ($amount <= 0) {
                    $item->payment_amount_label = '<span class="badge bg-secondary">Miễn phí</span>';
                } else {
                    $item->payment_amount_label =
                        '<span class="badge bg-warning text-dark">'
                        . number_format($amount, 0, ',', '.') . ' đ'
                        . '</span>';
                }

                // Biểu tượng "Đã nộp phí" — theo chuẩn Joomla
                if ($amount <= 0) {
                    // Không cần đóng phí → để trống
                    $item->payment_completed_html = '';
                } else {
                    $item->payment_completed_html = $item->payment_completed
                        ? HTMLHelper::_('jgrid.published', 1, 0, '', false)
                        : HTMLHelper::_('jgrid.published', 0, 0, '', false);
                }
            }
        }
    }

    protected function addToolbarForLayoutDefault(): void
    {
        $resitName = $this->resit->name ?? 'Đợt thi lại';
        ToolbarHelper::title('Thí sinh thi lần 2 — ' . $resitName);
        ToolbarHelper::appendGoHome();

        // Quay về danh sách các đợt thi lại
        $resitsUrl = Route::_('index.php?option=com_eqa&view=Resits', false);
        ToolbarHelper::appendLink(null, $resitsUrl, 'Các danh sách', 'arrow-up-2');

        /*
         * Các chức năng LÀM THAY ĐỔI danh sách thí sinh chỉ dành cho đợt vừa
         * ĐANG KÍCH HOẠT vừa ĐANG DÙNG (2.1.8). Với đợt đã qua hoặc đã tạm
         * ngừng, chỉ còn các chức năng xem và xuất dữ liệu. Đây mới là lớp ẩn
         * giao diện — chốt chặn thực sự nằm ở ResitModel::assertModifiable().
         *
         * LƯU Ý: chức năng "Làm mới" (dựng lại toàn bộ danh sách) đã bị BỎ HẲN
         * từ 2.1.8 vì nó xóa mất cả những thí sinh đã dự thi khỏi đợt.
         */
        if ($this->isModifiableResit) {
            // Nút "Thêm thí sinh": bổ sung các trường hợp đủ điều kiện thi lần 2
            // mà đợt chưa có; không đụng tới bản ghi nào đang có.
            ToolbarHelper::appendButton(
                'core.create',
                'loop',
                'Thêm thí sinh',
                'resit.addNew',
                false,
                'btn btn-success'
            );

            // Nút "Xóa": loại thí sinh khỏi đợt (không xóa được người đã đóng phí)
            $msg = 'Các thí sinh được chọn sẽ bị xóa khỏi danh sách. Trường hợp đã đóng phí sẽ'
                . ' KHÔNG bị xóa. Hành động này không thể hoàn tác. Bạn có chắc muốn xóa?';
            ToolbarHelper::appendDelete('resit.deleteExaminees', 'Xóa', $msg);

            // Nút Nhập sao kê — chuyển sang layout importstatement của CHÍNH đợt này
            $importUrl = Route::_(
                'index.php?option=com_eqa&view=ResitExaminees&layout=importstatement&resit_id=' . $this->resitId,
                false
            );
            ToolbarHelper::appendLink('core.edit', $importUrl, 'Nhập sao kê', 'file');

            //Nút Đổi trạng thái nộp phí
            ToolbarHelper::appendButton(
                'core.edit',
                'flag',
                'Đổi trạng thái nộp phí',
                'resit.setPaymentStatus',
                true,
                'btn btn-primary'
            );
        }

		//Xuất danh sách đầy đủ ra Excel
	    \Kma\Library\Kma\Helper\ToolbarHelper::appendButton('download','Xuất danh sách đầy đủ','resit.exportFullList');

		//Xuất danh sách đã đóng phí ra Excel
	    \Kma\Library\Kma\Helper\ToolbarHelper::appendButton('download','Xuất danh sách đã đóng phí','resit.exportPaidList');
	}
	
    // =========================================================================
    // Layout: importstatement
    // =========================================================================

    /**
     * Chuẩn bị dữ liệu cho layout upload bản sao kê ngân hàng.
     *
     * @return void
     * @since 2.0.3
     */
    protected function prepareDataForLayoutImportstatement(): void
    {
        //Sao kê chỉ được đối chiếu trong phạm vi một đợt, và chỉ với đợt đang
        //kích hoạt (2.1.8)
        $this->resitId = $this->resolveResitId();
        $this->resit   = $this->getResitModel()->assertModifiable($this->resitId);

        $this->form = FormHelper::getBackendForm(
            'com_eqa.upload.statement',
            'upload_statement.xml',
            []
        );
    }

    /**
     * Toolbar cho layout importstatement.
     *
     * @return void
     * @since 2.0.3
     */
    protected function addToolbarForLayoutImportstatement(): void
    {
        ToolbarHelper::title('Nhập bản sao kê ngân hàng — ' . ($this->resit->name ?? ''));

        // Nút Submit form upload (formValidate = true để bắt required field)
        ToolbarHelper::appendUpload('resit.importStatement', 'Đối chiếu & Cập nhật', 'upload','core.edit', true);

        // Nút Hủy — quay về danh sách đang mở
        $cancelUrl = Route::_(
	        'index.php?option=com_eqa&view=ResitExaminees&resit_id=' . $this->resitId,
	        false
        );
        ToolbarHelper::appendCancelLink($cancelUrl);
    }

	/**
	 * Chuẩn bị dữ liệu cho layout 'setpayment'.
	 *
	 * Đọc id từ GET, load bản ghi từ model, load form XML,
	 * bind giá trị hiện tại vào form để pre-fill các trường.
	 *
	 * @return void
	 * @since 2.0.4
	 */
	protected function prepareDataForLayoutSetpayment(): void
	{
		$app = Factory::getApplication();
		$id = $app->input->getInt('id');

		if ($id <= 0) {
			die('ID bản ghi không hợp lệ. Vui lòng quay lại và thử lại.');
		}

		// Load thông tin bản ghi (dùng cho phần hiển thị thông tin thí sinh read-only)
		$this->item = $this->getResitModel()->getExamineeById($id);

		//Đợt chứa bản ghi — dùng để quay lại đúng đợt (2.1.8); đồng thời chốt chặn
		//quyền và trạng thái kích hoạt trước khi mở form cập nhật.
		$this->resitId = (int) $this->item->resit_id;
		$this->resit   = $this->getResitModel()->assertModifiable($this->resitId);

		// Load form XML và bind giá trị hiện tại vào form để pre-fill
		$this->form = FormHelper::getBackendForm(
			'com_eqa.resitexaminees.setpaymentstatus',
			'setPaymentStatus.xml',
			[]
		);

		$this->form->setValue('id',                null, $this->item->id);
		$this->form->setValue('payment_completed', null, (int) $this->item->payment_completed);
		$this->form->setValue('description',       null, $this->item->description ?? '');
	}

	/**
	 * Toolbar cho layout 'setpayment'.
	 *
	 * @return void
	 * @since 2.0.4
	 */
	protected function addToolbarForLayoutSetpayment(): void
	{
		ToolbarHelper::title('Cập nhật trạng thái nộp phí');

		ToolbarHelper::appendButton(
			'core.edit',
			'save',
			'Lưu',
			'resit.savePaymentStatus',
			false,
			'btn btn-success'
		);

		$cancelUrl = Route::_(
			'index.php?option=com_eqa&view=ResitExaminees&resit_id=' . $this->resitId,
			false
		);
		ToolbarHelper::appendCancelLink($cancelUrl);
	}

	// =========================================================================
	// Tiện ích
	// =========================================================================

	/**
	 * Model của đợt thi lại, khởi tạo một lần cho mỗi request.
	 *
	 * @return  ResitModel
	 * @since   2.1.8
	 */
	private function getResitModel(): ResitModel
	{
		if ($this->resitModel === null) {
			$this->resitModel = ComponentHelper::createModel('Resit');
		}

		return $this->resitModel;
	}

	/**
	 * Xác định mã đợt thi lại từ request.
	 *
	 * @return  int
	 * @since   2.1.8
	 */
	private function resolveResitId(): int
	{
		$resitId = (int) Factory::getApplication()->getInput()->getInt('resit_id');

		if ($resitId <= 0) {
			die(
				'Không xác định được đợt thi lại.'
				. ' Hãy mở đợt từ màn hình "Danh sách thi lần 2".'
			);
		}

		return $resitId;
	}
}
