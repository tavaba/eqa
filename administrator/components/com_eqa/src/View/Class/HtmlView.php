<?php
namespace Kma\Component\Eqa\Administrator\View\Class; //The namespace must end with the VIEW NAME.
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use JRoute;
use Kma\Component\Eqa\Administrator\Base\EqaItemHtmlView;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutData;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFieldOption;
use Kma\Component\Eqa\Administrator\Base\EqaListLayoutItemFields;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use Kma\Component\Eqa\Administrator\Helper\ToolbarHelper;
use Kma\Component\Eqa\Administrator\Helper\GeneralHelper;

defined('_JEXEC') or die();

class HtmlView extends EqaItemHtmlView {
    protected object $class;
    protected EqaListLayoutData $listLayoutData;
    protected EqaListLayoutItemFields $listLayoutItemFields;
    protected function prepareDataForLayoutAddlearners(): void
    {
        //Toolbar
        $this->toolbarOption->title = Text::_('COM_EQA_MANAGER_CLASS_ADDLEARNERS_TITLE');

        //Data
        $model = $this->getModel();
        $classId = Factory::getApplication()->input->getInt('class_id');
        $this->form = $model->getCustomForm('com_eqa.addlearners','addlearners',[]);
        $this->class = $model->getItem($classId);
    }

    protected function addToolbarForLayoutAddlearners() : void
    {
        ToolbarHelper::title($this->toolbarOption->title);
        ToolbarHelper::save('class.addLearners');
        $url = JRoute::_('index.php?option=com_eqa&view=classlearners&class_id='.$this->class->id,false);
        ToolbarHelper::appendLink(null, $url,'JTOOLBAR_CANCEL', 'delete', 'btn btn-danger');
    }
}
