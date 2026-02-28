<?php
/**
 * @package     Kma.Library
 * @subpackage  Rule
 *
 * @copyright   (C) 2025 KMA
 * @license     GNU General Public License version 2 or later
 */

namespace Kma\Library\Kma\Rule;

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormRule;

/**
 * Validates an exam code.
 *
 * Allowed characters: uppercase Latin letters (A–Z), digits (0–9),
 * dot (.), hyphen (-), and underscore (_).
 *
 * Usage in form XML:
 *   addruleprefix="Kma\Library\Kma\Rule"
 *   validate="ExamCode"
 *
 * @since 1.3.0
 */
class ExamCodeRule extends FormRule
{
    /**
     * The regular expression to use in testing a form field value.
     * The pattern is anchored by FormRule::test() automatically with ^ and $.
     *
     * @var    string
     * @since  1.3.0
     */
    protected $regex = '^[A-Z0-9\.\-_]+$';
}
