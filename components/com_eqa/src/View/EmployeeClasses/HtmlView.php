<?php
namespace Kma\Component\Eqa\Site\View\EmployeeClasses;   //Must end with the View Name
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\ItemsHtmlView;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Site\Model\EmployeeClassesModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

/**
 * View front-end: giảng viên xem danh sách các lớp học phần đã dạy.
 *
 * @since 2.1.5
 */
class HtmlView extends ItemsHtmlView
{
	protected ?string $listModelName = 'EmployeeClasses';
	protected ?object $employee = null;
	protected ?string $errorMessage = null;

	protected function configureItemFieldsForLayoutDefault(): void
	{
		$option = new ListLayoutItemFields();
		$option->sequence = ListLayoutItemFields::defaultFieldSequence();
		$option->customFieldset1 = [];

		$f = new ListLayoutItemFieldOption('codeLink', 'Mã lớp');
		$f->printRaw = true;    //Được chuẩn bị dưới dạng HTML link
		$option->customFieldset1[] = $f;

		$option->customFieldset1[] = new ListLayoutItemFieldOption('subjectName', 'Môn học', true);
		$option->customFieldset1[] = new ListLayoutItemFieldOption('academicyearText', 'Năm học', false, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('term', 'Học kỳ', true, false, 'text-center');
		$option->customFieldset1[] = new ListLayoutItemFieldOption('size', 'Sĩ số', true, false, 'text-center');
		$this->itemFields = $option;
	}

	protected function prepareDataForLayoutDefault(): void
	{
		try
		{
			/**
			 * @var EmployeeClassesModel $model
			 */
			$model = $this->getModel();

			// Xác định giảng viên từ phiên đăng nhập
			$employeeId = GeneralHelper::getSignedInEmployeeId();
			if (empty($employeeId))
				throw new Exception('Không xác định được cán bộ. Hãy đăng nhập bằng tài khoản có email trùng với email cán bộ trong hệ thống.');
			$model->setState('filter.employee_id', $employeeId);

			// Kiểm tra quyền
			if (!$model->canViewList())
				throw new Exception('Bạn không có quyền xem thông tin này');

			// Gọi phương thức lớp cha
			parent::prepareDataForLayoutDefault();

			// Thông tin giảng viên
			$employeeInfos = DatabaseHelper::getEmployeeInfos([$employeeId], false);
			$this->employee = $employeeInfos[$employeeId] ?? null;

			// Tiền xử lý
			if (!empty($this->layoutData) && !empty($this->layoutData->items))
			{
				foreach ($this->layoutData->items as &$item)
				{
					$item->academicyearText = DatetimeHelper::decodeAcademicYear($item->academicyear);
					$url = Route::_('index.php?option=com_eqa&view=employeeClassLearners&class_id=' . (int) $item->id);
					$item->codeLink = '<a href="' . $url . '">' . htmlspecialchars($item->code) . '</a>';
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
		ToolbarHelper::title('Lớp học phần đã dạy');
		if ($this->errorMessage)
		{
			ToolbarHelper::back('');
			ToolbarHelper::render();
			return;
		}
		if(!is_null($this->toolbarOption))
			ToolbarHelper::render();
	}
}
