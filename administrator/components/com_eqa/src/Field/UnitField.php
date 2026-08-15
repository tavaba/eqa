<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use Kma\Library\Kma\Field\StateAwareListField;

/**
 * Danh sách chọn đơn vị.
 *
 * @since  1.6
 */
class UnitField extends StateAwareListField
{
    protected $type = 'unit';

    protected string $stateColumn = 'a.state';
    protected string $keyColumn   = 'a.id';

    protected function buildQuery(DatabaseInterface $db): QueryInterface
    {
        return $db->getQuery(true)
            ->select('a.id, a.code, a.name')
            ->from($db->quoteName('#__eqa_units', 'a'))
            ->order($db->quoteName('a.code') . ' ASC');
    }

    protected function buildOptionText(object $row): string
    {
        return $row->code . ' - ' . $row->name;
    }
}
