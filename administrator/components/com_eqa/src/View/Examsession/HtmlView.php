<?php
namespace Kma\Component\Eqa\Administrator\View\Examsession; //The namespace must end with the VIEW NAME.
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Base\EqaItemHtmlView;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;

class HtmlView extends EqaItemHtmlView {
    protected function prepareDataForLayoutAddbatch()
    {
        $model = $this->getModel();
        $this->form = $model->getAddbatchForm();
    }
    protected function addToolbarForLayoutAddbatch()
    {
        ToolbarHelper::title(Text::_('COM_EQA_MANAGER_EXAMSESSION_ADD_TITLE'));
        ToolbarHelper::appenddButton('core.create','save','JTOOLBAR_SAVE','examsession.saveBatch',false,null,true);
        ToolbarHelper::cancel('examsession.cancel');
    }
}
