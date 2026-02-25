<?php
namespace Kma\Component\Survey\Administrator\Rule;
defined('_JEXEC') or die;

use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;
use Joomla\CMS\Form\Form;
class JsonRule extends FormRule
{
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        //Check if $value is a valid JSON string.
        if (json_decode($value) === null && !empty($value)) {
            return false;
        } else {
            return true;
        }
    }
}