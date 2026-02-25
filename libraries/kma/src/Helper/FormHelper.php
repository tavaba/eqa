<?php
namespace Kma\Library\Kma\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use SimpleXMLElement;

abstract class FormHelper{
    public static function getFormFactory(): FormFactoryInterface
    {
        return Factory::getContainer()->get(FormFactoryInterface::class);
    }
	public static function getBackendForm(string $formName, string $fileName, array $options=['control'=>'jform', 'component_name'=>null]){
		$componentName = $options['component_name'] ?? ComponentHelper::getName();
		$fullFileName = JPATH_ADMINISTRATOR . '/components/' . $componentName . '/forms/' . $fileName;
		if(!file_exists($fullFileName))
			return null;
		$factory = self::getFormFactory();
		$form = $factory->createForm($formName, $options);
		$form->loadFile($fullFileName);
		return $form;
	}
	public static function getFrontendForm(string $formName, string $fileName, array $options=['control'=>'jform', 'component_name'=>null]){
		$componentName = $options['component_name'] ?? ComponentHelper::getName();
		$fullFileName = JPATH_SITE . '/components/' . $componentName . '/forms/' . $fileName;
		if(!file_exists($fullFileName))
			return null;
		$factory = self::getFormFactory();
		$form = $factory->createForm($formName, $options);
		$form->loadFile($fullFileName);
		return $form;
	}
    /**
     * Load form from XML file
     *
     * @param   string  $name      Form name
     * @param   string  $xmlFile   XML file path
     * @param   array   $options   Form options
     *
     * @return  Form|false  Form object or false on failure
     *
     * @since   1.0.0
     */
    public static function loadForm(string $name, string $xmlFile, array $options = [])
    {
        if (!file_exists($xmlFile)) {
            return false;
        }

        $form = new Form($name, $options);
        
        if (!$form->loadFile($xmlFile)) {
            return false;
        }

        return $form;
    }

    /**
     * Validate form data
     *
     * @param   Form   $form  Form object
     * @param   array  $data  Data to validate
     *
     * @return  boolean  True if valid
     *
     * @since   1.0.0
     */
    public static function validateForm(Form $form, array $data): bool
    {
        return $form->validate($data);
    }

    /**
     * Get form field value
     *
     * @param   Form    $form       Form object
     * @param   string  $fieldName  Field name
     * @param   mixed   $default    Default value
     *
     * @return  mixed  Field value
     *
     * @since   1.0.0
     */
    public static function getFieldValue(Form $form, string $fieldName, $default = null)
    {
        $field = $form->getField($fieldName);
        
        if (!$field) {
            return $default;
        }

        return $field->value ?? $default;
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

