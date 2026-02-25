<?php

namespace Kma\Library\Kma\Helper;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;

abstract class DatabaseHelper
{
    /**
     * Casting kết quả của get('DatabaseDriver') thành DatabaseInterface
     * @return DatabaseDriver
     * @since 1.0.0
     */
    public static function getDatabaseDriver(): DatabaseDriver
    {
        return Factory::getContainer()->get(DatabaseDriver::class);
    }
}