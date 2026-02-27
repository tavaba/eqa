<?php
namespace Kma\Component\Survey\Administrator\Rule;
defined('_JEXEC') or die;

use Joomla\CMS\Form\FormRule;
class AssetNameRule extends FormRule
{
    /**
     * The regular expression to use in testing a form field value.
     *
     * \p{L} = any kind of letter from any language
     * \p{N} = any kind of numeric character
     * Space, dot, underscore, dash and parentheses are allowed
     *
     * @var    string
     * @since 1.0.0
     */
    protected $regex = '^[\p{L}\p{N} .,_\-\(\)]+$';
    protected $modifiers = 'u';
}