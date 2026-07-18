<?php
namespace Kma\Component\Eqa\Site\View\EmployeeClassLearners;   //Must end with the View Name
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Enum\Conclusion;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Model\ClassModel;
use Kma\Component\Eqa\Site\Model\EmployeeClassLearnersModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

/**
 * View front-end: giảng viên xem danh sách HVSV của một lớp học phần
 * đã dạy kèm kết quả học tập (điểm quá trình + kết quả lần thi cuối).
 *
 * @since 2.1.5
 */
class HtmlView extends ItemsHtmlView
{
	protected ?string $listModelName = 'EmployeeClassLearners';
	protected ?object $class = null;
	protected ?string $errorMessage = null;

	protected function configureItemFieldsForLayoutDefault(): void
	{
		$option = new ListLayoutItemFields();
		$option->sequence = ListLayoutItemFields::defaultFieldSequence();
		$option->customFieldset1 = [];
		$option->customFieldset1[] = new ListLayoutItemFieldOption('code', 'Mã HVSV', false, false, 'text-center');
		$option->customFieldset1[] = ListLayoutItemFields::defaultFieldLastname();
		$option->customFieldset1[] = ListLayoutItemFields::defaultFieldFirstname();
		$option->customFieldset1[] = new ListLayoutItemFieldOption('group', 'Lớp', false, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('pam1', 'TP1', false, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('pam2', 'TP2', false, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('pam', 'ĐQT', false, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('attempt', 'Lần thi', false, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('finalMark', 'Điểm thi', false, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('moduleMark', 'Điểm HP', false, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('moduleBase4Mark', 'Điểm hệ 4', false, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('moduleGrade', 'Điểm chữ', false, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('conclusionText', 'Kết luận', false, false, 'text-center');
		$this->itemFields = $option;
	}

	protected function prepareDataForLayoutDefault(): void
	{
		try
		{
			$app = Factory::getApplication();

			/**
			 * @var EmployeeClassLearnersModel $model
			 */
			$model = $this->getModel();

			// Xác định lớp học phần
			$classId = $app->input->getInt('class_id');
			if (empty($classId))
				throw new Exception('Không xác định được lớp học phần');
			$model->setState('filter.class_id', $classId);

			// Xác định giảng viên từ phiên đăng nhập
			$employeeId = GeneralHelper::getSignedInEmployeeId();
			if (empty($employeeId))
				throw new Exception('Không xác định được cán bộ. Hãy đăng nhập bằng tài khoản có email trùng với email cán bộ trong hệ thống.');

			// Kiểm tra quyền: chỉ giảng viên phụ trách lớp mới được xem
			if (!$model->canViewList())
				throw new Exception('Bạn không có quyền xem thông tin của lớp học phần này');

			// Gọi phương thức lớp cha
			parent::prepareDataForLayoutDefault();

			// Cố định class_id qua các lần submit form (search, phân trang)
			$this->layoutData->formHiddenFields['class_id'] = $classId;

			// Các link phân trang là thẻ <a> GET thuần túy (không submit adminForm)
			// nên phải đăng ký tham số bắt buộc vào Pagination để Joomla đưa
			// chúng vào URL của từng nút trang
			$pagination = $this->layoutData->pagination ?? $model->getPagination();
			if (!empty($pagination))
			{
				$pagination->setAdditionalUrlParam('view', 'employeeClassLearners');
				$pagination->setAdditionalUrlParam('class_id', $classId);
			}

			/**
			 * Thông tin lớp học phần
			 * @var ClassModel $classModel
			 */
			$classModel = ComponentHelper::createModel('Class', 'Administrator');
			$this->class = $classModel->getItem($classId);

			// Tiền xử lý: chuẩn hóa điểm số và nhãn kết luận
			if (!empty($this->layoutData) && !empty($this->layoutData->items))
			{
				foreach ($this->layoutData->items as &$item)
				{
					ExamHelper::normalizeMarks($item);
					$conclusion = is_numeric($item->conclusion)
						? Conclusion::tryFrom((int) $item->conclusion)
						: null;
					$item->conclusionText = $conclusion?->getLabel() ?? '';
				}
				unset($item);
			}
		}
		catch (Exception $e)
		{
			$this->errorMessage = $e->getMessage();
		}
	}

	protected function addToolbarForLayoutDefault(): void
	{
		ToolbarHelper::title('Danh sách HVSV và kết quả học tập');
		if ($this->errorMessage)
		{
			ToolbarHelper::back('');
			ToolbarHelper::render();
		}
	}
}
