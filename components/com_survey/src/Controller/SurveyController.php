<?php
namespace Kma\Component\Survey\Site\Controller;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Kma\Component\Survey\Administrator\Helper\SurveyHelper;
use Kma\Component\Survey\Site\Model\SurveyModel;
use Kma\Library\Kma\Helper\StateHelper;

defined('_JEXEC') or die;


class SurveyController extends BaseController
{
    protected function checkCanRespond(int $surveyId, int|null $respondentId, bool $throw=false): bool
    {
        /**
         * Load the model
         * @var SurveyModel $surveyModel
         */
        $surveyModel = $this->getModel();
        $survey = $surveyModel->getItem($surveyId);
        if(empty($survey))
        {
            if ($throw)
                throw new Exception('Không tìm thấy cuộc khảo sát');
            return false;
        }

        //If the survey is not published, then we cannot respond to it.
        if($survey->state != StateHelper::STATE_PUBLISHED)
        {
            if ($throw)
                throw new Exception('Cuộc khảo sát đã bị vô hiệu');
            return false;
        }

        //If the deadline is over or the start time hasn't passed yet,
        // then we cannot respond to it.
        $now = Factory::getDate()->toSql();
        if($survey->end_time < $now || $survey->start_time > $now)
        {
            if ($throw)
                throw new Exception('Đã hết hạn hoặc chưa đến hạn khảo sát');
            return false;
        }

        //If the survey requires the user to be a respondent,
        // but the current user isn't one of them,
        if(empty($respondentId)
            && ($survey->auth_mode==SurveyHelper::AUTH_MODE_RESPONDENT || $survey->auth_mode==SurveyHelper::AUTH_MODE_ASSIGNED))
        {
            if ($throw)
                throw new Exception('Bạn không có quyền tham gia khảo sát này');
            return false;
        }

        //If the survey requires the user to be explicitly assigned as a respondent,
        // but the user isn't an explicit respondent for that survey,
        if($survey->auth_mode == SurveyHelper::AUTH_MODE_ASSIGNED && !$surveyModel->isAssigned($surveyId,$respondentId))
        {
            if ($throw)
                throw new Exception('Bạn không có quyền tham gia khảo sát này');
            return false;
        }

        //If the current user has already responded to this survey,
        // and the survey doesn't allow edit responses (or it is strictly anonymous),
        // then user cannot respond to it again.
        if($respondentId > 0 && $surveyModel->hasResponded($surveyId,$respondentId) && ($survey->strictly_anonymous || !$survey->allow_edit_response))
        {
            if ($throw)
                throw new Exception('Bạn đã gửi ý kiến cho nội dung khảo sát này rồi');
            return false;
        }



        //If all the above checks pass,
        return true;
    }
    public function respond():void
    {
        try
        {
            //Check token
            $this->checkToken();

            /**
             * Prepare the model
             * @var SurveyModel $surveyModel
             */
            $surveyModel = $this->getModel();

            //Get and validate request data
            $token = $this->input->getAlnum('token', '');
            if(empty($token)) {
                $surveyId = $this->input->getInt('id', 0);
                if(empty($surveyId))
                    throw new Exception('Không xác định được cuộc khảo sát');
            }
            else {
                $surveyId = $surveyModel->getIdByToken($token);
                if(empty($surveyId))
                    throw new Exception('Token không hợp lệ');
            }
            $response = $this->input->getRaw('response', '');
            if(!json_decode($response))
                throw new Exception('Cấu trúc phản hồi không hợp lệ');

            //Authorization
            $respondent = $this->app->get('respondent');
            if(isset($respondent->id))
                $respondentId = $respondent->id;
            else
                $respondentId = 0;
            $this->checkCanRespond($surveyId, $respondentId, true);

            //Save response
            $ok = $surveyModel->saveResponse($surveyId, $response, $respondentId);

            //Set a message and redirect
            if($ok)
                $this->setMessage('Ý kiến phản hồi đã được ghi nhận thành công', 'success');
            else
                $this->setMessage('Lỗi khi lưu ý kiến phản hồi', 'error');
            $redirect = Route::_('index.php?option=com_survey&view=surveys',false);
            $this->setRedirect($redirect);
        }
        catch (Exception $e) {
            $this->setMessage($e->getMessage(), 'error');
            $redirect = Route::_('index.php?option=com_survey&view=surveys',false);
            $this->setRedirect($redirect);
        }
    }
}