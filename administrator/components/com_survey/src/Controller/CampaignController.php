<?php
namespace Kma\Component\Survey\Administrator\Controller;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Router\Route;
use Kma\Component\Survey\Administrator\Model\CampaignModel;
use Kma\Component\Survey\Administrator\Model\ClassesModel;
use Kma\Component\Survey\Administrator\Model\ClassModel;
use Kma\Component\Survey\Administrator\Model\RespondentgroupModel;
use Kma\Component\Survey\Administrator\Model\SurveyModel;
use Kma\Library\Kma\Controller\FormController;


class CampaignController extends  FormController {
    protected function allowAdd($data = [], $specificPermission = 'com.create.survey'): bool
    {
        return parent::allowAdd($data, $specificPermission);
    }
    public function addClassSurveys():void
    {
        try {
            //Check form token
            $this->checkToken();

            //Determine the campaign id
            $campaignId = $this->app->input->getInt('campaign_id');
            if (empty($campaignId))
                throw new Exception('Campaign not found');

            //Get campaign model and check permission
            /**
             * @var CampaignModel $campaignModel
             **/
            $campaignModel = $this->getModel('Campaign');
            $campaign = $campaignModel->getItem($campaignId);
            if(!$campaignModel->canAddSurvey($campaign))
            {
                $msg = 'Bạn không có quyền tạo cuộc khảo sát cho đợt này. 
                Hãy đảm bảo rằng thời hạn phản hồi chưa kết thúc.';
                $redirectUrl = Route::_('index.php?option=com_survey&view=campaignsurveys&campaign_id='.$campaignId,false);
                $this->setMessage($msg,'error');
                $this->setRedirect($redirectUrl);
                return;
            }

            //Try to get form data
            $minClassSize = $this->app->input->getInt('min_class_size',null);

            //PHASE 1: Redirect to input form
            if($minClassSize===null)
            {
                $redirectUrl = Route::_('index.php?option=com_survey&view=campaign&layout=addClassSurveys&campaign_id='.$campaignId,false);
                $this->setRedirect($redirectUrl);
                return;
            }

            //PHASE 2: Process form data and redirect to list view
            $countLate=0;
            $countExisting = 0;
            $countSmallSize=0;

            //1. Continue to get form data
            $skipLateClasses = $this->app->input->getBool('skip_late_classes',false);
            $skipExistingSurveys = $this->app->input->getBool('skip_existing_surveys',true);
            $respectClassEnd = $this->app->input->getBool('respect_class_end',true);
            $respectCampaignStart = $this->app->input->getBool('respect_campaign_start',true);
            $classIds = $this->app->input->get('cid',[],'array');
            $classIds = array_filter($classIds, 'intval');
            $classIds = array_unique($classIds);
            if(empty($classIds))
                throw new Exception('Không có lớp học phần nào được chọn');

            //2. Get information of classes for filtering
            /**
             * @var ClassesModel $classesModel
             **/
            $classesModel = $this->getModel('Classes');
            $classes = $classesModel->getCompactInfo($classIds);
            $campaign = $campaignModel->getItem($campaignId);
            $surveysToCreate = [];
            foreach ($classes as $class)
            {
                //Skip class that has less than min size
                if($class->size==0 || $class->size < $minClassSize) {
                    $countSmallSize++;
                    continue;
                }

                //Skip class whose end date is after campaign start date
                if($class->end_date > $campaign->end_time)
                {
                    $countLate++;
                    if($skipLateClasses)
                        continue;
                    else{
                        $msg = sprintf('Lớp <b>%s %s</b> kết thúc vào ngày %s, sau ngày kết thúc của đợt khảo sát (%s)',
                            $class->subject,
                            $class->code,
                            $class->end_date,
                            $campaign->end_time
                        );
                        throw new Exception($msg);
                    }
                }

                //Check if the survey already exists
                $surveyTitle = "{$class->subject} {$class->code}";
                if($campaignModel->surveyExists($campaignId, $surveyTitle))
                {
                    $countExisting++;
                    if($skipExistingSurveys)
                        continue;
                    else{
                        $msg = sprintf('Cuộc khảo sát <b>%s</b> đã tồn tại trong đợt này', $surveyTitle);
                        throw new Exception($msg);
                    }
                }

                //Prepare survey info
                $survey = [];
                $survey['class_id'] = $class->id;
                $survey['title'] = $surveyTitle;
                $survey['description'] = '';
                $survey['form_id'] = $campaign->form_id;
                $survey['campaign_id'] = $campaignId;
                $survey['auth_mode'] = $campaign->auth_mode;
                $survey['allow_edit_response'] = $campaign->allow_edit_response;
                $survey['strictly_anonymous'] = $campaign->strictly_anonymous;
                $survey['state']=1;
                $survey['end_time'] = $campaign->end_time;
                if($class->end_date < $campaign->start_time)
                    $survey['start_time'] = $respectCampaignStart?$campaign->start_time:$class->end_date;
                else
                    $survey['start_time'] = $respectClassEnd?$class->end_date:$campaign->start_time;
                $surveysToCreate[] = $survey;
            }

            //3. Create surveys
            /**
             * @var SurveyModel $surveyModel
             * @var ClassModel $classModel
             * @var CampaignModel $campaignModel
             */
            $surveyModel = $this->getModel('Survey');
            $classModel = $this->getModel('Class');
            foreach ($surveysToCreate as $survey){
                $classId = $survey['class_id'];
                unset($survey['class_id']);
                $ok = $surveyModel->save($survey);
                if(!$ok)
                    throw new Exception("Tạo cuộc khảo sát {$survey['title']} thất bại: " . $surveyModel->getError());

                //Get the newly created survey id and then clear the state for next iteration
                $surveyId = $surveyModel->getState($surveyModel->getName().'.id');
                $surveyModel->setState($surveyModel->getName().'.id',null);

                //Get class learners and add to survey respondent list
                $respondentIds = $classModel->getLearnerIds($classId);
                $surveyModel->addRespondents($surveyId,$respondentIds);

				//Update the respondent statistics for this campaign
	            foreach ($respondentIds as $respondentId)
	            {
					if (isset($existingRespondents[$respondentId]))
						$existingRespondents[$respondentId]['survey_count']++;
					elseif (isset($newRespondents[$respondentId]))
						$newRespondents[$respondentId]['survey_count']++;
					else
						$newRespondents[$respondentId] = [
							'respondent_id' => $respondentId,
							'survey_count'=>1,
							'response_count'=>0
						];
	            }
            }

            //Set a message and redirect back to list view
            $countCreated = count($surveysToCreate);
            if($countSmallSize>0)
                $this->app->enqueueMessage("Bỏ quả {$countSmallSize} lớp có sĩ số nhỏ hơn mức tối thiểu");
            if($skipLateClasses && $countLate>0)
                $this->app->enqueueMessage("Bỏ qua {$countLate} lớp kết thúc muộn");
            if($skipExistingSurveys && $countExisting>0)
                $this->app->enqueueMessage("Bỏ qua {$countExisting} lớp đã được tạo cuộc khảo sát");
            if($countCreated>0)
                $this->app->enqueueMessage("Đã tạo mới cuộc khảo sát cho {$countCreated} lớp học phần",'success');
            else
                $this->app->enqueueMessage("Không có cuộc khảo sát nào được thêm mới");
            $redirectUrl = Route::_('index.php?option=com_survey&view=campaignsurveys&campaign_id='.$campaignId,false);
            $this->setRedirect($redirectUrl);
        }
        catch(Exception $e) {
            $this->setMessage($e->getMessage(),'error');
            if(empty($campaignId))
                $redirectUrl = Route::_('index.php?option=com_survey&view=campaigns',false);
            else
                $redirectUrl = Route::_('index.php?option=com_survey&view=campaign&layout=addClassSurveys&campaign_id='.$campaignId,false);
            $this->setRedirect($redirectUrl);
        }
    }
    public function addRespondentGroupSurveys():void
    {
        try {
            //Check form token
            $this->checkToken();

            //Determine the campaign id
            $campaignId = $this->app->input->getInt('campaign_id');
            if (empty($campaignId))
                throw new Exception('Campaign not found');

            //Get campaign model and check permission
            /**
             * @var CampaignModel $campaignModel
             **/
            $campaignModel = $this->getModel('Campaign');
            $campaign = $campaignModel->getItem($campaignId);
            if(!$campaignModel->canAddSurvey($campaign))
            {
                $msg = 'Bạn không có quyền tạo cuộc khảo sát cho đợt này. 
                Hãy đảm bảo rằng thời hạn phản hồi chưa kết thúc.';
                $redirectUrl = Route::_('index.php?option=com_survey&view=campaignsurveys&campaign_id='.$campaignId,false);
                $this->setMessage($msg,'error');
                $this->setRedirect($redirectUrl);
                return;
            }

            //Try to get form data
            $respondentGroupIds = $this->app->input->get('respondent_group_ids',[],'array');
            $respondentGroupIds = array_filter($respondentGroupIds,'intval');
            $respondentGroupIds = array_unique($respondentGroupIds);

            //PHASE 1: Redirect to input form
            if(empty($respondentGroupIds))
            {
                $redirectUrl = Route::_('index.php?option=com_survey&view=campaign&layout=addRespondentGroupSurveys&campaign_id='.$campaignId,false);
                $this->setRedirect($redirectUrl);
                return;
            }

            //PHASE 2: Process form data and redirect to list view

            /**
             * 2. Get information of classes for filtering
             * @var RespondentgroupModel $respondentgroupModel
             */
            $respondentgroupModel = $this->getModel('Respondentgroup');
            $campaign = $campaignModel->getItem($campaignId);
            $surveysToCreate = [];
            foreach ($respondentGroupIds as $respondentGroupId)
            {
                $respondentgroup = $respondentgroupModel->getItem($respondentGroupId);
                $surveyTitle = $respondentgroup->title;

                //Prepare survey info
                $survey = [];
                $survey['title'] = $surveyTitle;
                $survey['description'] = '';
                $survey['form_id'] = $campaign->form_id;
                $survey['campaign_id'] = $campaignId;
                $survey['auth_mode'] = $campaign->auth_mode;
                $survey['allow_edit_response'] = $campaign->allow_edit_response;
                $survey['strictly_anonymous'] = $campaign->strictly_anonymous;
                $survey['state']=1;
                $survey['end_time'] = $campaign->end_time;
                $surveysToCreate[] = $survey;
            }

            //3. Create surveys
            /**
             * @var SurveyModel $surveyModel
             * @var ClassModel $classModel
             */
            $surveyModel = $this->getModel('Survey');
            $classModel = $this->getModel('Class');
            foreach ($surveysToCreate as $survey){
                $classId = $survey['class_id'];
                unset($survey['class_id']);
                $ok = $surveyModel->save($survey);
                if(!$ok)
                    throw new Exception("Tạo cuộc khảo sát {$survey['title']} thất bại: " . $surveyModel->getError());

                //Get the newly created survey id and then clear the state for next iteration
                $surveyId = $surveyModel->getState($surveyModel->getName().'.id');
                $surveyModel->setState($surveyModel->getName().'.id',null);

                //Get class learners and add to survey respondent list
                $respondentIds = $classModel->getLearnerIds($classId);
                $surveyModel->addRespondents($surveyId,$respondentIds);
            }

            //Set a message and redirect back to list view
            $countCreated = count($surveysToCreate);
			/*
            if($countSmallSize>0)
                $this->app->enqueueMessage("Bỏ quả {$countSmallSize} lớp có sĩ số nhỏ hơn mức tối thiểu");
            if($skipLateClasses && $countLate>0)
                $this->app->enqueueMessage("Bỏ qua {$countLate} lớp kết thúc muộn");
            if($skipExistingSurveys && $countExisting>0)
                $this->app->enqueueMessage("Bỏ qua {$countExisting} lớp đã được tạo cuộc khảo sát");
			*/
            if($countCreated>0)
                $this->app->enqueueMessage("Đã tạo mới cuộc khảo sát cho {$countCreated} lớp học phần",'success');
            else
                $this->app->enqueueMessage("Không có cuộc khảo sát nào được thêm mới");
            $redirectUrl = Route::_('index.php?option=com_survey&view=campaignsurveys&campaign_id='.$campaignId,false);
            $this->setRedirect($redirectUrl);
        }
        catch(Exception $e) {
            $this->setMessage($e->getMessage(),'error');
            if(empty($campaignId))
                $redirectUrl = Route::_('index.php?option=com_survey&view=campaigns',false);
            else
                $redirectUrl = Route::_('index.php?option=com_survey&view=campaign&layout=addClassSurveys&campaign_id='.$campaignId,false);
            $this->setRedirect($redirectUrl);
        }
    }
    public function deleteSurveys():void
    {
        try {
            //Check token
            $this->checkToken();

            //Determine the campaign id
            $campaignId = $this->app->input->getInt('campaign_id');
            if (empty($campaignId))
                throw new Exception('Campaign not found');

            //Get campaign model and check permission
            /**
             * @var CampaignModel $campaignModel
             **/
            $campaignModel = $this->getModel('Campaign');
            if(!$campaignModel->canEdit($campaignId))
                throw new Exception('Bạn không có quyền xóa cuộc khảo sát trong đợt này');

            //Try to get form data
            $surveyIds = $this->app->input->get('cid',[],'array');
            $surveyIds = array_filter($surveyIds,'intval');
            $surveyIds = array_unique($surveyIds);
            if(empty($surveyIds))
                throw new Exception('Không có cuộc khảo sát nào được chọn để xóa');

            //Delete surveys
            /**
             * @var SurveyModel $surveyModel
             */
            $surveyModel = $this->getModel('Survey');
            $deletedCount = 0;
            foreach ($surveyIds as $surveyId){
                $ok = $surveyModel->delete($surveyId);
                if($ok)
                    ++$deletedCount;
            }

            //Enqueue a message and redirect back to the list view
            $this->setMessage("{$deletedCount} cuộc khảo sát đã được xóa");
            $redirectUrl = Route::_('index.php?option=com_survey&view=campaignsurveys&campaign_id='.$campaignId,false);
            $this->setRedirect($redirectUrl);
        }
        catch(Exception $e) {
            $this->setMessage($e->getMessage(),'error');
            if(empty($campaignId))
                $redirectUrl = Route::_('index.php?option=com_survey&view=campaigns',false);
            else
                $redirectUrl = Route::_('index.php?option=com_survey&view=campaignsurveys&campaign_id='.$campaignId,false);
            $this->setRedirect($redirectUrl);
        }
    }
    public function downloadRawData():void
    {
        try {
            //Determine the campaign id
            $campaignId = $this->app->input->getInt('id');
            if(empty($campaignId))
                throw new Exception('Không xác định được cuộc khảo sát');

            //TODO: Implement this method later
            throw new Exception('Tính năng này đang trong quá trình phát triển...');

            $url = Route::_('index.php?option=com_survey&view=campaigns',false);
            $this->setRedirect($url);
            return;
        }
        catch (Exception $e)
        {
            $this->setMessage($e->getMessage(),'error');
            $url = Route::_('index.php?option=com_survey&view=campaigns',false);
            $this->setRedirect($url);
        }
    }
    public function downloadReport():void
    {
        try {
            //Determine the campaign id
            $campaignId = $this->app->input->getInt('id');
            if(empty($campaignId))
                throw new Exception('Không xác định được cuộc khảo sát');

            //TODO: Implement this method later
            throw new Exception('Tính năng này đang trong quá trình phát triển...');

            $url = Route::_('index.php?option=com_survey&view=campaigns',false);
            $this->setRedirect($url);
            return;
        }
        catch (Exception $e)
        {
            $this->setMessage($e->getMessage(),'error');
            $url = Route::_('index.php?option=com_survey&view=campaigns',false);
            $this->setRedirect($url);
        }
    }
}