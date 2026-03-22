<?php
namespace Kma\Component\Survey\Site\Model;

use Exception;
use Kma\Component\Survey\Administrator\Base\AdminModel;

defined('_JEXEC') or die();

class SurveyModel extends AdminModel
{
    public function getIdByToken(string $token): int|null
    {
        $db = $this->getDatabase();
        $query= $db->getQuery(true)
            ->select('survey_id')
            ->from('#__survey_tokens')
            ->where($db->quoteName('value').'='.$db->quote($token));
        return $db->setQuery($query)->loadResult();
    }
    public function hasResponded($surveyId,$respondentId):bool
    {
        $db=$this->getDatabase();
        $query =  $db->getQuery(true)
            ->select('COUNT(1)')
            ->from('#__survey_survey_respondent')
            ->where('survey_id='.$surveyId.' AND respondent_id='.$respondentId . ' AND responded=1')
            ->setLimit(1);
        $db->setQuery($query);
        return $db->loadResult()>0;
    }
    public function isAssigned(int $surveyId, int $respondentId):bool
    {
        $db=$this->getDatabase();
        $query =  $db->getQuery(true)
            ->select('COUNT(1)')
            ->from('#__survey_survey_respondent')
            ->where('survey_id='.$surveyId.' AND respondent_id='.$respondentId)
            ->setLimit(1);
        $db->setQuery($query);
        return $db->loadResult()>0;
    }
    public function saveResponse(int $surveyId, string $response, int|null $respondentId): void
    {
        $db=$this->getDatabase();
        $survey = $this->getItem($surveyId);
        if(empty($survey))
            throw new Exception("Invalid survey");
        $isStrictlyAnonymous = $survey->strictly_anonymous;


        /**
         * If $respondentId is null, just add new record into the #__survey_responses table
         * with survey_id and response.
         */
        if(empty($respondentId))
        {
            $query = $db->getQuery(true)
                ->insert('#__survey_responses')
                ->columns(['survey_id','data'])
                ->values(implode(',', [$surveyId,$db->quote($response)]));
            $db->setQuery($query);
            if(!$db->execute())
                throw new Exception('Có lỗi khi ghi nhận ý kiến phản hồi');
            return;
        }

        /**
         * Load information from the junction table
         * #__survey_survey_respondent for this combination of survey_id and respondent_id.
         */
        $query = $db->getQuery(true)
            ->select('*')
            ->from('#__survey_survey_respondent')
            ->where('survey_id='.$surveyId.' AND respondent_id='.$respondentId);
        $db->setQuery($query);
        $sr = $db->loadObject();

        /*
         * If the respondent has not responded to this survey yet:
         *
         * - Add new record into the #__survey_responses table with survey_id and response.
         *   Get the id of that record.
         * - If the respondent has been assigned to the survey before
         *   (i.e. there's an entry in the #__survey_survey_respondent table), we'll need to update
         *   the record with the new response_id value.
         * - Otherwise insert a new record into the #__survey_survey_respondent table with
         *   survey_id, respondent_id and response_id.
         * - If the survey is a member of a campaign, we must update the table
         *   #__survey_campaign_respondent accordingly.
         */
        if(empty($sr) || $sr->responded==0)
        {
            $db->transactionStart();
            try {
                //1. Add new record into the #__survey_responses table with survey_id
                // and get the id of that record.
                $query = $db->getQuery(true)
                    ->insert('#__survey_responses')
                    ->columns(['survey_id','data'])
                    ->values(implode(',', [$surveyId,$db->quote($response)]));
                $db->setQuery($query);
                if(!$db->execute())
                    throw new Exception('Có lỗi khi ghi nhận ý kiến phản hồi');
                $responseId = $db->insertid(); //Get the ID of the last inserted row

                //2a. If the respondent has been assigned to the survey before
                // (i.e. there's an entry in the #__survey_survey_respondent table),
                // we'll need to update the record with the new response_id value.
                if(!empty($sr))
                {
                    $setClauses=['responded=1'];
                    if(!$isStrictlyAnonymous)
                        $setClauses[]='response_id='.$responseId;
                    $query = $db->getQuery(true)
                        ->update('#__survey_survey_respondent')
                        ->set($setClauses)
                        ->where('survey_id='.$surveyId.' AND respondent_id='.$respondentId);
                }

                //2b. Otherwise, insert a new record into the #__survey_survey_respondent table with
                // survey_id, respondent_id and response_id.
                else
                {
                    $columns=['survey_id','respondent_id','response_id','responded'];
                    $values = [
                        $surveyId,
                        $respondentId,
                        $isStrictlyAnonymous?'NULL':$responseId,
                        1
                    ];
                    $query = $db->getQuery(true)
                        ->insert('#__survey_survey_respondent')
                        ->columns($columns)
                        ->values(implode(',',$values));
                }
                $db->setQuery($query);
                if(!$db->execute())
                    throw new Exception('Có lỗi khi ghi nhận ý kiến phản hồi');

                /*
                 * 3. If the survey is a member of a campaign, we must update the table
                 * #__survey_campaign_respondent accordingly.
                 */
                if(!empty($survey->campaign_id))
                {
                    //3a. Check whether the respondent has been assigned to the campaign before
                    $query = $db->getQuery(true)
                        ->select('*')
                        ->from('#__survey_campaign_respondent')
                        ->where([
                            'campaign_id=' . $survey->campaign_id,
                            'respondent_id=' . $respondentId
                        ]);
                    $db->setQuery($query);
                    $cr = $db->loadObject();

                    //3b. If NO, we will create a new record in the #__survey_campaign_respondent table
                    if(empty($cr))
                    {
                        $query = $db->getQuery(true)
                            ->insert('#__survey_campaign_respondent')
                            ->columns(['campaign_id', 'respondent_id', 'survey_count', 'response_count'])
                            ->values(implode(',', [$survey->campaign_id, $respondentId, 1, 1]));
                    }

                    //3c. If YES, and the respondent was added to current survey before,
                    // we will increment the 'response_count' field
                    elseif(!empty($sr))
                    {
                        $query = $db->getQuery(true)
                            ->update('#__survey_campaign_respondent')
                            ->set('response_count=response_count+1')
                            ->where([
                                'campaign_id=' . $survey->campaign_id,
                                'respondent_id=' . $respondentId
                            ]);
                    }

                    //3d. If YES, and the respondent was NOT added to current survey before,
                    // we will increment both 'survey_count' and 'response_count'
                    else
                    {
                        $query = $db->getQuery(true)
                            ->update('#__survey_campaign_respondent')
                            ->set(['survey_count=survey_count+1','response_count=response_count+1'])
                            ->where([
                                'campaign_id=' . $survey->campaign_id,
                                'respondent_id=' . $respondentId
                            ]);
                    }
                    $db->setQuery($query);
                    if (!$db->execute())
                        throw new Exception('Có lỗi khi ghi nhận ý kiến phản hồi');
                }
                $db->transactionCommit();
            }
            catch(Exception $e){
                $db->transactionRollback();
                throw $e;
            }
            return;
        }

        //If the respondent has responded already, but noway to update
        if($isStrictlyAnonymous || empty($sr->response_id))
            throw new Exception('Không thể cập nhật ý kiến phản hồi này');

        //If the respondent has responded, and we can update existing record
        // in the #__survey_responses table
        $query = $db->getQuery(true)
            ->update('#__survey_responses')
            ->set('data='.$db->quote($response))
            ->where('id='.$sr->response_id);
        $db->setQuery($query);
        if(!$db->execute())
            throw new Exception('Có lỗi khi cập nhật ý kiến phản hồi');
    }
}
