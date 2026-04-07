<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Kma\Library\Kma\Helper\DatabaseHelper;

return new class () implements InstallerScriptInterface {

    private string $minimumJoomla = '5.0.0';
    private string $minimumPhp    = '8.1.0';

    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if (version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
            Factory::getApplication()->enqueueMessage(
                sprintf(Text::_('JLIB_INSTALLER_MINIMUM_PHP'), $this->minimumPhp),
                'error'
            );
            return false;
        }

        if (version_compare(JVERSION, $this->minimumJoomla, '<')) {
            Factory::getApplication()->enqueueMessage(
                sprintf(Text::_('JLIB_INSTALLER_MINIMUM_JOOMLA'), $this->minimumJoomla),
                'error'
            );
            return false;
        }

        if ($type === 'update') {
            $paths = [
                JPATH_ADMINISTRATOR . '/components/com_survey',
                JPATH_SITE . '/components/com_survey',
                JPATH_ADMINISTRATOR . '/language/en-GB/com_survey.ini',
                JPATH_ADMINISTRATOR . '/language/en-GB/com_survey.sys.ini',
                JPATH_SITE . '/language/en-GB/com_survey.ini',
            ];

            foreach ($paths as $path) {
                if (is_dir($path)) {
                    Folder::delete($path);
                } elseif (is_file($path)) {
                    File::delete($path);
                }
            }
        }

        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
	{
		return true;
	}

    public function postflight_bak(string $type, InstallerAdapter $adapter): bool
    {
        $db = DatabaseHelper::getDatabaseDriver();

        //1. Load all the respondent-related records of all campaigns
        $columns = [
            'b.respondent_id',
            'a.campaign_id',
            'b.responded'
        ];
        $query = $db->getQuery(true)
            ->select($columns)
            ->from('#__survey_surveys AS a')
            ->innerJoin('#__survey_survey_respondent AS b', 'a.id=b.survey_id')
            ->where('a.campaign_id IS NOT NULL');
        $db->setQuery($query);
        $items = $db->loadObjectList();
        if(empty($items))
            return true;

        /*
         * For a pair (campaign_id, respondent_id), we need to count:
         * - How many times this pair has been found in the database (in the array $items)
         * - How many times it was marked as responded
         * We will have a multidimensional associative array with keys campaign_id and respondent_id,
         * whose values are arrays containing two elements: the number of occurrences and
         * the number of times it was marked as responded.
         *      $count[$campaign_id][$respondent_id] = ['surveys', 'responded']
         *
         *
         */
        $counts = [];
        foreach ($items as $item) {
            $campaign_id  = $item->campaign_id;
            $respondent_id= $item->respondent_id;
            if(!isset($counts[$campaign_id])) {
                $counts[$campaign_id]=[];
            }
            if(!isset($counts[$campaign_id][$respondent_id])) {
                $counts[$campaign_id][$respondent_id] = [
                    'surveys'=>0,
                    'responses'=>0
                ];
            }

            $counts[$campaign_id][$respondent_id]['surveys']++;
            if($item->responded == 1){
                $counts[$campaign_id][$respondent_id]['responses']++;
            }
        }

        /*
         * Write the results into the table #__survey_campaign_respondent
         * which has four columns:
         * - campaign_id (foreign key referencing #__survey_campaigns.id)
         * - respondent_id (foreign key referencing #__survey_respondents.id)
         * - survey_count (integer counting how many surveys the respondent should participate in for that campaign)
         * - response_count (integer counting how many surveys the respondent has already completed for that campaign)
         */
        $columns = [
            'campaign_id',
            'respondent_id',
            'survey_count',
            'response_count'
        ];
        foreach ($counts as $campaign_id => $respondent_counts) {
            foreach ($respondent_counts as $respondent_id => $count) {
                $query = $db->getQuery(true)
                    ->insert('#__survey_campaign_respondent')
                    ->columns($columns)
                    ->values(implode(',', [
                        $campaign_id,
                        $respondent_id,
                        $count['surveys'],
                        $count['responses']
                    ]));
                $db->setQuery($query);
                if(!$db->execute())
                    return false;
            }
        }

        Factory::getApplication()->enqueueMessage('The table #__survey_campaign_respondent has been created');
        return true;
    }
};