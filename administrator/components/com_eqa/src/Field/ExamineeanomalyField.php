<?php
namespace Kma\Component\Eqa\Administrator\Field;

defined('_JEXEC') or die();

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Kma\Component\Eqa\Administrator\Enum\Anomaly;

/**
 * Field rendering a <select> for examinee anomaly codes.
 *
 * @since 1.0
 */
class ExamineeanomalyField extends ListField
{
	/**
	 * Field type.
	 *
	 * @var string
	 */
	protected $type = 'examineeanomaly';

	/**
	 * Cached list of <option> objects (independent of $name / $selectedValue,
	 * so it is safe to cache statically across calls).
	 *
	 * @var array|null
	 */
	static protected ?array $options = null;

	/**
	 * Initialize the static $options array from the Anomaly enum.
	 *
	 * @return void
	 * @since 1.0
	 */
	static protected function initOptions(): void
	{
		$options = [];
		foreach (Anomaly::getOptions() as $code => $text)
		{
			$options[] = HTMLHelper::_('select.option', $code, $text);
		}
		self::$options = $options;
	}

	/**
	 * Build options for the standard Joomla form rendering path
	 * (when the field is used inside an XML form definition).
	 *
	 * @return array
	 * @since 1.0
	 */
	protected function getOptions()
	{
		if (self::$options === null)
		{
			self::initOptions();
		}

		return array_merge(parent::getOptions(), self::$options);
	}

	/**
	 * Render a standalone <select> element for the anomaly field.
	 *
	 * NOTE: The rendered HTML depends on $name and $selectedValue, so it MUST
	 * be rebuilt on every call. Do NOT cache the resulting HTML across calls,
	 * otherwise multiple selects on the same page will share the same `name`
	 * attribute and POST data will collide.
	 *
	 * @param   string    $name           The HTML name attribute (e.g. "jform[123][anomaly]").
	 * @param   int|null  $selectedValue  The currently selected anomaly code.
	 * @param   string    $prompt         Text for the empty/prompt option.
	 * @param   string    $class          CSS class for the <select>.
	 *
	 * @return  string  The rendered <select>...</select> HTML.
	 *
	 * @since 1.0
	 */
	static public function getElementHtml(
		string $name,
		?int $selectedValue = null,
		string $prompt = '',
		string $class = 'select2-basic'
	): string
	{
		// Init options cache once per request
		if (self::$options === null)
		{
			self::initOptions();
		}

		// Escape attribute values
		$nameAttr   = htmlspecialchars($name,   ENT_QUOTES, 'UTF-8');
		$classAttr  = htmlspecialchars($class,  ENT_QUOTES, 'UTF-8');
		$promptText = htmlspecialchars($prompt, ENT_QUOTES, 'UTF-8');

		// Opening tag
		$html  = '<select name="' . $nameAttr . '" class="' . $classAttr . '">';

		// Prompt option (empty value)
		$html .= '<option value="">' . $promptText . '</option>';

		// Real options
		foreach (self::$options as $option)
		{
			// Compare by int to avoid string/int mismatch (e.g. "0" === 0 is false)
			$isSelected = ($selectedValue !== null)
				&& ((int) $option->value === (int) $selectedValue);

			$valueAttr = htmlspecialchars((string) $option->value, ENT_QUOTES, 'UTF-8');
			$textHtml  = htmlspecialchars((string) $option->text,  ENT_QUOTES, 'UTF-8');

			$html .= '<option value="' . $valueAttr . '"'
				. ($isSelected ? ' selected="selected"' : '')
				. '>' . $textHtml . '</option>';
		}

		// Closing tag
		$html .= '</select>';

		return $html;
	}
}