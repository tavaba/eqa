<?php
namespace Kma\Component\Survey\Site\View\Surveys;

defined('_JEXEC') or die;

use Kma\Component\Survey\Administrator\Base\ItemsHtmlView;
use Kma\Component\Survey\Administrator\Enum\AuthorizationMode;
use Kma\Component\Survey\Administrator\Helper\SurveyHelper;
use Kma\Library\Kma\Helper\StateHelper;
use Kma\Library\Kma\Helper\DatetimeHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
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
        $f = new ListLayoutItemFieldOption('title', 'Cuộc khảo sát',false,false);
        $f->urlFormatString = 'index.php?option=com_survey&view=survey&layout=form&id=%d';
        $f->urlFormatStringField = 'id';
        $f->showLinkConditionField='canRespond';
        $f->showLinkConditionValue=true;
        $f->altField='authModeText';
        $option->customFieldset1[] = $f;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('startTime','Bắt đầu',true,false,'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('endTime','Kết thúc',true,false,'text-center');
        $f = new ListLayoutItemFieldOption('responded','Phản hồi',false, false,'text-center');
        $f->printRaw = true;
        $option->customFieldset1[] = $f;

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        parent::prepareDataForLayoutDefault();

        //Data preprocessing
        if(!empty($this->layoutData->items))
        {
            foreach ($this->layoutData->items as $item) {
                //Field: 'responded'
                $hasResponded = $item->responded;
                if (is_null($hasResponded))
                    $item->responded = 'N/A';
                elseif ($hasResponded == 0)
                    $item->responded = '<span class="tbody-icon"><span class="icon-unpublish" aria-hidden="true"></span></span>';
                else
                    $item->responded = '<span class="tbody-icon active" ><span class="icon-publish" aria-hidden="true"></span></span>';

                //Show link condition for field: 'title'
                $item->canRespond  = DatetimeHelper::isTimeOver($item->startTime)
                    && !DatetimeHelper::isTimeOver($item->endTime)
                    && !$hasResponded;

                //Field: authModeText
                $item->authModeText = 'Quyền gửi ý kiến: ' . AuthorizationMode::from($item->authMode)->getLabel();

                //Row CSS
                if($hasResponded)
                    $item->optionRowCssClass = 'table-success';
                elseif(DatetimeHelper::isTimeOver($item->startTime))//StartTime has passed
                    $item->optionRowCssClass = 'table-warning';
                elseif(DatetimeHelper::isTimeOver($item->endTime))//EndTime has passed
                    $item->optionRowCssClass = 'table-danger';
            }
        }
    }
    protected function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title('Quản lý cuộc khảo sát');
        ToolbarHelper::appendGoHome();
        ToolbarHelper::addNew('survey.add');
        ToolbarHelper::publish('surveys.publish','JTOOLBAR_PUBLISH', true);
        ToolbarHelper::unpublish('surveys.unpublish', 'JTOOLBAR_UNPUBLISH', true);
        ToolbarHelper::archiveList('surveys.archive');

        //Decide whether to show the trash or delete button
        $model = $this->getModel();
        $filterStatus = $model->getState('filter.state');
        if ($filterStatus == StateHelper::STATE_TRASHED)
            ToolbarHelper::appendDelete('surveys.delete');
        else
            ToolbarHelper::trash('surveys.trash');
    }
}