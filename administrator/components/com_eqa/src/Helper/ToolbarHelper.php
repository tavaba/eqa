<?php

namespace Kma\Component\Eqa\Administrator\Helper;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarFactoryInterface;
use JRoute;

defined('_JEXEC') or die();

abstract class ToolbarHelper extends \Joomla\CMS\Toolbar\ToolbarHelper
{
    public static function getToolbarFactory(): ToolbarFactoryInterface
    {
        return Factory::getContainer()->get(ToolbarFactoryInterface::class);
    }

    public static function getToolbarInstance(): Toolbar
    {
        return Toolbar::getInstance();
    }

	public static function render():void
	{
		echo Toolbar::getInstance()->render();
	}

    public static function appendConfirmButton($requiredActions, string $msg, string $icon, string $text, string $task, bool $listCheck, $btnClass=null):void
    {
        if(!GeneralHelper::checkPermissions($requiredActions))
            return;

        $toolbar = self::getToolbarInstance();
        $button = $toolbar->confirmButton($icon, $text, $task);
        $button->message($msg);
        $button->listCheck($listCheck);
        if(!empty($btnClass))
            $button->setOption('button_class', $btnClass);
    }
    public static function appenddButton($requiredActions, string $icon, string $text, string $task, bool $listCheck=false, $btnClass=null, bool $formValidate=false):void
    {
        if(!GeneralHelper::checkPermissions($requiredActions))
            return;

        $toolbar = self::getToolbarInstance();
        $button = $toolbar->standardButton($icon,$text,$task);
        $button->listCheck($listCheck);
        if(!empty($btnClass))
            $button->setOption('button_class', $btnClass);
        $button->formValidation($formValidate);
    }
    public static function appendGoHome($requiredActions='core.manage'):void
    {
        if(!GeneralHelper::checkPermissions($requiredActions))
            return;

        $toolbar = self::getToolbarInstance();
        $url = JRoute::_('index.php?option=com_eqa');
        $button = $toolbar->link('COM_EQA_BUTTON_GO_HOME', $url);
        $button->name('home');
    }
    public static function appendLink($requiredActions, $url, $text, $icon='link', $class='btn btn-primary'):void
    {
        if(!GeneralHelper::checkPermissions($requiredActions))
            return;

        $toolbar = self::getToolbarInstance();
        $button = $toolbar->link($text, $url);
        $button->name($icon);
        $button->buttonClass($class);
    }
    public static function appendGoBack($task, $text='COM_EQA_BUTTON_BACK', $icon='arrow-up-2', $requiredActions='core.manage'):void
    {
        if(!GeneralHelper::checkPermissions($requiredActions))
            return;
        $toolbar = self::getToolbarInstance();
        $toolbar->standardButton($icon,$text,$task);
    }
    public static function appendDelete($task, $text='JTOOLBAR_DELETE', $msg='COM_EQA_MSG_CONFIRM_DELETE', $requiredActions='core.delete'):void
    {
        if(!GeneralHelper::checkPermissions($requiredActions))
            return;
        $toolbar = self::getToolbarInstance();
        $button = $toolbar->delete($task,$text);
        $button->listCheck(true);
        $button->message($msg);
    }
    public static function appendUpload($task, $text='COM_EQA_BUTTON_UPLOAD', $icon='upload', $requiredActions='core.create'):void
    {
        if(!GeneralHelper::checkPermissions($requiredActions))
            return;
        $toolbar = self::getToolbarInstance();
        $toolbar->standardButton($icon, $text, $task);
    }
	public static function appendCancelLink($url)
	{
		self::appendLink(null,$url,'JTOOLBAR_CANCEL','delete','btn btn-danger');
	}
}