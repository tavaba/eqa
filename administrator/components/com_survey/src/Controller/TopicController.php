<?php
namespace Kma\Component\Survey\Administrator\Controller;
defined('_JEXEC') or die();

use Kma\Library\Kma\Controller\FormController;


class TopicController extends  FormController {
    protected function allowAdd($data = [], $specificPermission = 'com.create.topic'): bool
    {
        return parent::allowAdd($data, $specificPermission);
    }
}