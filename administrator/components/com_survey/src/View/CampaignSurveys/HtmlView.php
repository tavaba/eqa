<?php
namespace Kma\Component\Survey\Administrator\View\CampaignSurveys;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Kma\Component\Survey\Administrator\Base\ItemsHtmlView;
use Kma\Component\Survey\Administrator\Enum\AuthorizationMode;
use Kma\Component\Survey\Administrator\Helper\SurveyHelper;
use Kma\Component\Survey\Administrator\Model\CampaignModel;
use Kma\Component\Survey\Administrator\Model\SurveyModel;
use Kma\Component\Survey\Administrator\Model\SurveysModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\Service\InlineProgressBar;
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
        $f = new ListLayoutItemFieldOption('title', 'Cuộc khảo sát');
        $f->altField = 'description';
        $option->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('respondentCount', 'Số người',true,false,'text-center');
        $f->urlFormatString='index.php?option=com_survey&view=surveyrespondents&survey_id=%d';
        $f->showLinkConditionField='canMonitor';
        $option->customFieldset1[] = $f;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('responseCount','Đã phản hồi',true,false,'text-center');
        $f = new ListLayoutItemFieldOption('progress','Tiến độ');
        $f->printRaw = true;
        $f->columnCssClasses = 'text-center w-10';
        $option->customFieldset1[] = $f;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('startTime','Bắt đầu',true,false);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('createdBy','Người tạo');

        //Actions field
        $action1 = new ItemAction();
        $action1->icon = 'chart';
        $action1->urlFormatString = 'index.php?option=com_survey&view=survey&layout=analytics&id=%d';
        $action1->text = 'Phân tích kết quả';
        $action1->displayConditionField = 'canAnalyse';

        $action2 = new ItemAction();
        $action2->icon = 'bars';
        $action2->urlFormatString = 'index.php?option=com_survey&task=survey.downloadWordReport&id=%d';
        $action2->text = 'Tải báo cáo tổng hợp';
        $action2->displayConditionField = 'canAnalyse';

        $option->actions = [$action1, $action2];

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        //Determine the id of the current campaign
        $campaignId = Factory::getApplication()->input->getInt('campaign_id');
        if(empty($campaignId))
            die('Invalid request');

	    /**
	     * Load the campaign object
	     * @var CampaignModel $itemModel
	     */
        $itemModel = ComponentHelper::createModel('Campaign');
        $this->item = $itemModel->getItem($campaignId);
        if(empty($this->item))
            die('Cannot load campaign');

        //Prepare the list model
        $model = $this->getModel();
        $model->setState('filter.campaign_id',$campaignId);

        //Get data from model
        parent::prepareDataForLayoutDefault();

        //Data preprocessing
        if(!empty($this->layoutData->items))
        {
            /**
             * Load survey model for access permission checking
             * @var SurveyModel $surveyModel
             */
            $surveyModel = ComponentHelper::createModel('Survey');
            $progressBarService = new InlineProgressBar();
            foreach ($this->layoutData->items as $item)
            {
                //Progress bar for response count
                if($item->authMode == AuthorizationMode::AssignedRespondent->value && $item->respondentCount>0)
                {
                    $rate = round($item->responseCount/$item->respondentCount*100);
                    $item->progress = $progressBarService->render($rate, $item->responseCount);
                }
                else
                    $item->progress = '';
                $item->canEdit = $surveyModel->canEdit($item);
                $item->canMonitor = $surveyModel->canMonitor($item);
                $item->canAnalyse = $surveyModel->canAnalyse($item);
            }
        }

        //Set the hidden form fields
        $this->layoutData->formActionParams = [
            'view'=>'campaignsurveys',
            'campaign_id'=>$campaignId
        ];
    }

    protected function addToolbarForLayoutDefault(): void
    {
        /**
         * @var CampaignModel $campaignModel
         */
        $campaignModel = ComponentHelper::createModel('Campaign');
        $campaign = $this->item;
        ToolbarHelper::title('Danh sách cuộc khảo sát của đợt khảo sát');
        ToolbarHelper::appendGoHome();
        ToolbarHelper::appendGoBack('campaign.cancel','Đợt khảo sát');
        if($campaignModel->canAddSurvey($campaign)) {
            ToolbarHelper::appendButton('plus-circle', 'Khảo sát lớp học phần', 'campaign.addClassSurveys', false, 'btn btn-success');
            ToolbarHelper::appendButton('plus-circle', 'Khảo sát nhóm người', 'campaign.addRespondentGroupSurveys', false, 'btn btn-success');
        }

        if($campaignModel->canEdit($campaign))
            ToolbarHelper::appendDelete('campaign.deleteSurveys');
    }
}