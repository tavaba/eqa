<?php
namespace Kma\Component\Survey\Administrator\Controller;
defined('_JEXEC') or die();

use Kma\Library\Kma\Controller\FormController;


class RespondentController extends  FormController {
    protected function allowAdd($data = [], $specificPermission = 'com.create.respondent'): bool
    {
        return parent::allowAdd($data, $specificPermission);
    }
}