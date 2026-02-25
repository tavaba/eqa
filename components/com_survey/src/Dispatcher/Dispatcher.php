<?php
/**
 * Survey Component Dispatcher
 *
 * Place this file at: components/com_survey/src/Dispatcher/Dispatcher.php
 *
 */

namespace Kma\Component\Survey\Site\Dispatcher;

use Exception;
use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Factory;
use Kma\Library\Kma\Helper\DatabaseHelper;
use stdClass;

class Dispatcher extends ComponentDispatcher
{
    /**
     * The respondent mapping data
     *
     * @var object|null
     * @since 1.0.0
     */
    protected $respondent = null;

    /**
     * Dispatch a controller task
     *
     * @return  void
     * @since 1.0.0
     */
    public function dispatch(): void
    {
        // Map user to respondent before any controller logic
        $this->mapUserToRespondent();

        // Store respondent data in application for access throughout the component
        $this->app->set('respondent', $this->respondent);

        // Continue with normal dispatch
        parent::dispatch();
    }

    /**
     * Map current user to a respondent record
     *
     * @return void
     * @since 1.0.0
     */
    protected function mapUserToRespondent(): void
    {
        try {
            $app = Factory::getApplication();
        }
        catch (Exception) {
            return; // No app? Bail out.
        }

        //Try to check respondent token first. If a token is provided, the user
        // will be granted access occording to that token regardless of whether
        // he/she is logged in or not
        $token = $app->input->getAlnum('token','');
        if(!empty($token)) {
            $this->respondent = $this->getRespondentByToken($token);
            return;
        }

        //If no token was provided, check if the user is logged in already
        $user = $app->getIdentity();
        if($user->guest)
        {
            $this->respondent=null;
            return;
        }

        //Then try to match by email or username if the user has logged in
        if(!empty($user->email))
        {
            $this->respondent = $this->getRespondentByEmailUsername($user->email);
            if(empty($this->respondent))
                $this->respondent = $this->getRespondentByEmail($user->email);

            //Also mark the respondent as authenticated
            if(empty($this->respondent))
                $this->respondent = new stdClass();
            $this->respondent->authenticated = true;
        }
    }
    protected function getRespondentByToken($token)
    {
        $db = DatabaseHelper::getDatabaseDriver();
        $columns = [
            $db->quoteName('respondent_id')       . ' AS ' . $db->quoteName('id'),
            $db->quoteName('value')               . ' AS ' . $db->quoteName('token'),
            $db->quoteName('survey_id')           . ' AS ' . $db->quoteName('surveyId'),
        ];
        $query = $db->getQuery(true)
            ->select($columns)
            ->from('#__survey_tokens')
            ->where($db->quoteName('value') . '=' . $db->quote($token));
        $db->setQuery($query);
        return $db->loadObject();
    }
    protected function getRespondentByEmailUsername($email)
    {
        //If no email address is found, bail out
        if(empty($email))
            return null;

        //Check if it's an ACTVN email
        $email = strtoupper($email);
        $emailPattern = '/^([0-9A-Z]+)@ACTVN.EDU.VN$/';
        $matched = preg_match($emailPattern, $email, $matches);
        if(!$matched)
            return null;

        //Get respondent id from database
        //The username part is expected to be the same as the 'code' field of respondents table
        $respondentCode = $matches[1];
        $db = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__survey_respondents')
            ->where($db->quoteName('code') . '=' . $db->quote($respondentCode));
        $db->setQuery($query);
        return $db->loadObject();
    }
    protected function getRespondentByEmail($email)
    {
        //If no email address is found, bail out
        if(empty($email))
            return null;


        //Get respondent id from database
        //The email is expected to be the same as the 'email' field of respondents table
        $email = strtolower($email);
        $db = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__survey_respondents')
            ->where($db->quoteName('email') . '=' . $db->quote($email));
        $db->setQuery($query);
        return $db->loadObject();
    }
}
