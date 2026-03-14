<?php

namespace Kma\Component\Eqa\Administrator\View\Assessments;

defined('_JEXEC') or die();

use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Enum\AssessmentResultType;
use Kma\Component\Eqa\Administrator\Enum\AssessmentType;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Model\AssessmentsModel;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

/**
 * View danh sách kỳ sát hạch.
 *
 * @since 2.0.5
 */
class HtmlView extends ItemsHtmlView
{
	/**
	 * @var int[] Danh sách năm có dữ liệu (dùng cho filter dropdown).
	 * @since 2.0.5
	 */
	protected array $availableYears = [];

	// =========================================================================
	// Layout: default (danh sách)
	// =========================================================================

	protected function configureItemFieldsForLayoutDefault(): void
	{
		$fields = new ListLayoutItemFields();

		$fields->sequence = ListLayoutItemFields::defaultFieldSequence();
		$fields->check    = ListLayoutItemFields::defaultFieldCheck();

		$fields->customFieldset1 = [];
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('title', 'Tên kỳ sát hạch', true, true);
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('typeLabel', 'Loại', false, false, 'text-center');
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('resultTypeLabel', 'Kiểu kết quả', false, false, 'text-center');
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('start_date', 'Bắt đầu', true, false, 'text-center');
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('end_date', 'Kết thúc', false, false, 'text-center');
		$fields->customFieldset1[] = new ListLayoutItemFieldOption('feeFormatted', 'Phí (VNĐ)', false, false, 'text-end');

		// Cột "Số TS" có link drill-down sang danh sách thí sinh
		$f = new ListLayoutItemFieldOption('ncandidate', 'Số TS', false, false, 'text-center');
		$f->urlFormatString = 'index.php?option=com_eqa&view=assessmentlearners&assessment_id=%d';
		$fields->customFieldset1[] = $f;

		$f = new ListLayoutItemFieldOption('completedLabel', 'Hoàn thành', false, false, 'text-center');
		$f->printRaw = true;
		$fields->customFieldset1[] = $f;

		$fields->published = ListLayoutItemFields::defaultFieldPublished();

		$this->itemFields = $fields;
	}

	protected function prepareDataForLayoutDefault(): void
	{
		parent::prepareDataForLayoutDefault();

		/**
		 * @var AssessmentsModel $model
		 */
		$model = $this->getModel();

		// Lấy danh sách năm để populate filter dropdown
		$this->availableYears = $model->getAvailableYears();

		// Preprocessing items
		if (!empty($this->layoutData->items)) {
			foreach ($this->layoutData->items as &$item) {
				// Dịch type
				$typeEnum = AssessmentType::tryFrom((int) $item->type);
				$item->typeLabel = $typeEnum?->getLabel() ?? '—';

				// Dịch result_type
				$resultTypeEnum = AssessmentResultType::tryFrom((int) $item->result_type);
				$item->resultTypeLabel = $resultTypeEnum?->getLabel() ?? '—';

				// Format phí
				$item->feeFormatted = number_format((int) $item->fee, 0, ',', '.');

				// Trạng thái hoàn thành
				$item->completedLabel = $item->completed
					? '<span class="badge bg-success">Đã xong</span>'
					: '<span class="badge bg-secondary">Chưa xong</span>';
			}
			unset($item);
		}
	}

	protected function addToolbarForLayoutDefault(): void
	{
		ToolbarHelper::title('Danh sách kỳ sát hạch');
		ToolbarHelper::appendGoHome();
		ToolbarHelper::addNew('assessment.add');
		ToolbarHelper::editList('assessment.edit');
		ToolbarHelper::deleteList('Bạn có chắc muốn xóa không?', 'assessments.delete');
	}
}
