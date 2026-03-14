<?php
namespace Kma\Component\Survey\Administrator\Model;

use Exception;
use Joomla\Database\ParameterType;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Model\AdminModel;

defined('_JEXEC') or die();

class CampaignModel extends AdminModel {
    public function canCreate(?string $specificAction = 'com.create.survey'): bool
    {
        return parent::canCreate($specificAction);
    }

    public function canAddSurvey(object $campaignItem):bool
    {
        /*
         * The current user can add new survey to a campaign if the following
         * conditions are met:
         * -The current user has permission to edit the given campaign.
         * -The 'end_time' has not passed yet for the given campaign.
         */
        $currentTime = date("Y-m-d H:i:s");
        if($campaignItem->end_time <= $currentTime)
            return false; // Campaign đã hết hạn

        if(!$this->canEdit($campaignItem))
            return false;

        return true;
    }

    public function canMonitor(object $campaignItem):bool
    {
        return $this->canDo($campaignItem,'com.monitor.survey',true);
    }

    public function canAnalyse(object $campaignItem):bool
    {
        return $this->canDo($campaignItem,'com.analyse.survey',true);
    }

    public function delete(&$pks)
    {
        //1. Initialize variables
        if(is_array($pks))          //Multiple campaign selected
            $campaignId = $pks[0];  //Take the first one
        elseif(is_object($pks))     //Single campaign item passed
            $campaignId = $pks->id; //Get its id
        else
            $campaignId = $pks;
        if(!is_numeric($campaignId))
            throw new Exception('CampaignModel: Invalid campaign ID');

        //2. Get IDs of all the surveys associated with the campaign
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__survey_surveys')
            ->where('campaign_id='.(int)$campaignId);
        $db->setQuery($query);
        $surveyIds = $db->loadColumn();

        //3. Delete all the surveys
        if(count($surveyIds)>0){
            /**
             * @var SurveyModel $surveyModel
             */
            $surveyModel = ComponentHelper::createModel('Survey');
            foreach ($surveyIds as $surveyId)
                $surveyModel->delete($surveyId);
        }

        //4. Delete the campaign itself
        return parent::delete($pks);
    }

    public function surveyExists(int $campaignId, string $surveyTitle): bool
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__survey_surveys'))
            ->where($db->quoteName('campaign_id') . ' = :campaignId')
            ->where($db->quoteName('title') . ' = :surveyTitle')
            ->bind(':campaignId', $campaignId, ParameterType::INTEGER)
            ->bind(':surveyTitle', $surveyTitle, ParameterType::STRING);
        $db->setQuery($query);
        return (bool) $db->loadResult();
    }

    /**
     * For each respondent in the list of respondent ids, returns a pair of values:
     * -count_surveys: The number of surveys that have been sent to this respondent.
     * -count_respones: The number of surveys that have been completed by this respondent.
     * @param int $campaignId
     * @param array $respondentIds
     * @return array
     * @since 1.0.0
     */
    public function getRespondentsInfo(int $campaignId, array $respondentIds): array
    {
        // Nếu mảng rỗng, trả về mảng rỗng
        if (empty($respondentIds)) {
            return [];
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('sr.respondent_id'),
                'COUNT(' . $db->quoteName('sr.id') . ') AS ' . $db->quoteName('count_surveys'),
                'SUM(CASE WHEN ' . $db->quoteName('sr.responded') . ' = 1 THEN 1 ELSE 0 END) AS ' . $db->quoteName('count_responses')
            ])
            ->from($db->quoteName('#__survey_survey_respondent', 'sr'))
            ->innerJoin('#__survey_surveys AS s', 's.id = sr.survey_id')
            ->where($db->quoteName('s.campaign_id') . ' = :campaign_id')
            ->whereIn($db->quoteName('sr.respondent_id'), $respondentIds)
            ->group($db->quoteName('sr.respondent_id'))
            ->bind(':campaign_id', $campaignId, \Joomla\Database\ParameterType::INTEGER);

        $db->setQuery($query);
        $results = $db->loadObjectList('respondent_id');

        // Đảm bảo tất cả respondent_id đều có trong kết quả, kể cả những người chưa có survey nào
        $output = [];
        foreach ($respondentIds as $respondentId) {
            if (isset($results[$respondentId])) {
                $output[$respondentId] = [
                    'count_surveys' => (int) $results[$respondentId]->count_surveys,
                    'count_responses' => (int) $results[$respondentId]->count_responses
                ];
            } else {
                // Respondent không có trong bất kỳ survey nào của campaign
                $output[$respondentId] = [
                    'count_surveys' => 0,
                    'count_responses' => 0
                ];
            }
        }

        return $output;
    }
}
