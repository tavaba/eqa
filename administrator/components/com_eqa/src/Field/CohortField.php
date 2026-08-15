<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use Kma\Library\Kma\Field\StateAwareListField;

/**
 * Danh sách chọn nhóm người học (cohort).
 *
 * @since  1.0
 */
class CohortField extends StateAwareListField
{
    protected $type = 'cohort';

    protected string $stateColumn = 'a.state';
    protected string $keyColumn   = 'a.id';

    protected function buildQuery(DatabaseInterface $db): QueryInterface
    {
        return $db->getQuery(true)
            ->select('a.id, a.code, a.name')
            ->from($db->quoteName('#__eqa_cohorts', 'a'))
            ->order($db->quoteName('a.id') . ' DESC');
    }

    protected function buildOptionText(object $row): string
    {
        return $row->code . ' - ' . htmlspecialchars((string) $row->name);
    }
}
