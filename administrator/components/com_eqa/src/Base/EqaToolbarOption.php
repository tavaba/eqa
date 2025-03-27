<?php
namespace Kma\Component\Eqa\Administrator\Base;
defined('_JEXEC') or die();

class EqaToolbarOption
{
    public array $canDo;
    public bool $showToolbar;
    public string $title;
    public string $taskPrefixItem;
    public string $taskPrefixItems;

    //'Home' button
    public bool $taskGoHome;

    //List task
    public bool $taskAddNew;
    public bool $taskEditList;
    public bool $taskDeleteList;
    public bool $taskPublish;
    public bool $taskUnpublish;
    public bool $taskResetOrder;
    public bool $taskUpload;

    //Item edit, upload task
    public bool $taskApply;
    public bool $taskSave;
    public bool $taskSave2New;
    public bool $taskImport;
    public bool $taskCancel;

    //Other tasks
    public array $customTasks;

    //Component Options
    public bool $taskPreferences;

    public function __construct()
    {
        $this->showToolbar = true;
        $this->customTasks = [];
    }
    public function clearAllTask():void{
        //Home
        $this->taskGoHome = false;

        //List tasks
        $this->taskAddNew = false;
        $this->taskEditList = false;
        $this->taskDeleteList = false;
        $this->taskPublish = false;
        $this->taskUnpublish = false;
        $this->taskResetOrder = false;
        $this->taskUpload = false;

        //Item edit, upload tasks
        $this->taskApply = false;
        $this->taskSave = false;
        $this->taskSave2New = false;
        $this->taskImport = false;
        $this->taskCancel = false;

        //Component Options
        $this->taskPreferences = false;
    }
    public function setDefaultListTasks():void{
        $this->clearAllTask();
        $this->taskGoHome = true;
        $this->taskAddNew = true;
        $this->taskDeleteList = true;
        $this->taskPublish = true;
        $this->taskUnpublish = true;
    }
    public function setItemEditTasks():void{
        $this->clearAllTask();
        $this->taskApply = true;
        $this->taskSave = true;
        $this->taskSave2New = true;
        $this->taskCancel = true;
    }
    public function setUploadTasks():void{
        $this->clearAllTask();
        $this->taskImport = true;
        $this->taskCancel = true;
    }
}