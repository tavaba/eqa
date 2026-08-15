<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use Kma\Library\Kma\Field\StateAwareListField;

/**
 * Danh sách chọn ngành học.
 *
 * @since  1.6
 */
class SpecialityField extends StateAwareListField
{
    protected $type = 'speciality';

    protected string $stateColumn = 'a.state';
    protected string $keyColumn   = 'a.id';

    protected function buildQuery(DatabaseInterface $db): QueryInterface
    {
        return $db->getQuery(true)
            ->select('a.id, a.code, a.name')
            ->from($db->quoteName('#__eqa_specialities', 'a'))
            ->order($db->quoteName('a.code') . ' ASC');
    }

    protected function buildOptionText(object $row): string
    {
        return $row->code . ' - ' . $row->name;
    }
}
