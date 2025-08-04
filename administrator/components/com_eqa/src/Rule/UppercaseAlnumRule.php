<?php
namespace Kma\Component\Eqa\Administrator\Rule;
defined('_JEXEC') or die;

use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;
use Joomla\CMS\Form\Form;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use SimpleXMLElement;
class UppercaseAlnumRule extends FormRule
{
	protected $regex = '^[A-Z0-9]+$';
}