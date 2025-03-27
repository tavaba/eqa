<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Matrix\Exception;

abstract class EmployeeHelper{
    protected static array $employees;
    protected static array $employeeMap;

    /**
     * Load employees into $employees
     * @return void
     * @since 1.0.2
     */
    static protected function loadEmployees(): void
    {
        $db = DatabaseHelper::getDatabaseDriver();
        $query = $db->getQuery(true)
            ->select('id')
            ->select("CONCAT(lastname,' ',firstname) AS fullname")
            ->from('#__eqa_employees');
        $db->setQuery($query);
        self::$employees = $db->loadAssocList('id','fullname');
    }

    /**
     * Flip the $employeesById to get the $employeesByFullname.
     * All the employees with duplicated fullname are removed
     *
     * @return void
     * @since 1.0.2
     */
    static protected function loadLectureMap():void{
        if(empty(self::$employees))
            self::loadEmployees();

        self::$employeeMap = [];
        $duplicates = [];

        // Populate the $lecturerMap and detect duplicates
        foreach (self::$employees as $id => $fullname) {
            if (isset(self::$employeeMap[$fullname]))
                $duplicates[] = $fullname;
            else
                self::$employeeMap[$fullname] = $id;
        }

        // Now remove any new entries that were duplicates
        foreach ($duplicates as $fullname) {
            unset(self::$employeeMap[$fullname]);
        }
    }

    /**
     * Returns one's full name depending on his id
     * @param int|null $id
     * @return string|null
     * @since 1.0
     */
    static public function getFullName(int|null $id): ?string{
        if($id==null)
            return null;

        //Load danh sách nếu chưa có
        if(empty(self::$employees)){
            self::loadEmployees();
        }

        //Trả kết quả
        return self::$employees[$id];
    }

    /**
     * Return one's id depending on his full name
     * @param string|null $fullname
     * @return int|null
     * @since 1.0.2
     */
    static public function getId(string|null $fullname):int|null
    {
        if(empty($fullname))
            return null;

        //Load danh sách nếu chưa có
        if(empty(self::$employeeMap))
            self::loadLectureMap();

        //Trả kết quả
        if(array_key_exists($fullname, self::$employeeMap))
            return self::$employeeMap[$fullname];
        return null;
    }
}

