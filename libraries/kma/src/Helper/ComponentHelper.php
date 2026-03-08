<?php
namespace Kma\Library\Kma\Helper;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Document\Document;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\Component\ComponentHelper as JoomlaComponentHelper;
use Joomla\Registry\Registry;

abstract class ComponentHelper
{
	/**
	 * Return the ComponentInterface instance of the component calling this helper.
	 * @return ComponentInterface
	 * @throws Exception
	 * @since 2.0.4
	 */
	public static function getComponent(): ComponentInterface
	{
		$app = Factory::getApplication();
		$componentName = self::getName();
		return $app->bootComponent($componentName);
	}

    /**
     * Return the full name of the component calling this helper. Example: com_kma
     * @return string
     * @throws Exception
     * @since 1.0.0
     */
    public static function getName():string
    {
	    $input  = Factory::getApplication()->getInput();
	    return strtolower($input->get('option', ''));
    }

    /**
     * Return the name of the component calling this helper. The returned value
     * does NOT contain prefix 'com_'. Example: 'kma' is returned for option 'com_kma'.
     * @return string
     * @throws Exception
     * @since 1.0.0
     */
    public static function getNameWithoutPrefix():string
    {
        $pattern = '/^com_(.*)$/';
        $option = self::getName();
        if(preg_match($pattern, $option, $matches))
            return $matches[1];
        return '';
    }

	public static function getParams(?string $componentName=null): Registry
	{
		if(is_null($componentName))
			$componentName = self::getName();
		return JoomlaComponentHelper::getParams($componentName);
	}

    /**
     * Lấy danh sách tât cả các actions và thực hiện kiểm tra quyền
     * của người dùng hiện thời đối với từng action.
     *
     * @param $assetName     string|null Unique name of asset in the table #__assets
     * @param $sectionName   string Name of the section where 'actions' are defined
     * @return array
     * @throws Exception
     * @since 1.0
     */
    public static function getCurrentUserPermissions(?string $assetName=null, string $sectionName='component'): array
    {
        $componentName = self::getName();
        if(is_null($assetName))
            $assetName= $componentName;

        $permissions = array();
        $path = JPATH_ADMINISTRATOR.'/components/' . $componentName . '/access.xml';
        $xpath = "/access/section[@name='$sectionName']/";
        $actions = Access::getActionsFromFile($path,$xpath);
        $user = Factory::getApplication()->getIdentity();
        foreach ($actions as $action){
            $permissions[$action->name] = $user->authorise($action->name,$assetName);
        }
        return $permissions;
    }

    public static function canDo(array|string|null $requiredActions, $permissions=null):bool
    {
        static $_permissions;
        if(empty($permissions))
        {
            if(empty($_permissions))
                $_permissions = self::getCurrentUserPermissions();
        }
        else
            $_permissions = $permissions;

        if(empty($requiredActions))
            return true;
        if(is_string($requiredActions))
            return $_permissions[$requiredActions];

        foreach ($requiredActions as $action)
            if($_permissions[$action])
                return true;
        return false;
    }

    public static function getDocument() : Document
    {
        return Factory::getApplication()->getDocument();
    }

    public static function getMVCFactory(): MVCFactory
    {
        $app = Factory::getApplication();
        $componentName = self::getName();
        return $app->bootComponent($componentName)->getMVCFactory();
    }
}
