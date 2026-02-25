<?php
namespace Kma\Component\Survey\Administrator\View\SurveyRespondents;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Survey\Administrator\Base\ItemsHtmlView;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;
use Kma\Component\Survey\Administrator\Model\SurveyModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

class HtmlView extends ItemsHtmlView
{
    protected function configureItemFieldsForLayoutDefault(): void
    {
        $model = $this->getModel();
        $isPersonList = $model->getState('filter.is_person');

        $option = new ListLayoutItemFields();

        $option->sequence = ListLayoutItemFields::defaultFieldSequence();
        $option->check = ListLayoutItemFields::defaultFieldCheck();

        $option->customFieldset1 = array();
        $option->customFieldset1[] = new ListLayoutItemFieldOption('type', 'Phân loại');
        $f = new ListLayoutItemFieldOption('code', 'Mã',true,false,'text-center');
        $f->altField = 'note';
        $option->customFieldset1[] = $f;
        if($isPersonList)
        {
            $option->customFieldset1[] = new ListLayoutItemFieldOption('lastname', 'Họ đệm');
            $option->customFieldset1[] = new ListLayoutItemFieldOption('firstname', 'Tên', true);
            $option->customFieldset1[] = new ListLayoutItemFieldOption('gender', 'Giới tính',false, false,'text-center');
        }
        else
        {
            $option->customFieldset1[] = new ListLayoutItemFieldOption('name', 'Tên gọi');
        }
        $option->customFieldset1[] = new ListLayoutItemFieldOption('token', 'Token');
        $f = new ListLayoutItemFieldOption('responded','Đã phản hồi',true, false,'text-center');
        $f->displayAsBooleanSign = true;
        $option->customFieldset1[] = $f;
        $option->customFieldset1[] = new ListLayoutItemFieldOption('phone', 'Điện thoại',false, false,'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('email', 'E-mail');

        //Set the option
        $this->itemFields = $option;
    }
    protected function prepareDataForLayoutDefault(): void
    {
        //Prepare the model
        $surveyId = Factory::getApplication()->input->getInt('survey_id');
        if(empty($surveyId))
            die('Invalid request');
        $model = $this->getModel();
        $model->setState('filter.survey_id',$surveyId);

        //Get survey item
        $mvcFactory = ComponentHelper::getMVCFactory();
        $surveyModel = $mvcFactory->createModel('Survey', 'Administrator');
        $this->item = $surveyModel->getItem($surveyId);
        if(empty($this->item))
            die('Invalid request');

        //Prepare data for layout
        parent::prepareDataForLayoutDefault();

        //Data preprocessing
        $isPersonList = $model->getState('filter.is_person');
        if(!empty($this->layoutData->items))
        {
            foreach ($this->layoutData->items as $item)
            {
                $item->type = RespondentHelper::decodeType($item->type);
                if($item->gender)
                    $item->gender = RespondentHelper::decodeGender($item->gender);
                if(!$isPersonList && $item->isPerson)
                    $item->name = implode(' ',[$item->lastname,$item->firstname]);
            }
        }

        //Add a hidden field to store the survey id
        $this->layoutData->formHiddenFields['survey_id'] = $surveyId;

        //Add action params to keep URL parameters when click on actions
        $this->layoutData->formActionParams = [
            'view' => 'surveyrespondents',
            'survey_id'=>$surveyId,
        ];
    }
    protected function addToolbarForLayoutDefault(): void
    {
        ToolbarHelper::title('Người tham gia cuộc khảo sát');
        ToolbarHelper::appendGoHome();

        /**
         * A very special button.
         * If the current survey is a single one, the button leads to 'Surveys' view,
         * otherwise it leads to 'CampaignSurveys' view.
         */
        $currentSurvey = $this->item;
        if($currentSurvey->campaign_id == 0)
            $url = Route::_('index.php?option=com_survey&view=surveys',false);
        else
            $url = Route::_('index.php?option=com_survey&view=campaignsurveys&campaign_id='.$currentSurvey->campaign_id,false);
        ToolbarHelper::appendGobackLink($url,'Cuộc khảo sát');

        /**
         * Load model to check access permission
         * A user can add/remove respondents only if he/she has permission to edit the survey.
         * @var SurveyModel $model
         */
        $model = ComponentHelper::getMVCFactory()->createModel('Survey');
        $survey = $this->item;
        if($model->canEdit($survey))
        {

            ToolbarHelper::appendButton('users','Thêm người', 'survey.addRespondents',false,'btn btn-success');
            ToolbarHelper::appendButton('puzzle','Thêm thí sinh kỳ thi', 'survey.addExaminees',false,'btn btn-success');
            ToolbarHelper::appendButton('key','Sinh token', 'survey.generateTokens',true,'btn btn-success');
            ToolbarHelper::appendButton('cancel-circle','Hủy token', 'survey.clearTokens',true,'btn btn-danger');
            ToolbarHelper::appendDelete('survey.removeRespondents');
        }
    }
}