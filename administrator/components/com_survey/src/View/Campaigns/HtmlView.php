<?php
namespace Kma\Component\Survey\Administrator\View\Campaigns;

defined('_JEXEC') or die;

use Kma\Component\Survey\Administrator\Base\ItemsHtmlView;
use Kma\Component\Survey\Administrator\Model\CampaignModel;
use Kma\Component\Survey\Administrator\Model\CampaignsModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ItemAction;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $f = new ListLayoutItemFieldOption('title', 'Tên đợt khảo sát', false, true);
        $f->altField = 'description';
        $f->showLinkConditionField = 'canEdit';
        $option->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('form', 'Mẫu phiếu', false, false, 'text-center');
        $f->printRaw = true;
        $f->altField = 'formDescription';
        $f->urlFormatString = 'index.php?option=com_survey&view=form&layout=preview&id=%s';
        $f->urlFormatStringField = 'formId';
        $option->customFieldset1[] = $f;

        $f = new ListLayoutItemFieldOption('countSurveys', 'Cuộc khảo sát',true, false,'text-center');
        $f->urlFormatString = 'index.php?option=com_survey&view=campaignsurveys&campaign_id=%s';
        $option->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('countUnits', 'Đơn vị', true, false,'text-center');
        $f->urlFormatString = 'index.php?option=com_survey&view=campaignunits&campaign_id=%s';
        $f->showLinkConditionField='canMonitor';
        $option->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('countRespondents', 'Tổng lượt người', true, false,'text-center');
        $f->urlFormatString='index.php?option=com_survey&view=campaignrespondents&campaign_id=%s';
        $f->showLinkConditionField='canMonitor';
        $option->customFieldset1[] = $f;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('countResponded', 'Tổng lượt ý kiến', true, false,'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('startTime', 'Bắt đầu', true);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('endTime', 'Kết thúc', true);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('creator', 'Người tạo');

        //Actions field
        $action1 = new ItemAction();
        $action1->icon = 'download';
        $action1->urlFormatString = 'index.php?option=com_survey&task=campaign.downloadRawData&id=%d';
        $action1->displayConditionField = 'canAnalyse';

        $action2 = new ItemAction();
        $action2->icon = 'bars';
        $action2->urlFormatString = 'index.php?option=com_survey&task=campaign.downloadReport&id=%d';
        $action2->displayConditionField = 'canAnalyse';

        $option->actions = [$action1, $action2];
        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();
        if(!empty($this->layoutData->items))
        {
            /**
             * Load model for access permission checking
             * @var CampaignModel $campainModel
             */
            $campainModel = ComponentHelper::createModel('Campaign');
            foreach ($this->layoutData->items as $item)
            {
                $item->form = '<span class="fa fa-eye"></span>';
                $item->canEdit = $campainModel->canEdit($item);
                $item->canMonitor = $campainModel->canMonitor($item);
                $item->canAnalyse = $campainModel->canAnalyse($item);
            }
        }
    }

    protected function addToolbarForLayoutDefault(): void
    {
        /**
         * @var CampaignsModel $campaignsModel
         */
        $campaignsModel = $this->getModel();
        $items = $this->layoutData->items;

        ToolbarHelper::title('Quản lý đợt khảo sát');
        ToolbarHelper::appendGoHome();

        if($campaignsModel->canDeleteAny($items))
            ToolbarHelper::appendDelete('campaigns.delete');

        if($campaignsModel->canCreate())
            ToolbarHelper::addNew('campaign.add');
    }
}