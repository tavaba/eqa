<?php
namespace Kma\Component\Survey\Administrator\View\Surveys;

defined('_JEXEC') or die;

use Kma\Component\Survey\Administrator\Base\ItemsHtmlView;
use Kma\Component\Survey\Administrator\Helper\SurveyHelper;
use Kma\Component\Survey\Administrator\Model\SurveyModel;
use Kma\Component\Survey\Administrator\Model\SurveysModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\StateHelper;
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

        $option->id = ListLayoutItemFields::defaultFieldId();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $f = new ListLayoutItemFieldOption('title', 'Cuộc khảo sát',false,true);
        $f->altField = 'description';
        $f->showLinkConditionField='canEdit';
        $option->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('form', 'Mẫu phiếu',false,false,'text-center');
        $f->printRaw = true;
        $f->altField = 'formDescription';
        $f->urlFormatString = 'index.php?option=com_survey&view=form&layout=preview&id=%s';
        $f->urlFormatStringField = 'formId';
        $option->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('respondentCount', 'Số người',true,false,'text-center');
        $f->urlFormatString='index.php?option=com_survey&view=surveyrespondents&survey_id=%d';
        $option->customFieldset1[] = $f;
        $f = new ListLayoutItemFieldOption('progress','Đã phản hồi',true,false,'text-center');
        $f->printRaw = true;
        $f->columnCssClasses = 'text-center w-10';
        $option->customFieldset1[] = $f;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('startTime','Bắt đầu',true,false);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('endTime','Kết thúc',true,false);
        $option->customFieldset1[] = new ListLayoutItemFieldOption('authModeText','Quyền ý kiến',false,false);
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
        parent::prepareDataForLayoutDefault();

        //Data preprocessing
        if(!empty($this->layoutData->items))
        {
            /**
             * Load admin model for access checking
             * @var SurveyModel $surveyModel
             */
            $surveyModel = ComponentHelper::getMVCFactory()->createModel('Survey');
            $progressBarService = new InlineProgressBar();

            foreach ($this->layoutData->items as $item)
            {
                $item->authModeText = SurveyHelper::decodeAuthMode($item->authMode);
                if($item->authMode != SurveyHelper::AUTH_MODE_ASSIGNED)
                    $item->respondentCount=null;

                //A link to survey form preview page
                $item->form = '<span class="fa fa-eye"></span>';

                //Progress bar for response count
                if($item->authMode == SurveyHelper::AUTH_MODE_ASSIGNED && $item->respondentCount>0)
                {
                    $rate = round($item->responseCount/$item->respondentCount*100);
                    $item->progress=$progressBarService->render($rate,$item->responseCount);
                }
                else
                    $item->progress=$item->responseCount;
                $item->canEdit = $surveyModel->canEdit($item);
                $item->canAnalyse = $surveyModel->canAnalyse($item);
            }
        }

        /**
         * Load custum styles
         * There are style definitions that used to set column with for displaying
         * the progress bar.
         */
        $this->wa->useStyle('com_survey.style');
    }
    protected function addToolbarForLayoutDefault(): void
    {
        /**
         * @var SurveysModel $model
         */
        $model = $this->getModel();
        $items = $this->layoutData->items;

        ToolbarHelper::title('Quản lý cuộc khảo sát');
        ToolbarHelper::appendGoHome();

        if($model->canCreate())
            ToolbarHelper::addNew('survey.add');

        if($model->canEditStateAny($items))
        {
            ToolbarHelper::publish('surveys.publish','JTOOLBAR_PUBLISH', true);
            ToolbarHelper::unpublish('surveys.unpublish', 'JTOOLBAR_UNPUBLISH', true);
            ToolbarHelper::archiveList('surveys.archive');
        }

        //Decide whether to show the trash or delete button
        $filterStatus = $model->getState('filter.state');
        if ($filterStatus == StateHelper::STATE_TRASHED && $model->canDeleteAny($items))
            ToolbarHelper::appendDelete('surveys.delete');
        elseif($model->canEditStateAny($items))
            ToolbarHelper::trash('surveys.trash');
    }
}