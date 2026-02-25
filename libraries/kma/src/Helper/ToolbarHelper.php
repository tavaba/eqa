<?php

namespace Kma\Library\Kma\Helper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarFactoryInterface;

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

    public static function appendConfirmButton(string $msg, string $icon, string $text, string $task, bool $listCheck, $btnClass=null):void
    {
        $toolbar = self::getToolbarInstance();
        $button = $toolbar->confirmButton($icon, $text, $task);
        $button->message($msg);
        $button->listCheck($listCheck);
        if(!empty($btnClass))
            $button->setOption('button_class', $btnClass);
    }
    public static function appendButton(string $icon, string $text, string $task, bool $listCheck=false, $btnClass=null, bool $formValidate=false):void
    {
        $toolbar = self::getToolbarInstance();
        $button = $toolbar->standardButton($icon,$text,$task);
        $button->listCheck($listCheck);
        if(!empty($btnClass))
            $button->setOption('button_class', $btnClass);
        $button->formValidation($formValidate);
    }
    public static function appendGoHome(string $textKey='Home'):void
    {
        $toolbar = self::getToolbarInstance();
        $url = Route::_('index.php?option=' . ComponentHelper::getName());
        $button = $toolbar->link($textKey, $url);
        $button->name('home');      //Set the button icon name
    }
    public static function appendLink($url, $textKey, $icon='link', $class='btn btn-primary'):void
    {
        $toolbar = self::getToolbarInstance();
        $button = $toolbar->link($textKey, $url);
        $button->name($icon);
        $button->buttonClass($class);
    }
    public static function appendGoBack($task, $text, $icon='arrow-up-2'):void
    {
        $toolbar = self::getToolbarInstance();
        $toolbar->standardButton($icon,$text,$task);
    }
    public static function appendDelete($task, $text='JTOOLBAR_DELETE', $msg='Are you sure you want to delete selected items?'):void
    {
        $toolbar = self::getToolbarInstance();
        $button = $toolbar->delete($task,$text);
        $button->listCheck(true);
        $button->message($msg);
    }
    public static function appendUpload($task, $text='Upload', $icon='upload', $formValidate=true):void
    {
        $toolbar = self::getToolbarInstance();
        $button = $toolbar->standardButton($icon, $text, $task);
	    $button->formValidation($formValidate);
    }
    public static function appendCancelLink(string $url, string $textKey='JTOOLBAR_CANCEL')
    {
        self::appendLink($url, $textKey,'delete','btn btn-danger');
    }
    public static function appendGobackLink($url, $textKey)
    {
        self::appendLink($url,$textKey,'arrow-up-2');
    }
}