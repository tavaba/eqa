<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use SimpleXMLElement;

abstract class FormHelper{
    static public function getFormFactory(): FormFactoryInterface
    {
        return Factory::getContainer()->get(FormFactoryInterface::class);
    }
	static public function getBackendForm(string $formName, string $fileName, array $options=['control'=>'jform'])
	{
		$fullFileName = JPATH_ADMINISTRATOR . '/components/com_eqa/forms/' . $fileName;
		if(!file_exists($fullFileName))
			return null;
		$factory = self::getFormFactory();
		$form = $factory->createForm($formName, $options);
		$form->loadFile($fullFileName);
		return $form;
	}
	static public function getFrontendForm(string $formName, string $fileName, array $options=['control'=>'jform'])
	{
		$fullFileName = JPATH_SITE . '/components/com_eqa/forms/' . $fileName;
		if(!file_exists($fullFileName))
			return null;
		$factory = self::getFormFactory();
		$form = $factory->createForm($formName, $options);
		$form->loadFile($fullFileName);
		return $form;
	}

	/**
	 * Adds a field to a specific fieldset in the form.
	 *
	 * @param   Form         $form        The Joomla form object.
	 * @param   string       $name        The name of the field.
	 * @param   string       $type        The field type (e.g. 'hidden', 'text').
	 * @param   mixed        $value       The default value for the field.
	 * @param   string|null  $group       The name of <fields> element to add the field into.
	 * @param   string|null  $fieldset    The name of <fieldset> element to add the field into.
	 * @param   array        $attributes  (Optional) Extra attributes (e.g. label, required, class).
	 *
	 * @return void
	 * @throws \Exception
	 * @since 1.2.0
	 */
	public static function addField(
		Form   $form,
		string $name,
		string $type,
		mixed  $value,
		?string $group,
		?string $fieldset,
		array  $attributes = []
	): void
	{
		$attrs = '';
		foreach ($attributes as $attrName => $attrValue) {
			$escapedAttr = htmlspecialchars($attrValue, ENT_QUOTES, 'UTF-8');
			$attrs .= " {$attrName}=\"{$escapedAttr}\"";
		}

		$escapedValue = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

		$fieldXml = <<<XML
				<field name="{$name}" type="{$type}" default="{$escapedValue}" {$attrs} />
		XML;
		$field = new SimpleXMLElement($fieldXml);

		$form->setField($field, $group, true, $fieldset);
	}
}

