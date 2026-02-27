<?php
namespace Kma\Component\Survey\Administrator\Rule;
defined('_JEXEC') or die;

use Joomla\CMS\Form\FormRule;

class UpperAlnumRule extends FormRule
{
	protected $regex = '^[A-Z0-9]+$';
}