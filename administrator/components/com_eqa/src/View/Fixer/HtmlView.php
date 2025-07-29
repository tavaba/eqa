<?php
namespace Kma\Component\Eqa\Administrator\View\Fixer; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\WebAsset\WebAssetManager;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemsHtmlView;
use Kma\Component\Eqa\Administrator\Helper\DependentListsHelper;
use Kma\Component\Eqa\Administrator\Helper\FormHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemsHtmlView{
	protected function configureItemFieldsForLayoutDefault(): void
	{
	}

	protected function prepareDataForLayoutFixpam():void
	{
		$this->layoutData->form = FormHelper::getBackendForm('com_eqa.fixer.fixpam','fixpam.xml', ['control'=>'jform']);

		//Load asset
		$this->wam->useScript('com_eqa.dependent_lists');

		//Init dependent lists
		DependentListsHelper::setup2Level(
			$this->wam,
			'jform',
			'class_id',
			'learner_id',
			'- Chọn HVSV -',
			JRoute::_('index.php?option=com_eqa&task=fixer.jsonGetClassLearners',false)
		);
	}
	protected function addToolbarForLayoutFixpam(): void
	{
		ToolbarHelper::title('Chỉnh sửa điểm quá trình');
		ToolbarHelper::save('fixer.fixpam');
		ToolbarHelper::appendCancelLink(JRoute::_('index.php?option=com_eqa', false));
	}
}
