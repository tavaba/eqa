<?php
namespace Kma\Component\Eqa\Administrator\View\Regradings; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\DataObject\ExamseasonInfo;
use Kma\Component\Eqa\Administrator\Enum\PpaaStatus;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Library\Kma\Helper\FormHelper;
use Kma\Library\Kma\Helper\DependentListsHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends ItemsHtmlView {
	protected ?ExamseasonInfo $examseason;
	/** @var \Joomla\CMS\Form\Form|null Form upload sao kê cho layout importstatement. */
	protected $uploadStatementForm = null;
	/** @var array Thống kê phí phúc khảo (toàn bộ, không phân trang). */
	protected array $paymentStatistic = [];
	protected function configureItemFieldsForLayoutDefault():void{
		$option = new ListLayoutItemFields();

		//Các trường thông tin
		$option->sequence = ListLayoutItemFields::defaultFieldSequence();
		$option->check = ListLayoutItemFields::defaultFieldCheck();
		$option->customFieldset1 = array();
		$option->customFieldset1[] = new ListLayoutItemFieldOption('learnerCode', 'Mã HVSV');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('learnerLastname', 'Họ đệm');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('learnerFirstname', 'Tên');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('examName', 'Môn thi');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('origMark', 'Điểm gốc', false, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('ppaaMark', 'Điểm PK', false, false, 'text-center');

		// Cột phí phúc khảo — raw HTML
		$feeField = new ListLayoutItemFieldOption('paymentAmountHtml', 'Phí PK', false, false, 'text-end');
		$feeField->printRaw = true;
		$option->customFieldset1[] = $feeField;

		// Cột nộp phí — raw HTML (badge)
		$paidField = new ListLayoutItemFieldOption('paymentStatusHtml', 'Nộp phí', false, false, 'text-center');
		$paidField->printRaw = true;
		$option->customFieldset1[] = $paidField;

		$option->customFieldset1[] = new ListLayoutItemFieldOption('statusText', 'Trạng thái');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('description', 'Nội dung xử lý');
		$f = new ListLayoutItemFieldOption('handler', 'Người xử lý');
		$f->printRaw = true;
		$option->customFieldset1[] = $f;
		$this->itemFields = $option;
	}
	protected function prepareDataForLayoutDefault(): void
	{
		// Gọi phương thức lớp cha
		parent::prepareDataForLayoutDefault();

		/** @var \Kma\Component\Eqa\Administrator\Model\RegradingsModel $model */
		$model = $this->getModel();

		// Lấy thông tin kỳ thi
		$examseasonId = $model->getFilteredExamseasonId();
		if (!empty($examseasonId)) {
			$this->examseason = DatabaseHelper::getExamseasonInfo($examseasonId);
		}

		// Thống kê phí — TOÀN BỘ kết quả lọc, không phụ thuộc phân trang
		$this->paymentStatistic = $model->getPaymentStatistic();

		// Tiền xử lý từng item trên trang hiện tại
		if (!empty($this->layoutData) && !empty($this->layoutData->items)) {
			foreach ($this->layoutData->items as &$item) {

				// ── Trạng thái phúc khảo ──────────────────────────────────────
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

				// ── Cột "Phí PK": số tiền + mã nộp tiền (gộp chung) ──────────
				$paymentAmount = (int) ($item->paymentAmount ?? 0);
				$paymentCode   = $item->paymentCode ?? null;

				if ($paymentAmount <= 0) {
					$item->paymentAmountHtml = '<span class="text-muted">—</span>';
				} else {
					$amountFormatted = htmlspecialchars(number_format($paymentAmount, 0, ',', '.'));
					$codeHtml = $paymentCode
						? '<br><code class="small text-secondary">' . htmlspecialchars($paymentCode) . '</code>'
						: '';
					$item->paymentAmountHtml = '<span class="fw-semibold">' . $amountFormatted . '&nbsp;đ</span>'
						. $codeHtml;
				}

				// ── Cột "Nộp phí": badge trạng thái ──────────────────────────
				if ($paymentAmount <= 0) {
					$item->paymentStatusHtml = '<span class="badge bg-secondary">Miễn phí</span>';
				} elseif ((bool) ($item->paymentCompleted ?? false)) {
					$item->paymentStatusHtml = '<span class="badge bg-success">'
						. '<span class="icon-check me-1" aria-hidden="true"></span>Đã nộp</span>';
				} else {
					$item->paymentStatusHtml = '<span class="badge bg-warning text-dark">Chưa nộp</span>';
				}

				// --- Người xử lý -------
				if($item->handlerName)
					$item->handler = "{$item->handlerName}<br/>({$item->handledAt})";
				elseif($item->handlerUsername)
					$item->handler = "{$item->handlerUsername}<br/>({$item->handledAt})";
				else
					$item->handler = '';
			}
			unset($item);
		}
	}

	protected function addToolbarForLayoutDefault(): void
	{
		ToolbarHelper::title('Danh sách yêu cầu phúc khảo');

		ToolbarHelper::appendGoHome();

		// Nút Nhập sao kê — link đến layout importstatement
		$examseasonId   = $this->getModel()->getFilteredExamseasonId() ?? 0;
		$importUrl = Route::_(
			'index.php?option=com_eqa&view=regradings&layout=importstatement&examseason_id=' . $examseasonId,
			false
		);
		ToolbarHelper::appendLink('core.manage', $importUrl, 'Nhập sao kê', 'file');

//		ToolbarHelper::appendButton('core.manage', 'download', 'Bảng thu phí', 'regradings.downloadRegradingFee');

		ToolbarHelper::appendButton('core.manage', 'checkmark-circle', 'Chấp nhận', 'regradings.accept', true, 'btn btn-success');
		ToolbarHelper::appendButton('core.manage', 'cancel-circle', 'Từ chối', 'regradings.reject', true, 'btn btn-danger');
		ToolbarHelper::appendButton('eqa.supervise', 'plus-circle', 'Tạo yêu cầu PK', 'regradings.add', false, 'btn btn-success');
		ToolbarHelper::appendDelete('regradings.delete', 'Xóa yêu cầu PK', 'Bạn có chắc muốn xóa yêu cầu phúc khảo?', 'eqa.supervise');
		ToolbarHelper::appendButton('core.manage', 'list', 'Phân công chấm', 'regradings.assignRegradingExaminers');
		ToolbarHelper::appendButton('core.manage', 'download', 'Bài thi iTest', 'regradings.downloadHybridRegradings');
		ToolbarHelper::appendButton('core.manage', 'download', 'Bài thi viết', 'regradings.downloadPaperRegradings');
		ToolbarHelper::appendButton('core.manage', 'download', 'Phiếu chấm thi viết', 'regradings.downloadPaperRegradingSheets');
		ToolbarHelper::appendButton('core.manage', 'download', 'Tổng hợp', 'regradings.download');
		$msg = 'Xóa hết mã nộp tiền đối với những kỳ thi đã kết thúc và thủ tục phúc khảo đã xong.
		Việc này giúp giảm số lượng mã trong CSDL, đảm bảo hiệu năng cho hệ thống';
		ToolbarHelper::appendConfirmButton('core.manage', $msg, 'loop', 'Làm sạch mã', 'regradings.cleanPaymentCodes',false);
	}

	// =========================================================================
	// Layout: importstatement
	// =========================================================================

	/**
	 * Chuẩn bị dữ liệu cho layout nhập sao kê ngân hàng phí phúc khảo.
	 *
	 * @since 2.0.7
	 */
	protected function prepareDataForLayoutImportstatement(): void
	{
		$examseasonId = Factory::getApplication()->input->getInt('examseason_id',0);

		// Load thông tin kỳ thi để hiển thị tiêu đề
		if (!empty($examseasonId)) {
			$this->examseason = DatabaseHelper::getExamseasonInfo($examseasonId);
		}

		// Load form upload và inject examseason_id
		$this->uploadStatementForm = FormHelper::getBackendForm(
			'com_eqa.regradings.importstatement',
			'upload_statement.xml',
			[]
		);
		$this->uploadStatementForm->setField(
			new \SimpleXMLElement('<field name="examseason_id" type="hidden" default="' . $examseasonId . '" />'),
			null,
			true,
			'upload'
		);
	}

	/**
	 * @since 2.0.7
	 */
	protected function addToolbarForLayoutImportstatement(): void
	{
		$title = 'Nhập sao kê ngân hàng — Phúc khảo';
		if (!empty($this->examseason)) {
			$title .= ' — ' . htmlspecialchars($this->examseason->name);
		}
		ToolbarHelper::title($title);
		ToolbarHelper::appendUpload('regradings.importStatement', 'Đối chiếu & Cập nhật', 'upload', 'core.manage', true);

		$examseasonId = $this->examseason->id ?? 0;
		$cancelUrl = Route::_(
			'index.php?option=com_eqa&view=regradings&examseason_id=' . $examseasonId,
			false
		);
		ToolbarHelper::appendCancelLink($cancelUrl);
	}

	protected function prepareDataForLayoutAdd()
	{
		$this->form = FormHelper::getBackendForm('com_eqa.regradings.add', 'addRegradings.xml',[]);
		$this->wa->useScript('com_eqa.dependent_lists');
		DependentListsHelper::setup3Level(
			$this->wa,
			'',
			'examseason_id',
			'exam_id',
			'learner_ids',
			' -Chọn môn thi- ',
			'',
			Route::_('index.php?option=com_eqa&task=examseason.getJsonListOfExams', false),
			Route::_('index.php?option=com_eqa&task=exam.getJsonListOfExaminees', false)
		);
	}

	protected function addToolbarForLayoutAdd()
	{
		//Title
		ToolbarHelper::title('Thêm yêu cầu phúc khảo cho thí sinh');

		// Add buttons to the toolbar
		ToolbarHelper::save('regradings.add');
		$url = Route::_('index.php?option=com_eqa&view=regradings', false);
		ToolbarHelper::appendCancelLink($url);
	}
}
