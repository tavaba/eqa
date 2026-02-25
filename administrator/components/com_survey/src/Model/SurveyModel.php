<?php
namespace Kma\Component\Survey\Administrator\Model;

use Exception;
use Kma\Component\Survey\Administrator\Helper\SurveyHelper;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\DatabaseHelper;
use Kma\Library\Kma\Model\AdminModel;

defined('_JEXEC') or die();

class SurveyModel extends AdminModel
{
    public function canCreate(?string $specificAction = 'com.create.survey'): bool
    {
        return parent::canCreate($specificAction);
    }
    public function canDelete($record = null): bool
    {
        if(parent::canDelete($record))
            return true;

        /**
         * Check whether the given survey is a member of a campaign and
         * the user has access to edit the campaign
         * @var CampaignModel $campaignModel
         */
        if(is_numeric($record))
            $record = $this->getItem($record);
        if(empty($record->campaign_id))
            return false;
        $campaignModel = ComponentHelper::getMVCFactory()->createModel('Campaign');
        return $campaignModel->canEdit($record->campaign_id);
    }
    public function canMonitor(object $surveyItem):bool
    {
        return $this->canDo($surveyItem,'com.monitor.survey',true);
    }

    public function canAnalyse(object $surveyItem):bool
    {
        if($this->canDo($surveyItem,'com.analyse.survey',true))
            return true;

        /**
         * User can also analyse the $surveyItem if the survey is a member of
         * a campaign and the user has permission to analyse the campaign
         * @var CampaignModel $campaignModel
         */
        if(empty($surveyItem->campaign_id))
            return false;
        $campaignModel = ComponentHelper::getMVCFactory()->createModel('Campaign');
        $campaignItem = $campaignModel->getItem($surveyItem->campaign_id);
        return $campaignModel->canAnalyse($campaignItem);
    }
    public function delete(&$pks): bool
    {
        if(is_array($pks))          //Multiple surveys selected
            $surveyId = $pks[0];    //Take the first one
        elseif(is_object($pks))     //Single survey item passed
            $surveyId = $pks->id;   //Get its id
        else
            $surveyId = $pks;
        if(!is_numeric($surveyId))
            throw new Exception('SurveyModel: Invalid survey ID');

        //Check if user can delete
        if(!$this->canDelete($surveyId))
            throw new Exception('You do not have permission to delete this survey.');

        //Check to confirm that noone has responded to the survey yet
        $db = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from('#__survey_responses')
            ->where('survey_id='.$surveyId)
            ->setLimit(1);
        $db->setQuery($query);
        if($db->loadResult()>0)
            throw new Exception('Đã có ý kiến phản hồi, không thể xóa cuộc khảo sát');

        //Get the list of respondent IDs for this survey
        $query = $db->getQuery(true)
            ->select('respondent_id')
            ->from('#__survey_survey_respondent')
            ->where('survey_id='.$surveyId);
        $db->setQuery($query);
        $respondentIds = $db->loadColumn();

        //Remove all respondents from this survey
        $this->removeRespondents($surveyId,$respondentIds);

        //Call parent's delete method
        return parent::delete($pks);
    }
    /**
     * @param int $surveyId The id of the survey we want to add respondents to.
     * @param array $respondentIds An array containing the ids of the respondents that should be added to this survey.
     * @return int The number of added respondents
     * @since 1.0.0
     * @throws Exception
     */
    public function addRespondents(int $surveyId, array $respondentIds):int
    {
        /**
         * STEPS TO TAKES:
         * 1. Get all the current respondents for this survey.
         * 2. Compare them with the new respondents and find out which ones are missing from the list of respondents.
         * 3. Add those respondents to the table `#__survey_respondents` using a single query (INSERT INTO ... SELECT ...)
         */

        $db = DatabaseHelper::getDatabaseDriver();

        //Step 1: Get all the current respondents for this survey.
        $db->setQuery('SELECT respondent_id FROM #__survey_survey_respondent WHERE survey_id='.$surveyId);
        $currentRespondents = $db->loadColumn();

        //Step 2: Find out which respondents are not in the list of current respondents.
        $missingRespondents = array_diff($respondentIds,$currentRespondents);

        /*
         * Step 3: Insert the missing respondents into the table `#__survey_survey_respondent`.
         * And if the current survey is a member of a campaign, then also add the respondents to
         * the table `#__survey_campaign_respondent`
         * Here we must use transactions to ensure data synchronization between both tables
         */
        if(count($missingRespondents)==0)
            return 0;
        $db->transactionStart();
        try{
            //3.1. Insert the missing respondents into the table `#__survey_survey_respondent`.
            $columns = ['survey_id','respondent_id'];
            $values = [];
            foreach ($missingRespondents as $respondentId) {
                $values[] = $surveyId.','.$respondentId;
            }
            $query = $db->getQuery(true)
                ->insert('#__survey_survey_respondent')
                ->columns($columns)
                ->values($values);
            $db->setQuery($query);
            if(!$db->execute())
                throw new Exception('Có lỗi khi thêm người tham gia khảo sát!');

            //3.2. If the current survey is a member of a campaign, then also add the respondents to
            //the table `#__survey_campaign_respondent`
            $campaignId = $this->getItem($surveyId)->campaign_id;
            if($campaignId)
            {
                foreach ($missingRespondents as $respondentId)
                {
                    $query = $db->getQuery(true)
                        ->select('*')
                        ->from('#__survey_campaign_respondent')
                        ->where('campaign_id='.$campaignId)
                        ->where('respondent_id='.$respondentId);
                    $db->setQuery($query);
                    $cr = $db->loadAssoc();
                    if(empty($cr)){
                        $query = $db->getQuery(true)
                            ->insert('#__survey_campaign_respondent')
                            ->columns(['campaign_id','respondent_id','survey_count','response_count'])
                            ->values(implode(',', [$campaignId,$respondentId,1,0]));
                        $db->setQuery($query);
                        if(!$db->execute())
                            throw new Exception('Có lỗi khi thêm người tham gia khảo sát!');
                    }
                    else{
                        $query = $db->getQuery(true)
                            ->update('#__survey_campaign_respondent')
                            ->set('survey_count=survey_count+1')
                            ->where('campaign_id='.$campaignId)
                            ->where('respondent_id='.$respondentId);
                        $db->setQuery($query);
                        if(!$db->execute())
                            throw new Exception('Có lỗi khi thêm người tham gia khảo sát!');
                    }
                }
            }

            //Return the number of respondents that were added to the survey
            $db->transactionCommit();
            return count($missingRespondents);
        }
        catch(Exception $e){
            $db->transactionRollback();
            throw $e;
        }
    }

    /**
     * This method adds respondents by their code instead of their id.
     *
     * @param int $surveyId The id of the survey we want to add respondents to.
     * @param array $respondentCodes An array containing the codes of the respondents that should be added to this survey.
     * @return array An associative array containing information about how many respondents were found, added etc.
     * @throws Exception
     * @since 1.0.0
     */
    public function addRespondentsByCode(int $surveyId, array $respondentCodes): array
    {
        $db = DatabaseHelper::getDatabaseDriver();

        /**
         * STEPS to take:
         * 1. Determine the respondent_ids corresponding to the given codes
         * 2. Call addRespondents() method above with these ids
         */

        //Step 1: Determine the respondent_ids corresponding to the given codes
        $quotedCodes = array_map(function($code){return "'".$code."'";},$respondentCodes);
        $codeSet = '(' . implode(',', $quotedCodes) . ')';
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__survey_respondents')
            ->where('code IN '.$codeSet);
        $db->setQuery($query);
        $respondentIds = $db->loadColumn();

        //Step 2: Call addRespondents() method above with these ids
        $countAdded = $this->addRespondents($surveyId,$respondentIds);

        return [
            'total' => count($respondentCodes),
            'found' => count($respondentIds),
            'added' => $countAdded,
        ];
    }
    public function removeRespondents(int $surveyId, array $respondentIds):int
    {
        /**
         * STEPS TO TAKE:
         * 1. Check if any of the given respondents has already responded to this survey.
         *    If yes, throw an exception
         * 2. Delete the respondents from the table `#__survey_survey_respondent`
         * 3. Return the number of actually deleted respondents
         */
        $db = DatabaseHelper::getDatabaseDriver();
        $respondentIdSet = '(' . implode(',', $respondentIds) . ')';

        //Step 1: Check if any of the given respondents has already responded to this survey.
        $query = $db->getQuery(true)
            ->select('COUNT(1)')
            ->from('#__survey_survey_respondent')
            ->where('survey_id='.$surveyId)
            ->where('respondent_id IN' . $respondentIdSet)
            ->where('responded=1');
        $db->setQuery($query);
        $count = $db->loadResult();
        if($count>0)
            throw new Exception('Không thể xóa người đã phản hồi khảo sát!');

        /**
         * Step 2: Remove the respondents from the table `#__survey_survey_respondent`
         * And if the current survey is a member of a campaign,
         * then accordingly update the table `#__survey_campaign_respondent`.
         * Here we must use transactions to ensure data synchronization between both tables
         */
        $db->transactionStart();
        try {
            //2.1. Remove the respondents from the table `#__survey_survey_respondent`.
            $query = $db->getQuery(true)
                ->delete('#__survey_survey_respondent')
                ->where('survey_id='.$surveyId)
                ->where('respondent_id IN ('.implode(',',$respondentIds).')');
            $db->setQuery($query);
            if(!$db->execute())
                throw new Exception('Có lỗi khi xóa người tham gia khảo sát!');
            $countRemoved = $db->getAffectedRows(); //Number of rows affected by last executed query

            //2.2. Check if the current survey is a member of a campaign
            $campaignId = $this->getItem($surveyId)->campaign_id;
            if($campaignId==null)
                return $countRemoved;

            //2.3. Update the table `#__survey_campaign_respondent`
            //a) Decrement the survey_count field of each respondent
            $query = $db->getQuery(true)
                ->update('#__survey_campaign_respondent')
                ->set('survey_count=survey_count-1')
                ->where('campaign_id='.$campaignId)
                ->where('respondent_id IN '.$respondentIdSet);
            $db->setQuery($query);
            if(!$db->execute())
                throw new Exception('Có lỗi khi xóa người tham gia khảo sát!');
            //b) If the survey_count becomes zero, then delete the record entirely
            $query = $db->getQuery(true)
                ->delete('#__survey_campaign_respondent')
                ->where('survey_count=0');
            $db->setQuery($query);
            if(!$db->execute())
                throw new Exception('Có lỗi khi xóa người tham gia khảo sát!');
            $db->transactionCommit();
        }
        catch(Exception $e){
            $db->transactionRollback();
            throw $e;
        }

        //Step 3: Return the number of removed respondents
        return $countRemoved;
    }

    public function getRespondentIdsFromJunctionIds(array $junctionIds): array
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('respondent_id')
            ->from('#__survey_survey_respondent')
            ->where('id IN ('.implode(',',$junctionIds).')');
        $db->setQuery($query);
        return $db->loadColumn();
    }
    public function generateTokens(int $surveyId, array $respondentIds, bool $overwrite=false):int
    {
        $db = $this->getDatabase();
        $count=0;
        foreach ($respondentIds as $respondentId)
        {
            //Check if a token exists for the given survey, given respondent
            $query = $db->getQuery(true)
                ->select('*')
                ->from('#__survey_tokens')
                ->where('survey_id='.$surveyId)
                ->where('respondent_id='.$respondentId);
            $db->setQuery($query);
            $token = $db->loadObject();

            //If it does exist, update its value only if overwrite is enabled
            if(!empty($token))
            {
                if(!$overwrite)
                    continue; //Skip to next iteration

                //Update the existing token
                $query = $db->getQuery(true)
                    ->update('#__survey_tokens')
                    ->set('token='.$db->quote(SurveyHelper::generateNonNumericToken()))
                    ->where('survey_id='.$surveyId)
                    ->where('respondent_id='.$respondentId);
                $db->setQuery($query);
                if(!$db->execute())
                    throw new Exception('Có lỗi khi tạo token!');
                $count++;
                continue;
            }

            //Otherwise create a new one. Ensure that there are no duplicates
            do {
                $newToken = SurveyHelper::generateNonNumericToken();
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from('#__survey_tokens')
                    ->where('value='.$db->quote($newToken));
                $db->setQuery($query);
            } while ($db->loadResult()>0); //Keep generating tokens until none matches
            $query = $db->getQuery(true)
                ->insert('#__survey_tokens')
                ->columns(['survey_id','respondent_id','value'])
                ->values(implode(',',[$surveyId,$respondentId,$db->quote($newToken)]));
            $db->setQuery($query);
            if(!$db->execute())
                throw new Exception('Có lỗi khi tạo token!');
            $count++;
        }
        return $count;
    }

    public function clearTokens(int $surveyId, array $respondentIds):void
    {
        $db = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->delete('#__survey_tokens')
            ->where('survey_id='.$surveyId. ' AND respondent_id IN ('.implode(',',$respondentIds).')');
        $db->setQuery($query);
        if(!$db->execute())
            throw new Exception('Có lỗi khi xóa token!');
    }

    public function getSurveyForm(int $surveyId): string
    {
        $db = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select('f.model')
            ->from('#__survey_surveys AS s')
            ->leftJoin('#__survey_forms AS f','f.id=s.form_id')
            ->where('s.id='.$surveyId);
        $db->setQuery($query);
        $res = $db->loadResult();
        if(empty($res))
            return '';
        return $res;
    }

    public function getResponses(int $surveyId): array
    {
        $db = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select('data')
            ->from('#__survey_responses')
            ->where('survey_id='.$surveyId);
        $db->setQuery($query);
        $responses = $db->loadColumn();
        if(empty($responses))
            return [];
        return $responses;
    }

}
