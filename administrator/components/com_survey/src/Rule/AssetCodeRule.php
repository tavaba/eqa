<?php
namespace Kma\Component\Survey\Administrator\Rule;
defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;

class AssetCodeRule extends FormRule
{
	protected $regex = '^[A-Z0-9\-_\.]+$';
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        if(empty($value))
        {
            if(!isset($element->required) || ($element->required !== 'true'))
                return true;
            return false;
        }
        return parent::test($element, $value, $group, $input, $form);
    }
}