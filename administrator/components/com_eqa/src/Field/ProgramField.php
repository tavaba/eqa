<?php
namespace Kma\Component\Eqa\Administrator\Field;
defined('_JEXEC') or die();

use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use Kma\Library\Kma\Field\StateAwareListField;

/**
 * Danh sách chọn chương trình đào tạo.
 *
 * @since  1.6
 */
class ProgramField extends StateAwareListField
{
    protected $type = 'program';

    protected string $stateColumn = 'a.state';
    protected string $keyColumn   = 'a.id';

    protected function buildQuery(DatabaseInterface $db): QueryInterface
    {
        return $db->getQuery(true)
            ->select('a.id, a.degree, a.name')
            ->from($db->quoteName('#__eqa_programs', 'a'))
            ->order($db->quoteName('a.degree') . ' ASC');
    }

    protected function buildOptionText(object $row): string
    {
        return (string) $row->name;
    }
}
