<?php
namespace Kma\Library\Kma\View;
defined('_JEXEC') or die();

class ToolbarOption
{
    public bool $showToolbar = false;
    public string $title;
    public string $taskPrefixItem;
    public string $taskPrefixItems;

    //'Home' button
    public bool $taskGoHome = false;

    //List task
    public bool $taskAddNew = false;
    public bool $taskEditList = false;
    public bool $taskDeleteList = false;
    public bool $taskPublish = false;
    public bool $taskUnpublish = false;
    public bool $taskUpload = false;

    /**
     * Đưa các bản ghi được chọn vào trạng thái 'Đã lưu trữ'.
     * Chỉ có ý nghĩa với thực thể dùng đủ 4 trạng thái.
     *
     * @var bool
     * @since 1.0.5
     */
    public bool $taskArchive = false;

    /**
     * Đưa các bản ghi được chọn vào thùng rác.
     * Chỉ có ý nghĩa với thực thể dùng đủ 4 trạng thái.
     *
     * @var bool
     * @since 1.0.5
     */
    public bool $taskTrash = false;

    //Item edit, upload task
    public bool $taskApply = false;
    public bool $taskSave = false;
    public bool $taskSave2New = false;
    public bool $taskImport = false;
    public bool $taskCancel = false;

    //Other tasks
    public array $customTasks;

    //Component Options
    public bool $taskPreferences = false;

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
        $this->taskArchive = false;
        $this->taskTrash = false;
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
        $this->taskArchive = true;
        $this->taskTrash = true;
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

    /**
     * Tắt toàn bộ các task liên quan tới trạng thái.
     *
     * Dùng cho các màn hình danh sách không cho phép đổi trạng thái từ toolbar,
     * ví dụ view 'Examseasons' của com_eqa.
     *
     * @return  void
     * @since   1.0.5
     */
    public function clearStateTasks(): void
    {
        $this->taskPublish   = false;
        $this->taskUnpublish = false;
        $this->taskArchive   = false;
        $this->taskTrash     = false;
    }
}
