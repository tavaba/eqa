<?php
namespace Kma\Component\Eqa\Administrator\View\Regradingresult; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Field\ExamsessionemployeeField;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ExamHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView {
	protected function configureItemFieldsForLayoutDefault(): void
	{
	}
	protected function prepareDataForLayoutUploadpaper(): void
	{
		$this->layoutData->form = FormHelper::getBackendForm(
			'com_eqa.upload.paper.regrading.result',
			'upload_excelfiles.xml',
			[]
		);
	}
	protected function addToolbarForLayoutUploadpaper(): void
	{
		ToolbarHelper::title('Tải lên kết quả phúc bài thi viết');
		ToolbarHelper::appendUpload('regradings.uploadPaperRegradingResult');
		ToolbarHelper::appendCancelLink(JRoute::_('index.php?option=com_eqa',false));
	}
	protected function prepareDataForLayoutUploaditest(): void
	{
		$this->layoutData->form = FormHelper::getBackendForm(
			'com_eqa.upload.itest.regrading.result',
			'upload_itest.xml',
			[]
		);
	}
	protected function addToolbarForLayoutUploaditest(): void
	{
		ToolbarHelper::title('Tải lên kết quả phúc bài thi hỗn hợp iTest');
		ToolbarHelper::appendUpload('regradings.uploadHybridRegradingResult');
		ToolbarHelper::appendCancelLink(JRoute::_('index.php?option=com_eqa',false));
	}

}
