<?php
namespace Kma\Library\Kma\Model;

defined('_JEXEC') or die();

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\QueryInterface;

/**
 * Model danh sách Mail Template.
 *
 * @since 2.0.9
 */
abstract class MailTemplatesModel extends ListModel
{
	//----------------------------------------------------
	// Abstract methods
	//----------------------------------------------------
	/**
	 * Trả về mảng options [value => label] cho filter context_type.
	 * Dùng trong View để render dropdown filter.
	 *
	 * @return array<int, string>
	 * @since  2.0.9
	 */
	abstract protected function getContextTypeOptions(): array;

	public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] = [
            't.id',
            't.title',
            't.context_type',
            't.published',
            't.created_at',
            't.modified_at',
        ];

        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 't.context_type', $direction = 'ASC'): void
    {
        parent::populateState($ordering, $direction);
    }

    public function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('t.id'),
                $db->quoteName('t.title'),
                $db->quoteName('t.context_type'),
                $db->quoteName('t.subject'),
                $db->quoteName('t.published'),
                $db->quoteName('t.created_by'),
                $db->quoteName('t.created_at'),
                $db->quoteName('t.modified_at'),
                $db->quoteName('u.name', 'creator_name'),
            ])
            ->from($db->quoteName('#__eqa_mail_templates', 't'))
            ->leftJoin(
                $db->quoteName('#__users', 'u') .
                ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('t.created_by')
            );

        // Filter: context_type
        $contextType = $this->getState('filter.context_type');
        if (is_numeric($contextType) && (int) $contextType > 0) {
            $query->where($db->quoteName('t.context_type') . ' = ' . (int) $contextType);
        }

        // Filter: published
        $published = $this->getState('filter.published');
        if (is_numeric($published) && $published !== '') {
            $query->where($db->quoteName('t.published') . ' = ' . (int) $published);
        }

        // Filter: search (title hoặc subject)
        $search = trim((string) $this->getState('filter.search'));
        if ($search !== '') {
            $like = $db->quote('%' . $db->escape($search) . '%');
            $query->where(
                '(' .
                $db->quoteName('t.title')   . ' LIKE ' . $like .
                ' OR ' .
                $db->quoteName('t.subject') . ' LIKE ' . $like .
                ')'
            );
        }

        // Ordering
        $orderCol = $db->escape($this->getState('list.ordering', 't.context_type'));
        $orderDir = $db->escape($this->getState('list.direction', 'ASC'));
        $query->order($db->quoteName($orderCol) . ' ' . $orderDir);

        return $query;
    }

    public function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.context_type');
        $id .= ':' . $this->getState('filter.published');
        $id .= ':' . $this->getState('filter.search');

        return parent::getStoreId($id);
    }
}
