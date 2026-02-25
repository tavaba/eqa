<?php
namespace Kma\Component\Survey\Administrator\Controller;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Exception;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Kma\Component\Survey\Administrator\Helper\ExternalDataHelper;
use Kma\Component\Survey\Administrator\Helper\SurveyHelper;
use Kma\Component\Survey\Administrator\Model\SurveyModel;
use Kma\Component\Survey\Administrator\Service\SurveyReportService;
use Kma\Library\Kma\Controller\FormController;
use Kma\Library\Kma\Helper\IOHelper;
use PhpOffice\PhpWord\PhpWord;

class SurveyController extends  FormController
{
    public function cancel($key = null)
    {
        $res = parent::cancel($key);

        /*
         * If this survey is a member of a campaign, we'll
         * redirect to campaign surveys view
         */
        $campaignId = $this->app->input->getInt('campaign_id');
        if($campaignId)
            $this->setRedirect(Route::_('index.php?option=com_survey&view=campaignsurveys&campaign_id='.$campaignId,false));
        return $res;
    }

    protected function allowAdd($data = [], $specificPermission = 'com.create.survey'): bool
    {
        return parent::allowAdd($data, $specificPermission);
    }
    protected function checkCanAddRespondents(int $surveyId, bool $throw=true): bool
    {
        /**
         * Adding respondents is allowed if all the following conditions hold:
         * 1. The user has permission to edit the survey
         * 2. The authorization mode of the survey is AUTH_MODE_LISTED
         *
         * If any of these conditions does not hold, we will return false.
         */


        /**
         * @var SurveyModel $model
         */
        $model = $this->getModel();

        //1. Check if current user can edit the survey
        if(!$model->canEdit($surveyId))
        {
            if ($throw)
                throw new Exception('Bạn không có quyền thực hiện thao tác này');
            return false;
        }

        //2. Check if the survey's authorization mode is AUTH_MODE_LISTED
        $survey = $model->getItem($surveyId);
        if($survey->auth_mode != SurveyHelper::AUTH_MODE_ASSIGNED)
        {
            if ($throw)
            {
                $msg = match ($survey->auth_mode) {
                    SurveyHelper::AUTH_MODE_ANYONE => 'Bất kỳ ai cũng có thể tham gia khảo sát!',
                    SurveyHelper::AUTH_MODE_AUTHENTICATED => 'Tất cả những người dùng đã đăng nhập đều có thể tham gia khảo sát!',
                    default => '',
                };
                throw new Exception($msg);
            }
            return false;
        }

        //If all conditions hold, we will return true
        return true;
    }
    protected function checkCanRemoveRespondents(int $surveyId,bool $throw=true): bool
    {
        /**
         * Removing respondents is allowed if all the following conditions hold:
         * 1. The user has permission to edit the survey
         *
         * If any of these conditions does not hold, we will return false.
         */

        /**
         * @var SurveyModel $model
         */
        $model = $this->getModel();

        //1. Check if current user can edit the survey
        if (!$model->canEdit($surveyId))
        {
            if ($throw)
                throw new Exception('Bạn không có quyền thực hiện thao tác này');
            return false;
        }

        //If all conditions hold, we will
        return true;
    }

    protected function checkCanViewResults(int $surveyId,bool $throw=true): bool
    {
        /**
         * @var SurveyModel $model
         */
        $model = $this->getModel();
        $item = $model->getItem($surveyId);
        if(!$model->canAnalyse($item))
        {
            if ($throw)
                throw new Exception('Bạn không có quyền xem kết quả của cuộc khảo sát này');
            return false;
        }
        return true;
    }

    public function addRespondents(): void
    {
        try
        {
            //Check token
            $this->checkToken();

            //Determine the survey id
            $surveyId = $this->app->input->getInt('survey_id');
            if(empty($surveyId))
                throw new Exception('Truy vấn không hợp lệ');

            //Check permission
            $this->checkCanAddRespondents($surveyId);

            //Try to get list of respondent ids from request data
            $respondentIds = $this->app->input->get('cid',[],'array');
            $respondentIds = array_filter($respondentIds, 'intval');


            //PHASE 1: Redirect to 'addrespondents' layout
            if(empty($respondentIds))
            {
                $redirectUrl = Route::_('index.php?option=com_survey&view=survey&layout=addrespondents&survey_id='.$surveyId,false);
                $this->setRedirect($redirectUrl);
                return;
            }

            //PHASE 2: Add respondents to survey
            /**
             * @var SurveyModel $model
             */
            $model = $this->getModel();
            $countAdded = $model->addRespondents($surveyId,$respondentIds);

            //Show message and redirect back to survey page
            $msg = sprintf('%d/%d người đã được thêm vào cuộc khảo sát',
                $countAdded, count($respondentIds));
            $this->setMessage($msg,'success');
            $redirectUrl = Route::_('index.php?option=com_survey&view=surveyrespondents&survey_id='.$surveyId,false);
            $this->setRedirect($redirectUrl);
            return;
        }
        catch (Exception $e)
        {
            $this->setMessage($e->getMessage(), 'error');
            if(empty($surveyId))
                $redirectUrl = Route::_('index.php?option=com_survey&view=surveys',false);
            else
                $redirectUrl = Route::_('index.php?option=com_survey&view=surveyrespondents&survey_id='.$surveyId,false);
            $this->setRedirect($redirectUrl);
            return;
        }
    }
    public function addExaminees():void
    {
        try {
            //Check token
            $this->checkToken();

            //Determine the survey id
            $surveyId = $this->app->input->getInt('survey_id');
            if(empty($surveyId))
                throw new Exception('Truy vấn không hợp lệ');

            //Check permissions
           $this->checkCanAddRespondents($surveyId);

            //Try to get the examseason id
            $examSeasonId = $this->app->input->getInt('examseason_id');

            //PHASE 1: Redirect to 'addexaminees' layout
            if(empty($examSeasonId)){
                $redirectUrl = Route::_('index.php?option=com_survey&view=survey&layout=addExaminees&survey_id='.$surveyId,false);
                $this->setRedirect($redirectUrl);
                return;
            }

            /**
             * PHASE 2: Retrieve examinees from external source and then add them into the survey
             * @var SurveyModel $model
             */
            $examinees = ExternalDataHelper::fetchExamseasonExaminees($examSeasonId);
            if (empty($examinees))
                throw new Exception('Không tìm thấy thí sinh nào');
            $model = $this->getModel();
            $count = $model->addRespondentsByCode($surveyId, $examinees);
            $msg = sprintf('Tổng cộng có %d thí sinh: %d/%d có trong danh sách, %d/%d được thêm vào cuộc khảo sát',
                $count['total'],
                $count['found'], $count['total'],
                $count['added'], $count['total']
            );
            $this->setMessage($msg,'success');
            $redirectUrl = Route::_('index.php?option=com_survey&view=surveyrespondents&survey_id='.$surveyId,false);
            $this->setRedirect($redirectUrl);
            return;
        }
        catch (Exception $e)
        {
            $this->setMessage($e->getMessage(),'error');
            if(empty($surveyId))
                $redirectUrl = Route::_('index.php?option=com_survey&view=surveys',false);
            else
                $redirectUrl = Route::_('index.php?option=com_survey&view=surveyrespondents&survey_id='.$surveyId,false);
            $this->setRedirect($redirectUrl);
        }
    }
    public function removeRespondents(): void
    {
        try
        {
            //Check token
            $this->checkToken();

            //Determine the survey id
            $surveyId = $this->app->input->getInt('survey_id');
            if(empty($surveyId))
                throw new Exception('Truy vấn không hợp lệ');

            //Check permission
            $this->checkCanRemoveRespondents($surveyId);

            /**
             * Load the model
             * @var SurveyModel $model
             */
            $model = $this->getModel();

            //Try to get list of respondent ids from request data
            //Get ids of respondents that need to be generated tokens for
            //The IDs came from request data are IDs in the junction table
            // #__survey_survey_respondent, but not the IDs of the respondents themselves.
            $junctionIds = $this->app->input->get('cid',[],'array');
            $junctionIds = array_filter($junctionIds, 'intval');
            $junctionIds = array_unique($junctionIds);
            $respondentIds = $model->getRespondentIdsFromJunctionIds($junctionIds);
            if(empty($respondentIds))
                throw new Exception('Không tìm thấy người cần xóa');

            //Remove respondents from survey
            $countRemoved = $model->removeRespondents($surveyId,$respondentIds);
            $msg = sprintf('%d người đã bị xóa khỏi cuộc khảo sát',$countRemoved);
            $this->setMessage($msg,'success');
            $redirectUrl = Route::_('index.php?option=com_survey&view=surveyrespondents&survey_id='.$surveyId,false);
            $this->setRedirect($redirectUrl);
            return;
        }
        catch(Exception $e) {
            $this->setMessage($e->getMessage(),'error');
            if(empty($surveyId)) {
                $redirectUrl = Route::_('index.php?option=com_survey&view=surveys',false);
            }else {
                $redirectUrl = Route::_('index.php?option=com_survey&view=surveyrespondents&survey_id='.$surveyId,false);
            }
            $this->setRedirect($redirectUrl);
        }
    }

    public function generateTokens(): void
    {
        try
        {
            //Check token
            $this->checkToken();

            //Dertermine the survey id
            $surveyId = $this->app->input->getInt('survey_id');
            if(empty($surveyId))
                throw new Exception('Truy vấn không hợp lệ');

            //Check permissions
            /**
             * @var SurveyModel $model
             */
            $model = $this->getModel();
            if(!$model->canEdit($surveyId))
                throw new Exception('Bạn không có quyền thực hiện thao tác này');

            //Get ids of respondents that need to be generated tokens for
            //The IDs came from request data are IDs in the junction table
            // #__survey_survey_respondent, but not the IDs of the respondents themselves.
            $junctionIds = $this->app->input->get('cid',[],'array');
            $junctionIds = array_filter($junctionIds, 'intval');
            $junctionIds = array_unique($junctionIds);
            $respondentIds = $model->getRespondentIdsFromJunctionIds($junctionIds);
            if(empty($respondentIds))
                throw new Exception('Không tìm thấy người cần tạo mã truy cập');

            //Generate tokens for those respondents
            $countGenerated = $model->generateTokens($surveyId,$respondentIds);
            $msg = sprintf('Đã sinh token cho %d người được khảo sát',$countGenerated);
            $this->setMessage($msg,'success');
            $redirectUrl = Route::_('index.php?option=com_survey&view=surveyrespondents&survey_id='.$surveyId,false);
            $this->setRedirect($redirectUrl);
        }
        catch (Exception $e){
            $this->setMessage($e->getMessage(),'error');
            if(empty($surveyId))
                $redirectUrl = Route::_('index.php?option=com_survey&view=surveys',false);
            else
                $redirectUrl = Route::_('index.php?option=com_survey&view=surveyrespondents&survey_id='.$surveyId,false);
            $this->setRedirect($redirectUrl);
        }
    }
    public function clearTokens(): void
    {
        try
        {
            //Check token
            $this->checkToken();

            //Dertermine the survey id
            $surveyId = $this->app->input->getInt('survey_id');
            if(empty($surveyId))
                throw new Exception('Truy vấn không hợp lệ');

            //Check permissions
            /**
             * @var SurveyModel $model
             */
            $model = $this->getModel();
            if(!$model->canEdit($surveyId))
                throw new Exception('Bạn không có quyền thực hiện thao tác này');

            //Get ids of respondents that need to be generated tokens for
            //The IDs came from request data are IDs in the junction table
            // #__survey_survey_respondent, but not the IDs of the respondents themselves.
            $junctionIds = $this->app->input->get('cid',[],'array');
            $junctionIds = array_filter($junctionIds, 'intval');
            $junctionIds = array_unique($junctionIds);
            $respondentIds = $model->getRespondentIdsFromJunctionIds($junctionIds);
            if(empty($respondentIds))
                throw new Exception('Không tìm thấy người cần xóa mã truy cập');

            //Generate tokens for those respondents
            $model->clearTokens($surveyId,$respondentIds);
            $msg = sprintf('Đã xóa token cho %d người được khảo sát',count($respondentIds));
            $this->setMessage($msg,'success');
            $redirectUrl = Route::_('index.php?option=com_survey&view=surveyrespondents&survey_id='.$surveyId,false);
            $this->setRedirect($redirectUrl);
        }
        catch (Exception $e){
            $this->setMessage($e->getMessage(),'error');
            if(empty($surveyId))
                $redirectUrl = Route::_('index.php?option=com_survey&view=surveys',false);
            else
                $redirectUrl = Route::_('index.php?option=com_survey&view=surveyrespondents&survey_id='.$surveyId,false);
            $this->setRedirect($redirectUrl);
        }
    }

    public function dashboardDataJson():void
    {
        try
        {
            $input = $this->app->input;
            $surveyId = $input->getInt('id');

            //Check permission
            $this->checkCanViewResults($surveyId, true);

            /**
             * Load survey model
             * @var SurveyModel $surveyModel
             */
            $surveyModel = $this->getModel();
            $form = $surveyModel->getSurveyForm($surveyId);

            //Get and decode responses to arrays
            $responses = $surveyModel->getResponses($surveyId);
            $decodedResponses=[];
            foreach ($responses as $response)
            {
                $decodedResponses[] = json_decode($response,true);
            }

            //Prepare dashboard data
            $data = [
                'model' => json_decode($form, true),
                'responses' => $decodedResponses,
            ];

            /**
             * Return response
             * JsonResponse automatically sets headers (Content-Type: application/json).
             * It outputs a JSON object like:
             *
             * {
             *      "success": true,
             *      "message": "",
             *      "data":
             *      {
             *          "model": {...},
             *          "responses": [...]
             *      }
             * }
             */
            echo new JsonResponse($data);
            $this->app->close();
        }
        catch (Exception $e)
        {
            echo new JsonResponse(null,$e->getMessage(),true);
            $this->app->close();
        }
    }

    public function downloadRawData():void
    {
        try {
            //Determine the survey id
            $surveyId = $this->app->input->getInt('id');
            if(empty($surveyId))
                throw new Exception('Không xác định được cuộc khảo sát');

            //TODO: Implement this method later
            throw new Exception('Tính năng này đang trong quá trình phát triển...');

            /**
             * Load survey model
             * @var SurveyModel $surveyModel
             */
            $surveyModel = $this->getModel();


            //Decide the redirection target
            $survey = $surveyModel->getItem($surveyId);
            if(empty($survey->campaign_id))
                $url = Route::_('index.php?option=com_survey&view=surveys',false);
            else
                $url = Route::_('index.php?option=com_survey&view=campaignsurveys&id='.$survey->campaign_id,false);
            $this->setRedirect($url);
            return;
        }
        catch (Exception $e)
        {
            $this->setMessage($e->getMessage(),'error');

            //Determine the campaign id
            if(!empty($surveyId))
            {
                $surveyModel = $this->getModel();
                if(!empty($surveyModel))
                {
                    $survey = $surveyModel->getItem($surveyId);
                    if(!empty($survey))
                        $campaignId = $survey->campaign_id;
                }
            }

            //Decide the redirection target
            if(empty($campaignId))
                $url = Route::_('index.php?option=com_survey&view=surveys',false);
            else
                $url = Route::_('index.php?option=com_survey&view=campaignsurveys&id='.$campaignId,false);
            $this->setRedirect($url);
        }
    }
    public function downloadWordReport():void
    {
        try {
            //Determine the survey id
            $surveyId = $this->app->input->getInt('id');
            if(empty($surveyId))
                throw new Exception('Không xác định được cuộc khảo sát');

            //Check permissions
            $this->checkCanViewResults($surveyId, true);

            /**
             * Load survey model
             * @var SurveyModel $surveyModel
             */
            $surveyModel = $this->getModel();
            $survey = $surveyModel->getItem($surveyId);
            $surveyForm = $surveyModel->getSurveyForm($surveyId);
            $responses = $surveyModel->getResponses($surveyId);
            if(empty($responses))
                throw new Exception('Không có dữ liệu để xuất báo cáo');
            $doc = new PhpWord();
            $reportService = new SurveyReportService($surveyForm, $responses, $survey->title);
            $reportService->writeReportToWord($doc);
            $fileName = 'Báo cáo '.$survey->title.'.docx';
            IOHelper::sendHttpDocx($doc, $fileName);
            jexit();
       }
        catch (Exception $e)
        {
            $this->setMessage($e->getMessage(),'error');

            //Determine the campaign id
            if(!empty($surveyId))
            {
                $surveyModel = $this->getModel();
                if(!empty($surveyModel))
                {
                    $survey = $surveyModel->getItem($surveyId);
                    if(!empty($survey))
                        $campaignId = $survey->campaign_id;
                }
            }

            //Decide the redirection target
            if(empty($campaignId))
                $url = Route::_('index.php?option=com_survey&view=surveys',false);
            else
                $url = Route::_('index.php?option=com_survey&view=campaignsurveys&campaign_id='.$campaignId,false);
            $this->setRedirect($url);
        }
    }
}