<?php
namespace Kma\Component\Eqa\Administrator\Rule;
defined('_JEXEC') or die;

use Joomla\CMS\Form\FormRule;

class UppercaseAlnumRule extends FormRule
{
	protected $regex = '^[A-Z0-9]+$';
}