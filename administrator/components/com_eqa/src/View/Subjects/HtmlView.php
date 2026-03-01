<?php
namespace Kma\Component\Eqa\Administrator\View\Subjects;    //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use JRoute;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Enum\TestType;
use Kma\Component\Eqa\Administrator\Service\RoomService;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Library\Kma\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends ItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault():void{
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $field = new ListLayoutItemFieldOption('department_code', 'COM_EQA_GENERAL_SUBJECT_DEPARTMENT',true,false);
        $field->cellCssClasses = 'text-center';
        $option->customFieldset1[] = $field;
        $field = new ListLayoutItemFieldOption('code','COM_EQA_GENERAL_SUBJECT_CODE', true, true);
        $field->cellCssClasses = 'text-center';
        $option->customFieldset1[] = $field;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('name', 'COM_EQA_GENERAL_SUBJECT_NAME');
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('degree','COM_EQA_GENERAL_COURSE_DEGREE',true,false,'text-center');
	    $option->customFieldset1[] = new ListLayoutItemFieldOption('credits','Số TC',true,false,'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('finaltesttype','COM_EQA_GENERAL_SUBJECT_TESTTYPE', true, false);
        $field = new ListLayoutItemFieldOption('testbankyear', 'COM_EQA_GENERAL_SUBJECT_TESTBANK', true, false);
        $field->cellCssClasses = 'text-center';
        $option->customFieldset1[] = $field;

	    // Cột "Phòng thi": danh sách mã đầy đủ các phòng được phép tổ chức thi,
	    // phân tách bởi ", ". Hiển thị chuỗi rỗng khi không giới hạn (NULL).
	    // Giá trị được populate trong prepareDataForLayoutDefault().
	    $option->customFieldset1[] = new ListLayoutItemFieldOption(
		    'allowed_rooms_display',
		    'Phòng thi',
		    false,
		    false
	    );

        $option->published = ListLayoutItemFields::defaultFieldPublished();

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

	    // Khởi tạo một lần ngoài vòng lặp.
	    // RoomService tự lazy-load dữ liệu phòng khi cần, tránh truy vấn DB lặp lại.
	    $roomService = new RoomService();

        if(!empty($this->layoutData->items)) {
            foreach ($this->layoutData->items as $item) {
                $item->finaltesttype = TestType::from($item->finaltesttype)->getLabel();
                $item->degree = CourseHelper::Degree($item->degree);

	            // Populate cột phòng thi
	            $item->allowed_rooms_display = $this->buildAllowedRoomsDisplay(
		            $item->allowed_rooms ?? null,
		            $roomService
	            );
            }
        }
    }
	protected function addToolbarForLayoutDefault(): void
	{
		parent::addToolbarForLayoutDefault();
		ToolbarHelper::appendImportLink(JRoute::_('index.php?option=com_eqa&view=subjects&layout=import',false));
	}

	protected function prepareDataForLayoutImport(): void
	{
		$this->form = FormHelper::getBackendForm('com_eqa.subjects.import', 'upload_excelfile.xml', []);
	}
	protected function addToolbarForLayoutImport(): void
	{
		ToolbarHelper::title('Nhập thông tin môn học');
		ToolbarHelper::appendUpload('subjects.import');
		ToolbarHelper::cancel('subjects.cancel');
	}

	// =========================================================================
	// Helper
	// =========================================================================

	/**
	 * Chuyển đổi giá trị `allowed_rooms` thành chuỗi hiển thị.
	 *
	 * Chấp nhận cả 3 dạng đầu vào:
	 *   - JSON string  "[1,3,7]"  → decode, sau đó tra cứu từng ID
	 *   - array        [1, 3, 7]  → tra cứu trực tiếp (đã decode ở Model)
	 *   - null / ""               → trả về "" (không giới hạn phòng)
	 *
	 * Room ID không tìm thấy trong RoomService (đã bị xóa khỏi DB) bị bỏ qua.
	 *
	 * @param  string|array|null $allowedRooms Giá trị thô của cột `allowed_rooms`.
	 * @param  RoomService       $roomService  Service tra cứu thông tin phòng.
	 * @return string Chuỗi mã phòng đầy đủ phân tách bởi ", "; "" nếu trống/NULL.
	 */
	private function buildAllowedRoomsDisplay(
		string|array|null $allowedRooms,
		RoomService $roomService
	): string {
		if (is_string($allowedRooms) && $allowedRooms !== '') {
			$roomIds = json_decode($allowedRooms, true) ?? [];
		} elseif (is_array($allowedRooms)) {
			$roomIds = $allowedRooms;
		} else {
			return '';
		}

		if (empty($roomIds)) {
			return '';
		}

		$fullCodes = [];

		foreach ($roomIds as $roomId) {
			$code = $roomService->getFullCode((int) $roomId);
			if ($code !== '') {
				$fullCodes[] = $code;
			}
		}

		return implode(', ', $fullCodes);
	}
}
