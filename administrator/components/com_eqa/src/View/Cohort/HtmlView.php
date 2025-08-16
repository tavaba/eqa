<?php
namespace Kma\Component\Eqa\Administrator\View\Cohort; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Eqa\Administrator\Base\EqaItemHtmlView;
use Kma\Component\Eqa\Administrator\Helper\DependentListsHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemHtmlView {

	protected function prepareDataForLayoutAddlearners()
	{
		//Determine the cohort id
		$app = Factory::getApplication();
		$cohortId = $app->input->getInt('cohort_id');
		if(empty($cohortId))
			die('Không tìm thấy thông tin nhóm HVSV');

		//Get the cohort information
		$model = $this->getModel();
		$this->item = $model->getItem($cohortId);
		if(empty($this->item))
			die('Không tìm thấy thông tin nhóm HVSV');

		//Load form
		$this->form = FormHelper::getBackendForm('com_eqa.cohort.addlearners','addcohortlearners.xml', ['control'=>'jform']);

		//Load asset
		$this->wam->useScript('com_eqa.dependent_lists');

		//Init dependent lists
		DependentListsHelper::setup2Level(
			$this->wam,
			'jform',
			'group_id',
			'learner_ids',
			'- Chọn HVSV -',
			Route::_('index.php?option=com_eqa&task=group.jsonGetLearners',false)
		);
	}

	protected function addToolbarForLayoutAddlearners() {
		ToolbarHelper::title('Thêm HVSV vào nhóm');
		ToolbarHelper::save('cohort.addLearners');
		ToolbarHelper::appendCancelLink(Route::_('index.php?option=com_eqa&view=cohortlearners&cohort_id='.$this->item->id,false));
	}
}
