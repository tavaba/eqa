<?php
namespace Kma\Component\Survey\Administrator\View\Survey;

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Kma\Component\Survey\Administrator\Base\ItemHtmlView;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;
use Kma\Component\Survey\Administrator\Model\SurveyModel;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\FormHelper;
use Kma\Library\Kma\Helper\ToolbarHelper;
use Kma\Library\Kma\View\ListLayoutData;
use Kma\Library\Kma\View\ListLayoutItemFieldOption;
use Kma\Library\Kma\View\ListLayoutItemFields;

defined('_JEXEC') or die;

class HtmlView extends ItemHtmlView
{
    protected ?object $campaign=null;
    protected ListLayoutData $listLayoutData;
    protected ListLayoutItemFields $listLayoutItemFields;
    protected function prepareDataForLayoutAddRespondents(): void
    {
        $surveyId = Factory::getApplication()->input->getInt('survey_id');
        if(empty($surveyId))
            throw new Exception('Invalid request');
        $mvcFactory = ComponentHelper::getMVCFactory();

        //Load current surrvey
        $itemModel = $mvcFactory->createModel('Survey');
        $this->item = $itemModel->getItem($surveyId);
        if(empty($this->item))
            throw new Exception('Invalid request');

        //Load data for list
        $listModel = $mvcFactory->createModel('Respondents');
        if(empty($listModel))
            throw new Exception('Cannot load list model');
        $this->listLayoutData = new ListLayoutData();
        $this->loadCommonListLayoutData($this->listLayoutData, $listModel);
        $isPersonList = (bool)$listModel->getState('filter.is_person');
        $this->listLayoutData->formActionParams = [
            'view' => 'survey',
            'layout'=>'addRespondents',
            'survey_id'=>$surveyId
        ];
        $this->listLayoutData->formHiddenFields = [
            'survey_id'=>$surveyId
        ];

        //Set fields for layout
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
            $option->customFieldset1[] = new ListLayoutItemFieldOption('firstname', 'Tên');
            $option->customFieldset1[] = new ListLayoutItemFieldOption('gender', 'Giới tính',false, false,'text-center');
        }
        else
        {
            $option->customFieldset1[] = new ListLayoutItemFieldOption('name', 'Tên gọi');
        }
        $option->customFieldset1[] = new ListLayoutItemFieldOption('phone', 'Điện thoại',false, false,'text-center');
        $option->customFieldset1[] = new ListLayoutItemFieldOption('email', 'E-mail');
        $this->listLayoutItemFields = $option;

        //Data preprocessing
        if(!empty($this->listLayoutData->items))
        {
            foreach ($this->listLayoutData->items as $item)
            {
                $item->type = RespondentHelper::decodeType($item->type);
                if($item->gender)
                    $item->gender = RespondentHelper::decodeGender($item->gender);
                if(!$isPersonList && $item->is_person)
                    $item->name = implode(' ',[$item->lastname,$item->firstname]);
            }
        }
    }
    protected function addToolbarForLayoutAddRespondents(): void
    {
        ToolbarHelper::title('Thêm người được khảo sát');
        ToolbarHelper::appendButton('save','Thêm','survey.addRespondents',true);
        $cancelUrl = Route::_('index.php?option=com_survey&view=surveyrespondents&survey_id='.$this->item->id, false);
        ToolbarHelper::appendCancelLink($cancelUrl);
    }

    protected function prepareDataForLayoutAddexaminees():void
    {
        $surveyId = Factory::getApplication()->input->getInt('survey_id');
        if(empty($surveyId))
            die('Invalid request');
        $this->item = $this->getModel()->getItem($surveyId);
        if (empty($this->item))
            die('Invalid request');
        $this->form = FormHelper::getBackendForm('com_survey','com_survey.survey.add_examinees','add_examinees.xml',[]);
    }
    protected function addToolbarForLayoutAddExaminees(): void
    {
        ToolbarHelper::title('Thêm thí sinh kỳ thi vào cuộc khảo sát');
        ToolbarHelper::save('survey.addExaminees');
        $cancelUrl = Route::_('index.php?option=com_survey&view=surveyrespondents&survey_id='.$this->item->id, false);
        ToolbarHelper::appendCancelLink($cancelUrl);

    }

    protected function prepareDataForLayoutAnalytics():void
    {
        $input = Factory::getApplication()->input;
        $surveyId = $input->getInt('id');
        if(empty($surveyId))
            die('Invalid request');

        /**
         * @var SurveyModel $model
         */
        $model = $this->getModel();

        $this->item = $model->getItem($surveyId);
        if (empty($this->item))
            die('Invalid request');

        if(!$model->canAnalyse($this->item))
            throw new Exception('You do not have permission to access this page.');


            /*
             * Determine the campaign id if there is
             */
        $campaignId = $this->item->campaign_id;
        if($campaignId)
        {
            $mvcFactory = ComponentHelper::getMVCFactory();
            $campaignModel = $mvcFactory->createModel('Campaign');
            $this->campaign = $campaignModel->getItem($campaignId);
            if(empty($this->campaign))
                die('Invalid request');
        }

        //Load scripts and styles
        $this->wa->useScript('surveyjs.analytics');
        $this->wa->useScript('surveyjs.survey.theme.index');
        $this->wa->useStyle('surveyjs.analytics.style');
    }
    protected function addToolbarForLayoutAnalytics(): void
    {
        ToolbarHelper::title('Phân tích kết quả khảo sát');
        if(!empty($this->item->campaign_id))
            $cancelUrl = Route::_('index.php?option=com_survey&view=campaignsurveys&campaign_id='.$this->item->campaign_id, false);
        else
            $cancelUrl = Route::_('index.php?option=com_survey&view=surveys', false);
        ToolbarHelper::appendCancelLink($cancelUrl,'JTOOLBAR_CLOSE');


        //Print mode tongle
        $this->wa->addInlineScript('
        document.addEventListener("DOMContentLoaded", function() {
            const toolbarContainer = document.querySelector(".subhead-toolbar") 
                || document.querySelector(".toolbar-list") 
                || document.querySelector("#toolbar") 
                || document.querySelector(".page-title + div");
            
            if (toolbarContainer) {
                const btn = document.createElement("button");
                btn.className = "btn btn-primary me-2";
                btn.type = "button";
                btn.id = "export-pdf-button";
                btn.innerHTML = \'<span class="icon-eye-close me-1" aria-hidden="true"></span>Chế độ in\';
                btn.onclick = window.togglePrintMode;
                
                toolbarContainer.insertBefore(btn, toolbarContainer.firstChild);
            }
        });
    ');
    }
}