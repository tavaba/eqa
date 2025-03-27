<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormFactoryInterface;

abstract class FormHelper{
    static public function getFormFactory(): FormFactoryInterface
    {
        return Factory::getContainer()->get(FormFactoryInterface::class);
    }
	static public function getBackendForm(string $formName, string $fileName, array $options=['control'=>'jform']){
		$fullFileName = JPATH_ADMINISTRATOR . '/components/com_eqa/forms/' . $fileName;
		if(!file_exists($fullFileName))
			return null;
		$factory = self::getFormFactory();
		$form = $factory->createForm($formName, $options);
		$form->loadFile($fullFileName);
		return $form;
	}
	static public function getFrontendForm(string $formName, string $fileName, array $options=['control'=>'jform']){
		$fullFileName = JPATH_SITE . '/components/com_eqa/forms/' . $fileName;
		if(!file_exists($fullFileName))
			return null;
		$factory = self::getFormFactory();
		$form = $factory->createForm($formName, $options);
		$form->loadFile($fullFileName);
		return $form;
	}
}

