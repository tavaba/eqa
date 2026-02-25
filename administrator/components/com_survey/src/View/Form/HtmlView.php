<?php
namespace Kma\Component\Survey\Administrator\View\Form;

use Joomla\CMS\Factory;
use Kma\Component\Survey\Administrator\Base\ItemHtmlView;
use Kma\Component\Survey\Administrator\Model\FormModel;
use Kma\Library\Kma\Helper\ToolbarHelper;

defined('_JEXEC') or die;


class HtmlView extends ItemHtmlView
{
    protected function prepareDataForLayoutDesign():void
    {
        $this->wa->useStyle('surveyjs.creator.core.style');
        $this->wa->useScript('surveyjs.creator');
        $this->wa->useStyle('surveyjs.core.style');
        $this->wa->useScript('surveyjs.ui');
        $model = $this->getModel('form');
        $this->item = $model->getItem();
    }
    protected function addToolbarForLayoutDesign(): void
    {
        /**
         * @var FormModel $model
         */
        $model = $this->getModel();
        $itemId = $this->item->id;
        $canSave = (empty($itemId) && $model->canCreate()) || $model->canEdit($itemId);
        ToolbarHelper::title('Thiết kế phiếu khảo sát');
        if($canSave)
        {
            ToolbarHelper::apply('form.applyModel');
            ToolbarHelper::save('form.saveModel');
        }
        ToolbarHelper::cancel('form.cancel');
    }
    protected function prepareDataForLayoutPreview():void
    {
        $this->wa->useStyle('surveyjs.core.style');
        $this->wa->useScript('surveyjs.ui');
        $this->wa->useScript('surveyjs.survey.i18n');
        $model = $this->getModel('form');
        $this->item = $model->getItem();
    }
    protected function addToolbarForLayoutPreview(): void
    {
        ToolbarHelper::title('Xem trước phiếu khảo sát');
        ToolbarHelper::back();
    }
}