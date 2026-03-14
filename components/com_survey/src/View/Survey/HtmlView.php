<?php
namespace Kma\Component\Survey\Site\View\Survey;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Kma\Component\Survey\Administrator\Base\ItemHtmlView;
use Kma\Component\Survey\Administrator\Model\FormModel;
use Kma\Component\Survey\Site\Model\SurveyModel;
use Kma\Library\Kma\Helper\ComponentHelper;

class HtmlView extends ItemHtmlView
{
    protected string $token;
    protected string $surveyFormJson;
    protected function prepareDataForLayoutForm():void
    {
        /**
         * Init required models
         * @var SurveyModel $surveyModel
         * @var FormModel $formModel
         */
        $surveyModel = $this->getModel();
        $formModel = ComponentHelper::createModel('Form', 'Administrator');

        /**
         * Load the survey item. The survey may be specified by an 'id' or a 'token'.
         * If the token is used, we need to find out what id it corresponds to.
         */
        $app = Factory::getApplication();
        $this->token = $app->input->getAlnum('token','');
        if(empty($this->token))
            $surveyId = $app->input->getInt('id');
        else
            $surveyId = $surveyModel->getIdByToken($this->token);
        if(!$surveyId)
            die('Không xác định được cuộc khảo sát');

        $this->item = $surveyModel->getItem($surveyId);
        if (empty($this->item))
            die('Không tìm thấy cuộc khảo sát đã chỉ định');

        /**
         * Load the survey form of the survey
         */
        $surveyForm = $formModel->getItem($this->item->form_id);
        if (!$surveyForm)
            die('Không tìm thấy phiếu khảo sát');
        $this->surveyFormJson = $surveyForm->model;

        /**
         * Load script and style assets for survey form display
         */
        $this->wa->useStyle('surveyjs.core.style')
            ->useScript('surveyjs.core')
            ->useScript('surveyjs.ui')
            ->useScript('surveyjs.survey.i18n');
    }
}