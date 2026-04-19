<?php

namespace Kma\Component\Eqa\Administrator\View\AssessmentLearners;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Enum\Anomaly;
use Kma\Component\Eqa\Administrator\Enum\AssessmentResultLevel;
use Kma\Component\Eqa\Administrator\Enum\AssessmentResultType;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Model\AssessmentLearnersModel;
use Kma\Component\Eqa\Administrator\Model\AssessmentModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\FormHelper;
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
	/** @var int ID của kỳ sát hạch */
	protected int $assessmentId;

    /** @var object|null Thông tin kỳ sát hạch (header). */
    protected ?object $assessment = null;

    /** @var object|null Số liệu thống kê tổng hợp. */
    protected ?object $statistics = null;

    /** @var bool Kỳ sát hạch còn được phép chỉnh sửa không (chưa kết thúc + chưa hoàn tất). */
    protected bool $isEditable = false;

    /** @var object|null Thống kê phục vụ layout distributerooms. */
    protected ?object $distributionStats = null;

    /** @var int[] Danh sách al.id được chọn cho layout distributerooms; [] = toàn bộ. */
    protected array $selectedIds = [];

    protected Form $setPaymentForm;
	protected Form $distributeToRoomsForm;
	protected Form $uploadStatementForm;
	protected Form $uploadItestResultForm;

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
		$user = Factory::getApplication()->getIdentity();

		$fields = new ListLayoutItemFields();

		$fields->sequence = ListLayoutItemFields::defaultFieldSequence();
		$fields->check    = ListLayoutItemFields::defaultFieldCheck();

		$fields->customFieldset1 = [];

		// Thông tin người học
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('learner_code',      'Mã HVSV',  true, false, 'text-center');
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('learner_lastname',   'Họ đệm');
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('learner_firstname',  'Tên', true);

		// Ca thi
		$f            = new ListLayoutItemFieldOption('examsession_name', 'Ca thi', true, false, 'text-center');
		$f->printRaw  = true;
		$fields->customFieldset1[] = $f;

		// Phòng thi
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('room_code', 'Phòng thi', false, false, 'text-center');

		// Số báo danh
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('examinee_code', 'SBD', true, false, 'text-center');


		if($user->authorise('eqa.assessment.payment','com_eqa'))
		{
			// Phí — payment_amount + payment_code
			$f           = new ListLayoutItemFieldOption('payment_info_html', 'Phí', false, false, 'text-center');
			$f->printRaw = true;
			$fields->customFieldset1[] = $f;

			// Trạng thái — payment_completed (nếu chưa hủy) hoặc cancelled (nếu đã hủy)
			$f           = new ListLayoutItemFieldOption('status_html', 'Trạng thái', true, false, 'text-center');
			$f->printRaw = true;
			$fields->customFieldset1[] = $f;
		}

		// Bất thường
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('anomaly_label', 'Bất thường', false, false, 'text-center');

		// Điểm TOEIC (thang 990) với hint điểm thành phần
		if($user->authorise('eqa.assessment.score', 'com_eqa'))
		{
			$f           = new ListLayoutItemFieldOption('score', 'Điểm', true, false, 'text-center');
			$f->printRaw = true;
			$fields->customFieldset1[] = $f;
		}

		// Đạt / Không
		$f           = new ListLayoutItemFieldOption('passed_badge_html', 'Đạt', false, false, 'text-center');
		$f->printRaw = true;
		$fields->customFieldset1[] = $f;

		// Cột kết quả kiểu cũ (score_display / level_label / passed_html) — bổ sung động
		// theo result_type trong prepareDataForLayoutDefault() nếu cần

		// Ngày tạo — kèm hint ngày sửa
		$f           = new ListLayoutItemFieldOption('created_at_html', 'Ngày tạo', true, false, '');
		$f->printRaw = true;
		$fields->customFieldset1[] = $f;

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

	    /**
	     * 2. Load thông tin kỳ sát hạch (dùng AssessmentModel)
	     * @var AssessmentModel $assessmentModel
	     */
        $assessmentModel  = ComponentHelper::createModel('Assessment');
        $this->assessment = $assessmentModel->getItem($assessmentId);
        if (empty($this->assessment)) {
            die('Không tìm thấy kỳ sát hạch có id = ' . $assessmentId . '.');
        }

	    /**
	     * 3. Set filter assessment_id cho list model TRƯỚC khi gọi parent
	     * @var AssessmentLearnersModel $listModel
	     */
        $listModel = $this->getModel();
        $listModel->setState('filter.assessment_id', $assessmentId);

        // 4. Gọi parent để nạp items, pagination, filterForm, activeFilters
        parent::prepareDataForLayoutDefault();

        // 5. Số liệu thống kê (tính sau khi state đã được set)
        $this->statistics = $listModel->getStatistics();

        // 5b. Kiểm tra kỳ sát hạch còn được phép chỉnh sửa không
        $this->isEditable = $listModel->isAssessmentEditable($assessmentId);

        // 6. Ghim assessment_id vào formActionParams để pagination/filter giữ đúng context
	    //    và vào hidden fiels để lấy qua POST khi cần
        $this->layoutData->formActionParams = [
            'view'          => 'assessmentlearners',
            'assessment_id' => $assessmentId,
        ];
		$this->layoutData->formHiddenFields = [
			'assessment_id' => $assessmentId,
		];

        // 7. Bổ sung cột kết quả động theo result_type
        $this->addResultColumns((int) $this->assessment->result_type);

        // 8. Preprocessing từng item
        if (!empty($this->layoutData->items)) {
	        // --- Xây dựng map examsession_id → badge class trước vòng lặp chính ---
	        // Mỗi ca thi (duy nhất theo examsession_id) được gán một màu badge Bootstrap
	        // khác nhau, xoay vòng trong tập màu theo thứ tự xuất hiện đầu tiên.
	        $sessionBadgePalette = [
		        'bg-primary',
		        'bg-danger',
		        'bg-secondary',
		        'bg-warning text-dark',
		        'bg-success',
		        'bg-dark',
		        'bg-info text-dark',
	        ];
	        $sessionColorMap = [];  // examsession_id (int) → badge css class (string)
	        foreach ($this->layoutData->items as $item) {
		        $sid = (int) ($item->examsession_id ?? 0);
		        if ($sid > 0 && !isset($sessionColorMap[$sid])) {
			        //$idx               = count($sessionColorMap) % count($sessionBadgePalette);
			        $idx               = $sid % count($sessionBadgePalette);
			        $sessionColorMap[$sid] = $sessionBadgePalette[$idx];
		        }
	        }

	        foreach ($this->layoutData->items as &$item) {
		        // ------------------------------------------------------------------
		        // Ca thi — badge màu phân biệt theo từng ca thi
		        // ------------------------------------------------------------------
		        if (!empty($item->examsession_name)) {
			        $sid        = (int) ($item->examsession_id ?? 0);
			        $badgeClass = $sessionColorMap[$sid] ?? 'bg-secondary';
			        $item->examsession_name = '<span class="badge ' . $badgeClass . '">'
				        . htmlspecialchars($item->examsession_name) . '</span>';
		        } else {
			        $item->examsession_name = '<span class="text-muted">—</span>';
		        }

		        // ------------------------------------------------------------------
		        // Phí — payment_amount + payment_code (2 dòng)
		        // ------------------------------------------------------------------
		        $amount = (int) $item->payment_amount;
		        if ($amount <= 0) {
			        $item->payment_info_html = '<span class="badge bg-secondary">Miễn phí</span>';
		        } else {
			        $feeLabel  = '<span class="badge bg-warning text-dark">'
				        . number_format($amount, 0, ',', '.') . ' đ</span>';
			        $codeLabel = !empty($item->payment_code)
				        ? '<code class="small">' . htmlspecialchars($item->payment_code) . '</code>'
				        : '<span class="text-muted small">—</span>';

			        $item->payment_info_html = $feeLabel . '<br>' . $codeLabel;
		        }

		        // ------------------------------------------------------------------
		        // Trạng thái — cancelled có ưu tiên; nếu không thì hiện payment_completed
		        // ------------------------------------------------------------------
		        if ($item->cancelled) {
			        $item->status_html = '<span class="badge bg-danger">Đã hủy</span>';
		        } elseif ($amount <= 0) {
			        // Miễn phí: không có thông tin nộp phí để hiển thị
			        $item->status_html = '';
		        } else {
			        $item->status_html = $item->payment_completed
				        ? '<span class="badge bg-success">Đã nộp</span>'
				        : '<span class="badge bg-danger">Chưa nộp</span>';
		        }

		        // ------------------------------------------------------------------
		        // Bất thường
		        // ------------------------------------------------------------------
		        $item->anomaly_label = ($item->anomaly != Anomaly::None->value)
			        ? Anomaly::from($item->anomaly)->getLabel()
			        : '';

		        // ------------------------------------------------------------------
		        // Điểm TOEIC (thang 990) + hint điểm thành phần
		        // ------------------------------------------------------------------
		        if ($item->score !== null) {
			        $scoreInt  = (int) round((float) $item->score);
			        $hintParts = [];

			        // Phân tích raw_result (JSON) để lấy điểm thành phần + ngưỡng
			        if (!empty($item->raw_result)) {
				        $raw = json_decode($item->raw_result, true);
				        if (is_array($raw)) {
					        if (isset($raw['listening_raw'], $raw['listening_scaled'])) {
						        $hintParts[] = 'Listening: ' . $raw['listening_raw']
							        . ' câu → ' . $raw['listening_scaled'] . ' điểm';
					        }
					        if (isset($raw['reading_raw'], $raw['reading_scaled'])) {
						        $hintParts[] = 'Reading: ' . $raw['reading_raw']
							        . ' câu → ' . $raw['reading_scaled'] . ' điểm';
					        }
					        if (isset($raw['total'])) {
						        $hintParts[] = 'Tổng: ' . (int) $raw['total'] . '/990';
					        }
					        if (isset($raw['passing_score'])) {
						        $hintParts[] = 'Ngưỡng đạt: ' . (int) $raw['passing_score'];
					        }
				        }
			        }

			        $hint = !empty($hintParts)
				        ? ' title="' . htmlspecialchars(implode(' | ', $hintParts)) . '"'
				        : '';

			        $item->score = '<span class="fw-semibold"' . $hint . '>'
				        . $scoreInt . '</span>';
		        } else {
			        $item->score = '<span class="text-muted">—</span>';
		        }

		        // ------------------------------------------------------------------
		        // Đạt / Không (passed_badge_html) — boolean đơn giản
		        // ------------------------------------------------------------------
		        if ($item->passed === null) {
			        $item->passed_badge_html = '<span class="text-muted">—</span>';
		        } elseif ((bool) $item->passed) {
			        $item->passed_badge_html = '<span class="badge bg-success">Đạt</span>';
		        } else {
			        $item->passed_badge_html = '<span class="badge bg-danger">Không</span>';
		        }

		        // ------------------------------------------------------------------
		        // Kết quả theo result_type cũ (score_display, level_label, passed_html)
		        // — giữ lại để addResultColumns() vẫn hoạt động bình thường
		        // ------------------------------------------------------------------
		        $item->score_display = ($item->score !== null)
			        ? number_format((float) $item->score, 0)
			        : '—';

		        if (isset($item->level) && $item->level !== null) {
			        $levelEnum         = AssessmentResultLevel::tryFrom((int) $item->level);
			        $item->level_label = $levelEnum?->getLabel() ?? '—';
		        } else {
			        $item->level_label = '—';
		        }

		        if ($item->passed === null) {
			        $item->passed_html = '<span class="text-muted">—</span>';
		        } elseif ((bool) $item->passed) {
			        $item->passed_html = '<span class="badge bg-success">Đạt</span>';
		        } else {
			        $item->passed_html = '<span class="badge bg-danger">Không đạt</span>';
		        }

		        // ------------------------------------------------------------------
		        // Ngày tạo — kèm hint ngày sửa (modifiedAt)
		        // ------------------------------------------------------------------
		        $createdDisplay  = !empty($item->createdAt)  ? htmlspecialchars($item->createdAt)  : '—';
		        $modifiedDisplay = !empty($item->modifiedAt) ? htmlspecialchars($item->modifiedAt) : '—';
		        $hintModified    = 'Ngày sửa: ' . $modifiedDisplay;

		        $item->created_at_html = '<span title="' . $hintModified . '">'
			        . $createdDisplay . '</span>';
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
	    $assessmentId = (int) ($this->assessment->id ?? 0);

        ToolbarHelper::title($title);
        ToolbarHelper::appendGoHome();
	    // Nút quay về danh sách kỳ sát hạch
	    $backUrl = Route::_('index.php?option=com_eqa&view=assessments', false);
	    ToolbarHelper::appendLink('core.manage', $backUrl,'Kỳ sát hạch','arrow-up-2');

        if ($this->isEditable) {
            // Nút Thêm thí sinh
            $addUrl = Route::_(
                'index.php?option=com_eqa&view=assessmentlearners&layout=addlearners&assessment_id=' . $assessmentId,
                false
            );
            ToolbarHelper::appendLink('core.edit', $addUrl, 'Thêm thí sinh', 'plus');

	        // Nút Xóa thí sinh (yêu cầu chọn ít nhất 1 bản ghi)
	        ToolbarHelper::appendButton('core.edit','trash','Xóa thí sinh','assessmentlearners.removeLearners',true,'btn btn-danger');

	        // Nút Nhập sao kê
	        $importUrl = Route::_(
		        'index.php?option=com_eqa&view=assessmentlearners&layout=importstatement&assessment_id=' . $assessmentId,
		        false
	        );
	        ToolbarHelper::appendLink('eqa.assessment.payment', $importUrl, 'Nhập sao kê', 'file');

            // Nút Đổi trạng thái nộp phí (yêu cầu chọn ít nhất 1 bản ghi)
            ToolbarHelper::appendButton(
                'eqa.assessment.payment',
                'flag',
                'Đổi trạng thái nộp phí',
                'assessmentlearners.setPaymentInfo',
                true,
                'btn btn-primary'
            );
            // Nút Chia phòng thi (không yêu cầu chọn bắt buộc)
            ToolbarHelper::appendButton(
                'core.edit',
                'grid-2',
                'Chia phòng thi',
                'assessmentlearners.distributeRooms',
                false,
                'btn btn-secondary'
            );

	        // Nút Chia phòng thi cho thí sinh chưa được chia phòng (không cần checkbox)
	        ToolbarHelper::appendButton(
		        'core.edit',
		        'grid-2',
		        'Chia phòng (chưa chia)',
		        'assessmentlearners.distributeUnassignedRooms',
		        false,
		        'btn btn-secondary'
	        );

	        // Nút Xóa thông tin chia phòng thi (có xác nhận; không bắt buộc chọn)
	        ToolbarHelper::appendConfirmButton(
		        'core.edit',
		        'Bạn có chắc muốn xóa thông tin chia phòng thi? Các phòng thi rỗng sau khi xóa cũng sẽ bị xóa theo.',
		        'cancel-2',
		        'Xóa chia phòng',
		        'assessmentlearners.clearRoomAssignments',
		        false,
		        'btn btn-danger'
	        );
        }

	    // Nút Xuất ca iTest (luôn hiển thị, không cần quyền edit)
	    ToolbarHelper::appendButton('core.manage', 'download', 'Xuất ca iTest', 'assessmentlearners.exportItest');

	    //Nút Nhập điểm iTest
	    $importItestUrl = Route::_(
		    'index.php?option=com_eqa&view=assessmentlearners&layout=importitestresult&assessment_id=' . $assessmentId,
		    false
	    );
	    ToolbarHelper::appendLink('eqa.assessment.score', $importItestUrl, 'Nhập điểm', 'upload');

	    //Nút Báo cáo HĐ
	    ToolbarHelper::appendButton(
	        'eqa.assessment.score',
	        'download',
	        'Báo cáo HĐ',
	        'assessmentlearners.exportCouncilReport',
	    );

	    // Nút Thông báo SV
	    ToolbarHelper::appendButton(
	        'eqa.assessment.score',
	        'download',
	        'Thông báo SV',
	        'assessmentlearners.exportStudentNotification',
	    );

    }

    // =========================================================================
    // Layout: addlearners
    // =========================================================================

    /**
     * Chuẩn bị dữ liệu cho layout nhập danh sách thí sinh thủ công.
     *
     * @since 2.0.5
     */
    protected function prepareDataForLayoutAddlearners(): void
    {
        $assessmentId = Factory::getApplication()->input->getInt('assessment_id');
        if (empty($assessmentId)) {
            die('Không xác định được kỳ sát hạch.');
        }

        $assessmentModel  = ComponentHelper::createModel('Assessment');
        $this->assessment = $assessmentModel->getItem($assessmentId);
    }

    /**
     * @since 2.0.5
     */
    protected function addToolbarForLayoutAddlearners(): void
    {
        $title = 'Thêm thí sinh';
        if (!empty($this->assessment->title)) {
            $title .= ' — ' . $this->assessment->title;
        }
        ToolbarHelper::title($title);
        ToolbarHelper::save('assessmentlearners.addLearners');

        $assessmentId = (int) ($this->assessment->id ?? 0);
        $cancelUrl    = Route::_(
            'index.php?option=com_eqa&view=assessmentlearners&assessment_id=' . $assessmentId,
            false
        );
        ToolbarHelper::appendCancelLink($cancelUrl);
    }

	// =========================================================================
	// Layout: importstatement
	// =========================================================================

	/**
	 * Chuẩn bị dữ liệu cho layout nhập sao kê ngân hàng.
	 *
	 * @since 2.0.5
	 */
	protected function prepareDataForLayoutImportstatement(): void
	{
		$assessmentId = Factory::getApplication()->input->getInt('assessment_id');
		if (empty($assessmentId)) {
			die('Không xác định được kỳ sát hạch.');
		}

		/**
		 * @var AssessmentModel $assessmentModel
		 */
		$assessmentModel  = ComponentHelper::createModel('Assessment');
		$this->assessment = $assessmentModel->getItem($assessmentId);

		/**
		 * Load the upload form
		 * and then inject a hidden 'assessment_id' field into the form
		 */
		$this->uploadStatementForm = FormHelper::getBackendForm('eqa.assessmentlearners.importstatement','upload_statement.xml', []);
		$this->uploadStatementForm->setField(new \SimpleXMLElement('<field name="assessment_id" type="hidden" default="' . (int) $assessmentId . '" />'), null, true, 'upload');

	}

	/**
	 * @since 2.0.5
	 */
	protected function addToolbarForLayoutImportstatement(): void
	{
		ToolbarHelper::title('Nhập sao kê ngân hàng');
		ToolbarHelper::appendUpload('assessmentlearners.importStatement', 'Đối chiếu & Cập nhật', 'upload', 'core.edit', true);

		$assessmentId = (int) ($this->assessment->id ?? 0);
		$cancelUrl    = Route::_(
			'index.php?option=com_eqa&view=assessmentlearners&assessment_id=' . $assessmentId,
			false
		);
		ToolbarHelper::appendCancelLink($cancelUrl);
	}

	// =========================================================================
    // Layout: setpayment
    // =========================================================================

    /**
     * Chuẩn bị dữ liệu cho layout cập nhật thông tin thanh toán.
     *
     * @since 2.0.5
     */
    protected function prepareDataForLayoutSetpayment(): void
    {
        $id = Factory::getApplication()->input->getInt('id');
        if ($id <= 0) {
            die('ID bản ghi không hợp lệ.');
        }

        /** @var \Kma\Component\Eqa\Administrator\Model\AssessmentLearnersModel $model */
        $model      = $this->getModel();
        $this->item = $model->getItemById($id);

        // Load form XML và pre-fill giá trị hiện tại
        $this->setPaymentForm = FormHelper::getBackendForm(
            'com_eqa.assessmentlearner.setpayment',
            'setassessmentpayment.xml',
            []
        );

        $this->setPaymentForm->setValue('id',                null, $this->item->id);
        $this->setPaymentForm->setValue('payment_amount',    null, $this->item->payment_amount);
        $this->setPaymentForm->setValue('payment_completed', null, (int) $this->item->payment_completed);
        $this->setPaymentForm->setValue('note',              null, $this->item->note ?? '');
    }

    /**
     * @since 2.0.5
     */
    protected function addToolbarForLayoutSetpayment(): void
    {
        ToolbarHelper::title('Cập nhật thông tin thanh toán');
        ToolbarHelper::appendButton('core.edit', 'save', 'Lưu', 'assessmentlearners.savePaymentInfo', false, 'btn btn-success');

        $assessmentId = (int) ($this->item->assessment_id ?? 0);
        $cancelUrl    = Route::_(
            'index.php?option=com_eqa&view=assessmentlearners&assessment_id=' . $assessmentId,
            false
        );
        ToolbarHelper::appendCancelLink($cancelUrl);
    }

    // =========================================================================
    // Layout: distributerooms
    // =========================================================================

    /**
     * Chuẩn bị dữ liệu cho layout chia phòng thi.
     *
     * Nhận selectedIds từ request (được nhúng dưới dạng hidden fields bởi phase 1 controller),
     * load thống kê, tạo form XML.
     *
     * @since 2.0.5
     */
    protected function prepareDataForLayoutDistributerooms(): void
    {
        $app          = Factory::getApplication();
        $assessmentId = $app->input->getInt('assessment_id');
        if (empty($assessmentId)) {
            die('Không xác định được kỳ sát hạch.');
        }

        /** @var AssessmentModel $assessmentModel */
        $assessmentModel  = ComponentHelper::createModel('Assessment');
        $this->assessment = $assessmentModel->getItem($assessmentId);
        if (empty($this->assessment)) {
            die('Không tìm thấy kỳ sát hạch có id = ' . $assessmentId . '.');
        }

        // Lấy selectedIds được truyền từ controller phase 1 qua session
        $this->selectedIds = (array) $app->getUserState(
            'com_eqa.assessmentlearners.distributerooms.selectedIds.' . $assessmentId,
            []
        );

        /** @var AssessmentLearnersModel $listModel */
        $listModel = $this->getModel();
        $listModel->setState('filter.assessment_id', $assessmentId);

        $this->distributionStats = $listModel->getDistributionStats($assessmentId, $this->selectedIds);

        $this->distributeToRoomsForm = FormHelper::getBackendForm(
            'com_eqa.assessmentlearners.distributerooms',
            'assessmentlearners_distribution.xml'
        );
    }

    /**
     * @since 2.0.5
     */
    protected function addToolbarForLayoutDistributerooms(): void
    {
        $assessmentId = (int) ($this->assessment->id ?? 0);
        $title        = 'Chia phòng thi';
        if (!empty($this->assessment->title)) {
            $title .= ' — ' . $this->assessment->title;
        }
        ToolbarHelper::title($title);

        ToolbarHelper::appendButton(
            'core.edit',
            'save',
            'Lưu',
            'assessmentlearners.distributeRooms',
            false,
            'btn btn-success',
            true  // formValidate
        );

        $cancelUrl = Route::_(
            'index.php?option=com_eqa&view=assessmentlearners&assessment_id=' . $assessmentId,
            false
        );
        ToolbarHelper::appendCancelLink($cancelUrl);
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

	// =========================================================================
	// Layout: importitestresult
	// =========================================================================

	/**
	 * Chuẩn bị dữ liệu cho layout nhập điểm thi iTest.
	 *
	 * @since 2.0.8
	 */
	protected function prepareDataForLayoutImportitestresult(): void
	{
		$assessmentId = Factory::getApplication()->input->getInt('assessment_id');
		if (empty($assessmentId)) {
			die('Không xác định được kỳ sát hạch.');
		}

		/** @var AssessmentModel $assessmentModel */
		$assessmentModel  = ComponentHelper::createModel('Assessment');
		$this->assessment = $assessmentModel->getItem($assessmentId);

		// Load form upload và inject assessment_id
		$this->uploadItestResultForm = FormHelper::getBackendForm(
			'eqa.assessmentlearners.importitestresult',
			'upload_excelfile.xml',
			[]
		);
		$this->uploadItestResultForm->setField(
			new \SimpleXMLElement(
				'<field name="assessment_id" type="hidden" default="' . (int) $assessmentId . '" />'
			),
			null,
			true,
			'upload'
		);
	}

	/**
	 * Toolbar cho layout nhập điểm thi iTest.
	 *
	 * @since 2.0.8
	 */
	protected function addToolbarForLayoutImportitestresult(): void
	{
		$title = 'Nhập điểm thi iTest';
		if (!empty($this->assessment->title)) {
			$title .= ' — ' . $this->assessment->title;
		}
		ToolbarHelper::title($title);
		ToolbarHelper::appendUpload(
			'assessmentlearners.importITestResult',
			'Nhập điểm',
			'upload',
			'core.edit',
			true
		);

		$assessmentId = (int) ($this->assessment->id ?? 0);
		$cancelUrl    = Route::_(
			'index.php?option=com_eqa&view=assessmentlearners&assessment_id=' . $assessmentId,
			false
		);
		ToolbarHelper::appendCancelLink($cancelUrl);
	}
}
