<?php

namespace Kma\Component\Eqa\Administrator\View\SecondAttempts;

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Model\SecondAttemptsModel;
use Kma\Library\Kma\Helper\FormHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

/**
 * View HTML cho danh sách thí sinh thi lần hai.
 *
 * @since 1.0
 */
class HtmlView extends ItemsHtmlView
{
    /** @var object Số liệu thống kê tổng hợp */
    protected object $statistics;

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
        // Gọi phương thức lớp cha để nạp items, pagination, filterForm, activeFilters
        parent::prepareDataForLayoutDefault();

        // Nạp số liệu thống kê
        /** @var \Kma\Component\Eqa\Administrator\Model\SecondAttemptsModel $model */
        $model            = $this->getModel();
        $this->statistics = $model->getStatistics();

        // Tiền xử lý từng bản ghi
        if (!empty($this->layoutData->items)) {
            foreach ($this->layoutData->items as $item) {
				// Nhãn "Nợ phí" từ Enum
	            $item->is_debtor_label = $item->is_debtor ? '<span class="badge bg-danger">Có</span>' : '<span class="badge bg-success">Không</span>';

				//Nhãn "Bất thường" từ Enum
	            $item->last_anomaly_label = $item->last_anomaly==ExamHelper::EXAM_ANOMALY_NONE ? '' : ExamHelper::getAnomaly($item->last_anomaly);

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
        ToolbarHelper::title('Danh sách thi lần 2');
        ToolbarHelper::appendGoHome();
        $msg = 'Làm mới danh sách: loại bỏ các trường hợp không còn hợp lệ và bổ sung các trường hợp mới';
        ToolbarHelper::appendConfirmButton('core.create', $msg, 'loop', 'Làm mới', 'secondattempts.refresh', false, null);

		//ToolbarHelper::deleteList('Bạn có chắc muốn xóa các bản ghi đã chọn? Hành động này không thể hoàn tác.', 'secondattempts.delete', 'Xóa');

        // Nút Nhập sao kê — chuyển sang layout importstatement
        $importUrl = Route::_('index.php?option=com_eqa&view=secondattempts&layout=importstatement', false);
        ToolbarHelper::appendLink('core.edit', $importUrl, 'Nhập sao kê', 'file');

		//Nút Đổi trạng thái nộp phí
	    ToolbarHelper::appendButton(
		    'core.edit',
		    'flag',
		    'Đổi trạng thái nộp phí',
		    'secondattempts.setPaymentStatus',
		    true,
		    'btn btn-primary'
	    );

		//Xuất danh sách đầy đủ ra Excel
	    \Kma\Library\Kma\Helper\ToolbarHelper::appendButton('download','Xuất danh sách đầy đủ','secondattempts.exportFullList');

		//Xuất danh sách đã đóng phí ra Excel
	    \Kma\Library\Kma\Helper\ToolbarHelper::appendButton('download','Xuất danh sách đã đóng phí','secondattempts.exportPaidList');
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
        ToolbarHelper::title('Nhập bản sao kê ngân hàng');

        // Nút Submit form upload (formValidate = true để bắt required field)
        ToolbarHelper::appendUpload('secondattempts.importStatement', 'Đối chiếu & Cập nhật', 'upload','core.edit', true);

        // Nút Hủy — quay về list view
        $cancelUrl = Route::_('index.php?option=com_eqa&view=secondattempts', false);
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
		/** @var SecondAttemptsModel $model */
		$model      = $this->getModel();
		$this->item = $model->getItemById($id);

		// Load form XML và bind giá trị hiện tại vào form để pre-fill
		$this->form = FormHelper::getBackendForm(
			'com_eqa.secondattempts.setpaymentstatus',
			'setpaymentstatus.xml',
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
			'secondattempts.savePaymentStatus',
			false,
			'btn btn-success'
		);

		$cancelUrl = Route::_('index.php?option=com_eqa&view=secondattempts', false);
		ToolbarHelper::appendCancelLink($cancelUrl);
	}
}
