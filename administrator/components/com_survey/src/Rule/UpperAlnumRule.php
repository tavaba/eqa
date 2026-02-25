<?php
namespace Kma\Component\Survey\Administrator\Rule;
defined('_JEXEC') or die;

use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;
use Joomla\CMS\Form\Form;
use Kma\Component\Eqa\Administrator\Helper\DatabaseHelper;
use SimpleXMLElement;
class UpperAlnumRule extends FormRule
{
	protected $regex = '^[A-Z0-9]+$';
}