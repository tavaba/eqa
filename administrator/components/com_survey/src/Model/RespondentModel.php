<?php
namespace Kma\Component\Survey\Administrator\Model;

use Exception;
use Kma\Component\Survey\Administrator\Helper\RespondentHelper;
use Kma\Component\Survey\Administrator\Helper\SurveyHelper;
use Kma\Library\Kma\Helper\DatabaseHelper;
use Kma\Library\Kma\Model\AdminModel;

defined('_JEXEC') or die();

class RespondentModel extends AdminModel {
    public function canCreate(?string $specificAction = 'com.create.respondent'): bool
    {
        return parent::canCreate($specificAction);
    }
    public function validate($form, $data, $group = null): bool|array
    {
        //Some specific logics for the 'Respondent' model.
        $mustBePerson = in_array($data['type'], [RespondentHelper::RESPONDENT_TYPE_LEARNER,
            RespondentHelper::RESPONDENT_TYPE_EMPLOYEE,
            RespondentHelper::RESPONDENT_TYPE_VISITING_LECTURER,
            RespondentHelper::RESPONDENT_TYPE_EXPERT]
        );
        $isPerson = $data['is_person'];
        if($mustBePerson && !$isPerson)
        {
            $this->setError('Với phân loại đã chọn thì phải là một cá nhân');
            return false;
        }

        if($isPerson)
        {
            if(empty($data['lastname']) || empty($data['firstname']))
            {
                $this->setError('Là cá nhân thì phải có Họ đệm, Tên');
                return false;
            }

            //Clear all the organization fields.
            $data['name'] = '';
        }

        if(!$isPerson)
        {
            if(empty($data['name']))
            {
                $this->setError('Là tổ chức thì phải có Tên');
                return false;
            }

            //Clear all the person fields.
            $data['lastname'] = '';
            $data['firstname'] = '';
            $data['gender'] = '';
        }

        //Validate the form data using the parent method.
        return parent::validate($form, $data, $group);
    }
}
