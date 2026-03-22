<?php
namespace Kma\Library\Kma\Model;
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel as BaseListModel;
use Joomla\CMS\User\User;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Helper\EnglishHelper;
use Kma\Library\Kma\Service\EnglishService;
use Kma\Library\Kma\Service\LogService;

/**
 * Class này sẽ được thừa kế bởi các Items Model
 *
 * @since 1.0
 */
abstract class ListModel extends BaseListModel
{
	/**
	 * An instance of LogService that is retrived from DIC by default
	 * ListModel không thực hiện CRUD nên không ghi log trực tiếp.
	 * Tuy nhiên cần inject LogService để subclass có thể ghi log nếu cần.
	 */
	protected ?LogService $logService=null;
	protected ?EnglishService $englishService=null;

	/** Bật/Tắt chế độ ghi log. Tự động bật trong constructor nếu tồn tại $logService */
	protected bool $loggingEnabled=false;

	/**
     * @var User Current logged-in user. It is initialized by the constructor.
     * @since 1.0.0
     */
    protected User $user;

    /**
     * @var string The entity type (the singular form of the model name).
     *              It is initialized by the constructor.
     * @since 1.0.0
     */
    protected string $entityType;

    /**
     * @var string|null Name of action defined in the ACL system which allows users to perform delete operation on their own records.
     *                      For example, if you define 'com.delete.own' here,
     *                      then the user who created an item will be able to delete his/her own items.
     *                      Note that this right may not be defined in the ACL system. Therefore, wrap it inside a try/catch block.
     * @since 1.0.0
     */
    protected ?string $actionDeleteOwn=null;

    /**
     * @var string|null Name of field containing the ID of the creator of the item.
     *                      This field is used when checking ownership of an item.
     *                      For example, if you define 'created_by' here,
     *                      then the user who created an item will be able to perform all operations on his/her own items.
     * @since 1.0.0
     */
    protected ?string $ownerIdField=null;

    /*
     * Some properties for caching purposes
     */
    private bool $__canCreate;
    private bool $__canEditAny;
    private bool $__canEditStateAny;
    private bool $__canDeleteAny;
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        parent::__construct($config, $factory);

	    //Resolve the LogService instance
	    $this->logService = ComponentHelper::getLogService();
	    if($this->logService)
		    $this->loggingEnabled = true;
		$this->englishService = ComponentHelper::getEnglishService();

        /*
         * Initialize some properties
         */
        $this->ownerIdField=$config['owner_id_field']??'created_by';
        $this->actionDeleteOwn=$config['action_delete_own']??'com.delete.own';

        /**
         * Determine the current user. Note that the BaseModel class already has the
         * getCurrentUser() method, but Joomla docs mentions that this method may return
         * an empty User object.
         */
        $this->user = Factory::getApplication()->getIdentity();

        //Get the entity type from the model name
        $this->entityType = $this->englishService
	        ? $this->englishService->pluralToSingular($this->getName())
	        : EnglishHelper::pluralToSingular($this->getName());
    }

	/**
	 * Thiêt lập LogService thay cho instance được khởi tạo mặc định trong constructor
	 *
	 * @param   LogService  $logService
	 * @since 1.0.3
	 */
	public function setLogService(LogService $logService)
	{
		$this->logService = $logService;
	}

	/**
	 * Subclass khai báo object_type của mình — chỉ một lần.
	 */
	abstract protected function getLogObjectType(): int;

	public function enableLogging(): static  { $this->loggingEnabled = true;  return $this; }
	public function disableLogging(): static { $this->loggingEnabled = false; return $this; }


	/**
     * Method to autopopulate the model state.
     * @since 1.0.0
     */
    protected function populateState($ordering = null, $direction = null): void
    {
        $ordering = $this->getUserStateFromRequest($this->context . '.ordercol', 'filter_order', $ordering);
        $direction = $this->getUserStateFromRequest($this->context . '.orderdirn', 'filter_order_Dir', $direction);
        parent::populateState($ordering, $direction);
    }

    /**
     * Method to get a store id based on model configuration state.
     * @since 1.0.0
     */
    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id .= ':' . $this->getState('list.ordering');
        $id .= ':' . $this->getState('list.direction');

        return parent::getStoreId($id);
    }

    /**
     * 'ImportForm' có nghĩa là form để import dữ liệu.
     * Cần lưu ý quy tắc đặt tên cho import form và cả tên file tương ứng
     * để đảm bảo phương thức này hoạt động thông suốt.
     * @param string $name
     * @param string $source    Name of the XML form file without extension
     *
     * @return false|Form
     * @throws Exception
     * @since 1.0
     */
    public function getImportForm(string $name='', string $source=''): bool|Form
    {
        $modelName = $this->getName();
        if(empty($name))
            $name = $this->option . '.import_'.$modelName;
        if(empty($source))
            $source = 'import_'.$modelName;         //Name of the XML form file without extension
        $form = $this->loadForm($name,$source);
        if(empty( $form))
            return false;
        else return $form;
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
            && (int)$record->{$this->ownerIdField} == $user->id;
    }
    protected function getAssetName(int $itemId): string
    {
        return "$this->option.$this->name.$itemId";
    }

    /**
     * Check if the current user can create a new item.
     * @param string|null $specificAction The specific action defined in the ACL system
     *              which allows users to create new items of specific entity type.
     *              For example, 'com.create.survey'.
     * @return bool
     * @since 1.0.0
     */
    public function canCreate(?string $specificAction=null):bool
    {
        $user = $this->user;
        if(isset($this->__canCreate))
            return $this->__canCreate;

        if($user->authorise('core.create', $this->option))
        {
            $this->__canCreate = true;
            return true;
        }

        $this->__canCreate = $specificAction && $user->authorise($specificAction, $this->option);
        return $this->__canCreate;
    }

    public function canEditAny(array $items): bool
    {
        if(isset($this->__canEditAny))
            return $this->__canEditAny;
        $user = $this->user;

        //1. If there are no items at all, we cannot edit them
        if (!count($items))
        {
            $this->__canEditAny=false;
            return false;
        }

        //2. If the user has permission to 'core.edit.own', we'll check if
        //the user owns any of these items.
        $hasPermissionToEditOwn = $user->authorise('core.edit.own', $this->option);
        if ($hasPermissionToEditOwn)
        {
            foreach ($items as $item) {
                if ($this->checkOwnership($user, $item))
                {
                    $this->__canEditAny=true;
                    return true;
                }
            }
        }

        //3.1. If there's no 'asset_id' field, we'll check for component-level permissions
        if(!property_exists($items[0], 'asset_id'))
        {
            $this->__canEditAny=$user->authorise('core.edit', $this->option);
            return $this->__canEditAny;
        }

        //3.2. Otherwise, we need to check if the user has permission to edit
        // each individual item.
        foreach ($items as $item)
        {
            $assetName = $this->getAssetName($item->id);
            if($user->authorise('core.edit', $assetName))
            {
                $this->__canEditAny=true;
                return true;
            }
        }

        //The user cannot edit any of these items
        $this->__canEditAny=false;
        return false;
    }
     public function canEditStateAny(array $items): bool
    {
        if(isset($this->__canEditStateAny))
            return $this->__canEditStateAny;
        $user = $this->user;

        //1. If there are no items at all, we cannot edit them
        if (!count($items))
        {
            $this->__canEditStateAny=false;
            return false;
        }

        //2. If the user has permission to 'core.edit.own', we'll check if
        //the user owns any of these items.
        $hasPermissionToEditOwn = $user->authorise('core.edit.own', $this->option);
        if ($hasPermissionToEditOwn)
        {
            foreach ($items as $item) {
                if ($this->checkOwnership($user, $item))
                {
                    $this->__canEditStateAny=true;
                    return true;
                }
            }
        }

        //3.1 If there's no 'asset_id' field, we'll check for component-level permissions
        if(!property_exists($items[0], 'asset_id'))
        {
            $this->__canEditStateAny = $user->authorise('core.edit', $this->option)
                || $user->authorise('core.edit.state', $this->option);
            return $this->__canEditStateAny;
        }

        //3.2. Otherwise, we need to check if the user has permission to edit state
        // each individual item.
        foreach ($items as $item)
        {
            $assetName = $this->getAssetName($item->id);
            if($user->authorise('core.edit', $assetName)
                || $user->authorise('core.edit.state', $assetName))
            {
                $this->__canEditStateAny = true;
                return true;
            }
        }

        //The user cannot edit any of these items
        $this->__canEditStateAny = false;
        return false;
    }

    public function canDeleteAny(array $items): bool
    {
        if(isset($this->__canDeleteAny))
            return $this->__canDeleteAny;
        $user = $this->user;

        //1. If there are no items at all, we cannot delete them
        if (!count($items))
        {
            $this->__canDeleteAny=false;
            return false;
        }

        //2. If the user has permission to delete his/her own records, we'll check if
        //the user owns any of these items.
        $hasPermissionToDeleteOwn = $this->actionDeleteOwn
            && $user->authorise($this->actionDeleteOwn, $this->option);
        if ($hasPermissionToDeleteOwn)
        {
            foreach ($items as $item) {
                if ($this->checkOwnership($user, $item))
                {
                    $this->__canDeleteAny=true;
                    return true;
                }
            }
        }

        //3.1. If there's no 'asset_id' field, we'll check for component-level permissions
        if(!property_exists($items[0], 'asset_id'))
        {
            $this->__canDeleteAny = $user->authorise('core.delete', $this->option);
            return $this->__canDeleteAny;
        }

        //3.2. Otherwise, we need to check if the user has permission to delete
        // each individual item.
        foreach ($items as $item)
        {
            $assetName = $this->getAssetName($item->id);
            if($user->authorise('core.delete', $assetName))
            {
                $this->__canDeleteAny=true;
                return true;
            }
        }

        //5. The user cannot delete any of these items
        $this->__canDeleteAny=false;
        return false;
    }
}
