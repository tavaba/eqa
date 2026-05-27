<?php
namespace Kma\Component\Eqa\Administrator\View\Subject; //The namespace must end with the VIEW NAME.
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\ItemHtmlView;
use Kma\Component\Eqa\Administrator\Helper\CourseHelper;
use Kma\Component\Eqa\Administrator\Model\SubjectModel;
use Kma\Library\Kma\Helper\ToolbarHelper;

defined('_JEXEC') or die();

class HtmlView extends ItemHtmlView
{
	// =========================================================================
	// Layout: statistics
	// =========================================================================

	/**
	 * Chuẩn bị dữ liệu cho layout thống kê môn học.
	 *
	 * @return void
	 * @since  2.0.8
	 */
	protected function prepareDataForLayoutStatistics(): void
	{
		$subjectId = Factory::getApplication()->input->getInt('subject_id');

		if (empty($subjectId)) {
			die('Không xác định được môn học.');
		}

		/** @var SubjectModel $model */
		$model = $this->getModel();

		$this->item       = $model->getItem($subjectId);
		$this->statistics = $model->getStatistics($subjectId);

		// Decode degree để hiển thị nhãn tiếng Việt
		if (!empty($this->item)) {
			$this->item->degree = CourseHelper::Degree($this->item->degree);
		}
	}

	/**
	 * Toolbar cho layout statistics.
	 *
	 * @return void
	 * @since  2.0.8
	 */
	protected function addToolbarForLayoutStatistics(): void
	{
		$title = 'Thống kê môn học';
		if (!empty($this->statistics->subject->name)) {
			$title .= ' — ' . $this->statistics->subject->name;
		}

		ToolbarHelper::title($title);
		ToolbarHelper::back();
	}

}
