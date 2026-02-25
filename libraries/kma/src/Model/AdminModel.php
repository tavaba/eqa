<?php
namespace Kma\Library\Kma\Model;
defined('_JEXEC') or die();

use Exception;
use InvalidArgumentException;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\AdminModel as BaseAdminModel;
use Joomla\CMS\User\User;
use Kma\Library\Kma\Table\Table;
use stdClass;

/**
 * Class này sẽ được thừa kế bởi các Item Model
 *
 * @since 1.0
 */
class AdminModel extends BaseAdminModel
{
    /**
     * @var string|null Name of action defined in the ACL system which allows users to perform
     *                      access controll operation on their own records.
     *                      For example, if you define 'com.admin.own' here,
     *                      then the user who created an item will be able to delete his/her own items.
     * @since 1.0.0
     */
    protected ?string $actionAdminOwn=null;

    /**
     * @var string|null Name of action defined in the ACL system which allows users to perform
     *                      delete operation on their own records.
     *                      For example, if you define 'com.delete.own' here,
     *                      then the user who created an item will be able to delete his/her own items.
     * @since 1.0.0
     */
    protected ?string $actionDeleteOwn=null;

    /**
     * @var string|null Name of field containing the ID of the creator of the item.
     *                      This field (e.g. 'created_by') is used when checking ownership of an item.
     * @since 1.0.0
     */
    protected ?string $ownerIdField=null;
    public function __construct($config = [], ?MVCFactoryInterface $factory = null, ?FormFactoryInterface $formFactory = null)
    {
        parent::__construct($config, $factory, $formFactory);

        /*
         * Initialize some properties
         */
        $this->ownerIdField=$config['owner_id_field']??'created_by';
        $this->actionDeleteOwn=$config['action_delete_own']??'com.delete.own';

        /*
         * Determine the current user. Note that the BaseModel class already has the
         * getCurrentUser() method, but Joomla docs mentions that this method may return
         * an empty User object.
         */
        $this->user = Factory::getApplication()->getIdentity();

        //Init the model name
        $this->getName();
    }

    /**
     * 'loadFormData' là hàm đã có trong lớp AdminModel nhưng nó rỗng
     * Vì thế, việc định nghĩa lại ở đây sẽ giúp tránh phải định nghĩa lại ở
     * các item model được sử dụng trong component này.
     * @since 1.0
     */
    public function loadFormData()
    {
        $app = Factory::getApplication();

        $context = "$this->option.edit.$this->name";            //$this->name = item name (or entity type)

        // Check the session for previously entered form data
        $data = $app->getUserState("$context.data", array());

        if (empty($data)) {
            // No session data, load from database
            $data = $this->getItem();

            // Prime required properties that are expected to exist
            if (empty($data)) {
                $data = new stdClass();
                $data->id = 0;
            }
        }

        return $data;
    }

    /**
     * Overide phương thức 'getForm' của  lớp cha để tự động xác định tên form
     * Cụ thể, tên form được xác định thông qua tên model. Do vậy, cần lưu ý quy tắc
     * đặt tên form và form file để phương thức này không bị lỗi.
     *
     * @param $data
     * @param $loadData
     * @return false|Form
     * @throws Exception
     * @since 1.0
     */
    public function getForm($data = [], $loadData = true)
    {
        $formName = $this->option . '.' . $this->name;
        $formSource = $this->name;           //Name (without extension) of the XML form file
        $form = $this->loadForm($formName,$formSource,array('control'=>'jform','load_data'=>$loadData));
        if(empty( $form))
            return false;
        return $form;
    }

    /**
     * Set or Unset the 'default' status of an item.
     *
     * @param int $id
     * @param string $fieldName
     * @return bool
     * @throws Exception
     * @since  1.0
     */
    public function setDefault(int $id, string $fieldName='default'): bool
    {
        //Authorization: the user must have 'core.edit' permission on the component
        if(!$this->getCurrentUser()->authorise('core.edit', $this->option)){
            $textKeyPrefix = strtoupper($this->option).'_MSG_UNAUTHORISED';
            $msg = Text::_($textKeyPrefix);
            throw new Exception($msg);
        }

        $db = $this->getDatabase();
        $table = $this->getTable();
        $table->load($id);
        $tableName = $table->getTableName();
        $currentDefaultStatus = $table->{$fieldName};

        if(!$currentDefaultStatus){
            //Clear the 'default' flag of current default item
            $query = $db->getQuery(true);
            $query->update($db->quoteName($tableName))
                ->set($db->quoteName($fieldName) . '= 0')
                ->where($db->quoteName($fieldName).' > 0');
            $db->setQuery($query);
            if(!$db->execute())
                return false;

            //Set the current item to be 'default'
            $table->{$fieldName} = 1;
            if(!$table->store())
                return false;
        }

        return true;
    }

    public function getDefaultItem(string $fieldName='default')
    {
        $db = $this->getDatabase();
        $table = $this->getTable();
        $tableName = $table->getTableName();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName($tableName))
            ->where($db->quoteName($fieldName).'>0');
        $db->setQuery($query);
        return $db->loadObject();
    }

    /**
     * Check if user owns the record
     *
     * @param User $user User object
     * @param object $record The record object
     * @return  boolean  True if user owns the record, false otherwise
     *
     * @since   1.0.0
     */
    protected function checkOwnership(User $user, object $record): bool
    {
        return $this->ownerIdField
            && isset($record->{$this->ownerIdField})
            && (int)$record->{$this->ownerIdField} === (int)$user->id;
    }

    public function getAssetName(int $itemId): string
    {
        return "$this->option.$this->name.$itemId";
    }

    /**
     * Check whether the current user can perform a certain action on a given item.
     *
     * @param object $item
     * @param string $action
     * @param bool $ownerAlwaysCanDo
     * @return bool
     * @since 1.0.0
     */
    public function canDo(object $item, string $action, bool $ownerAlwaysCanDo): bool
    {
        $user = $this->user;

        //1. If owner always can do the thing and the user is the owner of the record,
        // return TRUE
        if ($ownerAlwaysCanDo && $this->checkOwnership($user, $item))
            return true;

        //2. If the $item has an asset id, we'll then check permission on the asset
        if (!empty($item->asset_id)) {
            $assetName = $this->getAssetName($item->id);
            return $user->authorise($action, $assetName);
        }

        //3. Otherwise, we'll check for component-wide permission
        return $user->authorise($action, $this->option);
    }

    /**
     * Check if the current user can create a new item.
     * @param string|null $specificAction The specific action defined in the ACL system
     * *              which allows users to create new items of specific entity type.
     * *              For example, 'com.create.survey'.
     * @return bool
     * @since 1.0.0
     */
    public function canCreate(?string $specificAction=null):bool
    {
        $user = $this->user;
        if($user->authorise('core.create', $this->option))
            return true;
        if($specificAction && $user->authorise($specificAction, $this->option))
            return true;
        return false;
    }

    /**
     * @param object|int|null $record The record object or its ID.
     * @return bool
     * @throws Exception
     * @since 1.0.0
     */
    public function canAdmin(object|int|null $record=null):bool
    {
        $user = $this->user;
        $itemId = is_object($record)? $record->id : $record;

        /**
         * 1. For new items (or no item specified at all), check component-level 'core.admin' only
         * 'com.admin.own' doesn't apply because ownership doesn't exist yet.
         * A user with 'com.admin.own' permission but no 'core.admin' permission
         * will be able to 'admin' his/her assets only after the assets are created.
         */
        if(empty($itemId))
            return $user->authorise('core.admin', $this->option);


        //2. Otherwise, we'll check for permission on the asset
        $assetName = $this->getAssetName($itemId);
        return $user->authorise('core.admin',$assetName);
    }

    /**
     * @param object|int|null $record The record object or its ID.
     * @return bool
     * @throws Exception
     * @since 1.0.0
     */
    public function canEdit(object|int|null $record=null):bool
    {
        $user = $this->user;
        $itemId = is_object($record)? $record->id : $record;

        //1. For new items (or no item specified at all), check component-level 'core.edit' only
        if(empty($itemId))
            return $user->authorise('core.edit', $this->option);


        //2. If $record is an integer, will try to fetch the corresponding item from DB
        if(is_int($record))
        {
            $table = $this->getTable();
            if(!$table->load($record))
                return false;
            $record=$table;
        }

        //3. If the current user is the owner of the record,
        // we must then check whether he/she also has 'core.edit.own' permission
        $isOwner = $this->checkOwnership($user, $record);
        if($isOwner && $user->authorise('core.edit.own', $this->option))
            return true;

        //4. If the record has an asset id, we'll then check permission on the asset
        if(!empty($record->asset_id))
        {
            $assetName = $this->getAssetName($record->id);
            return $user->authorise('core.edit',$assetName);
        }

        //5. Otherwise, we'll check for component-wide permission
        return $user->authorise('core.edit', $this->option);
    }

    /**
     * @param $record object|int|null The record object or its ID.
     * @return bool
     * @throws Exception
     * @since 1.0.0
     */
    public function canEditState($record=null):bool
    {
        $user = $this->user;
        $itemId = is_object($record)? $record->id : $record;

        //1. For new items (or no item specified at all), check component-level permission only
        if(empty($itemId))
        {
            return $user->authorise('core.edit', $this->option)
                || $user->authorise('core.edit.state', $this->option);
        }

        //2. If $record is an integer, will try to fetch the corresponding item from DB
        if(is_int($record))
        {
            $table = $this->getTable();
            if(!$table->load($record))
                return false;
            $record=$table;
        }

        //3. If the current user is the owner of the record,
        // we must then check whether he/she also has 'core.edit.own' permission
        $isOwner = $this->checkOwnership($user, $record);
        if($isOwner && $user->authorise('core.edit.own', $this->option))
            return true;

        //4. If the record has an asset id, we'll then check permission on the asset
        if(!empty($record->asset_id))
        {
            $assetName = $this->getAssetName($record->id);
            return $user->authorise('core.edit',$assetName)
                || $user->authorise('core.edit.state',$assetName);
        }

        //5. Otherwise, we'll check for component-wide permission
        return $user->authorise('core.edit', $this->option)
            || $user->authorise('core.edit.state', $this->option);
    }

    /**
     * @param $record object|int|null The record object or its ID.
     * @return bool
     * @throws Exception
     * @since 1.0.0
     */
    public function canDelete($record=null):bool
    {
        $user = $this->user;
        $itemId = is_object($record)? $record->id : $record;

        //1. If $record is not provided, we will return component-wide permission only
        if(is_null($itemId))
            return $user->authorise('core.delete', $this->option);

        //2. If the $item has not been created yet, we will return FALSE, since there's nothing to delete
        if(empty($itemId))
            return false;

        //3. If $record is an integer, we'll try to fetch the corresponding item from DB
        if(is_int($record))
        {
            $table = $this->getTable();
            if(!$table->load($record))
                return false;
            $record=$table;
        }

        //4. If the current user is the owner of the record and
        // there is a specific action defined in the ACL system for allowing deletion of own records,
        // we must then check whether he/she has that permission
        $isOwner = $this->checkOwnership($user, $record);
        if($isOwner && $this->actionDeleteOwn && $user->authorise($this->actionDeleteOwn, $this->option))
            return true;

        //5. If the record has an asset id, we'll then check permission on the asset
        if(!empty($record->asset_id))
        {
            $assetName = $this->getAssetName($record->id);
            return $user->authorise('core.delete',$assetName);
        }

        //6. Otherwise, we'll check for component-wide permission
        return $user->authorise('core.delete', $this->option);
    }


	//TODO: Remove the following methods
	/**
	 * Hàm này gán giá trị trường 'ordering' của mỗi phần tử trong bảng
	 * về giá trị của trường 'id' tương ứng.
	 * @return bool
	 *
	 * @since 1.0
	 */
	public function resetOrdering():bool{
		try {
			// Get the database object
			$dbo = Factory::getContainer()->get('DatabaseDriver');

			// Build the query
			$tableName = $this->getTable()->getTableName();
			$query = $dbo->getQuery(true)
				->update($dbo->quoteName($tableName))
				->set('ordering=id');

			// Set the query and execute
			$dbo->setQuery($query);

			$result = $dbo->execute();
		} catch (RuntimeException $e) {
			return false;
		}

		return $result;
	}


}
