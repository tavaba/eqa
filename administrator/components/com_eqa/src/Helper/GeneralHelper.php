<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Exception;
use JFactory;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Document\Document;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Toolbar\ContainerAwareToolbarFactory;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarFactoryInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use stdClass;

abstract class GeneralHelper{
    /**
     * Thống nhất cách xác định 'id' của người dùng để lưu vào CSDL
     * @return string|null
     * @throws Exception
     * @since 1.0
     */
	static public function getCurrentUsername(): string|null
	{
		$leanerEmailPattern = "/^((AT|CT|DT|H|CH|CHAT)[0-9]{4,6}N?)@actvn\.edu\.vn$/";
		$user = Factory::getApplication()->getIdentity();

		//Nếu không có user nào logged in
		if($user->guest)
			return null;

		//Nếu là HVSV thì ưu tiên lấy username theo email
		if($user->email && preg_match($leanerEmailPattern, $user->email, $matches)){
			$username = $matches[1];
			return $username;
		}

		if($user->username)
			return $user->username;

		//Trích username từ email
		return strstr($user->email, "@", true); // Get part before "@"
	}
	static public function getSignedInLearnerCode(): string|null
	{
		$leanerEmailPattern = "/^((AT|CT|DT|H|CH|CHAT)[0-9]{4,6}N?)@ACTVN\.EDU\.VN$/";
		$user = Factory::getApplication()->getIdentity();

		//Nếu là HVSV thì ưu tiên lấy username theo email
		if(isset($user->email))
		{
			//Capitalize the user email
			$email = strtoupper($user->email);
			if (preg_match($leanerEmailPattern, $email, $matches))
				return $matches[1];
		}

		return null;
	}
	static public function getSignedInLearnerId(): int|null
	{
		$learnerCode = self::getSignedInLearnerCode();
		if(!$learnerCode)
			return null;

		$db = DatabaseHelper::getDatabaseDriver();
		$db->setQuery( 'SELECT id FROM #__eqa_learners WHERE code=' . $db->quote($learnerCode));
		return $db->loadResult();
	}

    /**
     * Lấy danh sách tât cả các actions và thực hiện kiểm tra quyền
     * của người dùng hiện thời đối với từng action.
     *
     * @param $assetName
     * @param $sectionName
     * @return array
     * @throws Exception
     * @since 1.0
     */
    static public function getActions($assetName='com_eqa', $sectionName='component'){
        $result= array();
        $path = JPATH_ADMINISTRATOR.'/components/com_eqa/access.xml';
        $xpath = "/access/section[@name='$sectionName']/";
        $actions = Access::getActionsFromFile($path,$xpath);
        $user = JFactory::getApplication()->getIdentity();
        foreach ($actions as $action){
            $result[$action->name] = $user->authorise($action->name,$assetName);
        }
        return $result;
    }

    static public function checkPermissions(array|string|null $requiredActions, $canDo=null):bool
    {
        static $_canDo;
        if(empty($canDo))
        {
            if(empty($_canDo))
                $_canDo = self::getActions();
        }
        else
            $_canDo = $canDo;

        if(empty($requiredActions))
            return true;
        if(is_string($requiredActions))
            return $_canDo[$requiredActions];

        foreach ($requiredActions as $action)
            if($_canDo[$action])
                return true;
        return false;
    }

    /**
     * Fake casting to Object
     * @param $o
     * @return stdClass
     * @since 1.0
     */
    public static function castToCmsObject($o):stdClass{
        return $o;
    }

    public static function getDocument() : Document
    {
        return Factory::getApplication()->getDocument();
    }

    public static function getMVCFactory(): MVCFactory
    {
        return new MVCFactory('Kma\Component\Eqa');
    }

	public static function toFloat($value, $precison=null): float|bool
	{
		if(is_numeric($value))  //Nếu là số thì coi như xong
		{
			if(is_null($precison))
				return $value;
			else
				return round($value, $precison);
		}

		if(empty($value))   //Nếu không phải số mà rỗng thì hỏng
			return false;

		$value = str_replace(',', '.', $value);    //Trường hợp sử dụng dấu phẩy thay vì dấu chấm
		if(is_numeric($value))                                  //và kiểm tra lại lần nữa
		{
			if(is_null($precison))
				return $value;
			else
				return round($value, $precison);
		}

		return false;
	}

	public static function isInteger($value): bool
	{
		if(!is_numeric($value))
			return  false;
		$intValue = (int)$value;
		if($intValue != $value)
			return false;
		return true;
	}
	static public function isTimeOver(string $timestamp):bool
	{
		$hanoiTimezone = 'Asia/Ho_Chi_Minh';
		$inputTime = new Date($timestamp, $hanoiTimezone);
		$currentTime = new Date('now');
		return $inputTime < $currentTime;
	}
}
