<?php

namespace Kma\Component\Eqa\Administrator\View\SecondAttempts;

defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
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
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('conclusion_label', 'Kết luận', false, false, 'text-center');

        $f = new ListLayoutItemFieldOption('payment_required_label', 'Có phí', false, false, 'text-center');
		$f->printRaw = true; // Để in HTML badge
        $option->customFieldset1[] = $f;

	    $option->customFieldset1[] = new ListLayoutItemFieldOption('payment_code', 'Mã thanh toán', false, false, 'text-center text-nowrap font-monospace');

        // Cột "Đã nộp phí": giá trị đã được tiền xử lý thành HTML icon trong prepareData
        $f = new ListLayoutItemFieldOption('payment_completed_html', 'Đã nộp phí', false, false, 'text-center');
		$f->printRaw = true; // Để in HTML icon
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
                // Nhãn kết luận từ Enum
                $conclusion            = Conclusion::from((int) $item->last_conclusion);
                $item->conclusion_label = $conclusion->getLabel();

                // Nhãn "Có phí"
                $item->payment_required_label = $item->payment_required
                    ? '<span class="badge bg-warning text-dark">Có phí</span>'
                    : '<span class="badge bg-secondary">Không</span>';

                // Biểu tượng "Đã nộp phí" — theo chuẩn Joomla
                if (!$item->payment_required) {
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

	//	ToolbarHelper::deleteList('Bạn có chắc muốn xóa các bản ghi đã chọn? Hành động này không thể hoàn tác.', 'secondattempts.delete', 'Xóa');

	    // Nút "Đã nộp phí": đánh dấu các mục đã chọn là đã nộp phí (cần confirm)
	    ToolbarHelper::appendConfirmButton(
		    'core.edit',
		    'Xác nhận các trường hợp được chọn ĐÃ NỘP PHÍ?',
		    'check',
		    'Đã nộp phí',
		    'secondattempts.markPaymentCompleted',
		    true,
		    'btn btn-success'
	    );

	    // Nút "Chưa nộp phí": thu hồi trạng thái đã nộp phí của các mục đã chọn
	    ToolbarHelper::appendConfirmButton(
		    'core.edit',
			'Xác nhận các trường hợp đã chọn là CHƯA NỘP PHÍ?',
		    'minus-circle',
		    'Chưa nộp phí',
		    'secondattempts.markPaymentIncomplete',
		    true,
		    'btn btn-warning'
	    );
    }
}
