<?php

/**
 * @package     Kma.Library.Kma
 * @subpackage  Model
 *
 * @copyright   (C) 2025 KMA
 * @license     GNU General Public License version 2 or later
 *
 * @since       1.0.3
 */

namespace Kma\Library\Kma\Model;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\QueryInterface;
use Kma\Library\Kma\Enum\MailCampaignStatus;
use Kma\Library\Kma\Enum\MailQueueStatus;
use Kma\Library\Kma\Helper\ComponentHelper;
use Kma\Library\Kma\Service\MailService;

/**
 * Base model cho view quản lý chiến dịch email (Mail Campaigns).
 *
 * Chứa toàn bộ logic:
 *   - Query danh sách campaign với filter và ordering
 *   - Query chi tiết queue của một campaign (delivery log)
 *   - Tạo campaign mới: chọn template, resolve người nhận, build queue
 *   - Hủy campaign
 *
 * Schema các bảng DB (được inject qua MailService):
 *   #__xxx_mail_templates : id, title, context_type, subject, body, published
 *   #__xxx_mail_campaigns : id, template_id, context_type, context_id,
 *                           recipient_filter, status, total_count,
 *                           sent_count, failed_count, created_by, created_at
 *   #__xxx_mail_queue     : id, campaign_id, recipient_type, recipient_id,
 *                           recipient_email, subject, body, status,
 *                           attempts, last_attempt_at, sent_at,
 *                           error_message, created_at
 *
 * Lớp con BẮT BUỘC override:
 *   - getMailService(): MailService
 *       Trả về instance MailService (lấy từ DI Container hoặc tạo mới).
 *
 * Lớp con CÓ THỂ override:
 *   - getContextLabel(int $contextType, int $contextId): string
 *       Trả về nhãn hiển thị của ngữ cảnh, ví dụ: tên môn thi, tên kỳ thi.
 *       Mặc định trả về "#{$contextId}".
 *   - resolveRecipients(int $contextType, int $contextId, ?string $recipientFilter): array
 *       Resolve danh sách người nhận từ ngữ cảnh nghiệp vụ.
 *       Mặc định trả về mảng rỗng — lớp con phải override nếu muốn tạo queue.
 *   - buildPlaceholderResolver(int $contextType): callable
 *       Trả về callback fn(object $recipient): array<string,string>.
 *       Mặc định chỉ trả về common placeholders — lớp con override để thêm
 *       placeholder đặc thù của context (exam_name, room_name, ...).
 *
 * Ví dụ lớp con (com_eqa):
 * -----------------------------------------------------------------------
 *   class MailCampaignModel extends \Kma\Library\Kma\Model\MailCampaignModel
 *   {
 *       protected function getMailService(): MailService
 *       {
 *           return Factory::getContainer()->get(MailService::class);
 *       }
 *
 *       protected function getContextLabel(int $contextType, int $contextId): string
 *       {
 *           return match (MailContextType::from($contextType)) {
 *               MailContextType::Exam       => DatabaseHelper::getExamInfo($contextId)?->name ?? "#{$contextId}",
 *               MailContextType::ExamSeason => DatabaseHelper::getExamseasonInfo($contextId)?->name ?? "#{$contextId}",
 *               default                     => "#{$contextId}",
 *           };
 *       }
 *
 *       protected function resolveRecipients(int $contextType, int $contextId, ?string $recipientFilter): array
 *       {
 *           // ... query ra danh sách thí sinh theo ngữ cảnh
 *       }
 *
 *       protected function buildPlaceholderResolver(int $contextType): callable
 *       {
 *           return match (MailContextType::from($contextType)) {
 *               MailContextType::Exam => fn(object $r) => array_merge(
 *                   MailService::buildCommonPlaceholders($r->learner),
 *                   ['{exam_name}' => $r->exam_name ?? '', ...]
 *               ),
 *               default => fn(object $r) => MailService::buildCommonPlaceholders($r->learner),
 *           };
 *       }
 *   }
 * -----------------------------------------------------------------------
 *
 * @since 1.0.3
 */
abstract class MailCampaignsModel extends ListModel
{
    // =========================================================================
    // Overridable — lớp con nên override để hỗ trợ đầy đủ tính năng
    // =========================================================================

    /**
     * Trả về nhãn hiển thị của ngữ cảnh (context).
     *
     * Ví dụ: contextType=Exam, contextId=42 → "Toán cao cấp 1 (HK1/2024-2025)"
     *
     * Mặc định trả về "#{$contextId}" — lớp con override để hiển thị tên thật.
     *
     * @param  int  $contextType  Giá trị MailContextType enum
     * @param  int  $contextId    ID đối tượng ngữ cảnh
     *
     * @return string
     * @since  1.0.3
     */
    protected function getContextLabel(int $contextType, int $contextId): string
    {
        return '#' . $contextId;
    }

    /**
     * Resolve danh sách người nhận từ ngữ cảnh nghiệp vụ.
     *
     * Mỗi phần tử trả về PHẢI có $recipient->learner (object) với:
     *   ->id (int), ->code (string), ->lastname (string), ->firstname (string)
     * Các thuộc tính khác là context data cho placeholder resolver.
     *
     * Mặc định trả về [] — lớp con PHẢI override để tạo queue có dữ liệu.
     *
     * @param  int          $contextType      Giá trị MailContextType enum
     * @param  int          $contextId        ID đối tượng ngữ cảnh
     * @param  string|null  $recipientFilter  JSON filter bổ sung (có thể NULL)
     *
     * @return array
     * @since  1.0.3
     */
    protected function resolveRecipients(
        int     $contextType,
        int     $contextId,
        ?string $recipientFilter
    ): array {
        return [];
    }

    /**
     * Trả về callback resolver placeholder cho một context type.
     *
     * Callback signature: fn(object $recipient): array<string, string>
     *
     * Mặc định chỉ trả về common placeholders ({learner_name}, {learner_code}).
     * Lớp con override để thêm placeholder đặc thù theo context.
     *
     * @param  int  $contextType  Giá trị MailContextType enum
     *
     * @return callable
     * @since  1.0.3
     */
    protected function buildPlaceholderResolver(int $contextType): callable
    {
        return static fn(object $recipient): array
            => MailService::buildCommonPlaceholders($recipient->learner);
    }

    // =========================================================================
    // Constructor & state
    // =========================================================================

    /**
     * @param  array                    $config
     * @param  MVCFactoryInterface|null $factory
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] = [
            'mc.id',
            'mc.created_at',
            'mc.created_by',
            'mc.context_type',
            'mc.context_id',
            'mc.status',
            'mc.total_count',
            'mc.sent_count',
            'mc.failed_count',
        ];

        parent::__construct($config, $factory);
    }

    /**
     * Mặc định sắp xếp theo thời gian tạo mới nhất lên đầu.
     *
     * @since 1.0.3
     */
    protected function populateState($ordering = 'mc.created_at', $direction = 'DESC'): void
    {
        parent::populateState($ordering, $direction);
    }

    // =========================================================================
    // Query — danh sách campaign
    // =========================================================================

    /**
     * Xây dựng câu truy vấn danh sách campaign.
     *
     * JOIN với #__users để lấy tên người tạo.
     * context_label được tính toán ở tầng View (sau khi getItems() trả về)
     * thông qua method enrichItems() — không thể join động trong SQL.
     *
     * @return QueryInterface
     * @since  1.0.3
     */
    public function getListQuery(): QueryInterface
    {
        $db             = $this->getDatabase();
        $mailService    = $this->getMailService();
        $tableCampaigns = $mailService->getTableCampaigns();

        $query = $db->getQuery(true)
            ->from($db->quoteName($tableCampaigns, 'mc'))
            ->leftJoin(
                $db->quoteName('#__users', 'u') .
                ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('mc.created_by')
            )
            ->select([
                $db->quoteName('mc.id'),
                $db->quoteName('mc.template_id'),
                $db->quoteName('mc.context_type'),
                $db->quoteName('mc.context_id'),
                $db->quoteName('mc.recipient_filter'),
                $db->quoteName('mc.status'),
                $db->quoteName('mc.total_count'),
                $db->quoteName('mc.sent_count'),
                $db->quoteName('mc.failed_count'),
                $db->quoteName('mc.created_by'),
                $db->quoteName('mc.created_at'),
                $db->quoteName('u.name',     'creator_name'),
                $db->quoteName('u.username', 'creator_username'),
            ]);

        // ----- Filters -----

        // Lọc theo context_type
        $contextType = $this->getState('filter.context_type');
        if (is_numeric($contextType) && (int) $contextType > 0) {
            $query->where($db->quoteName('mc.context_type') . ' = ' . (int) $contextType);
        }

        // Lọc theo context_id (ví dụ: xem tất cả campaign của một môn thi)
        $contextId = $this->getState('filter.context_id');
        if (is_numeric($contextId) && (int) $contextId > 0) {
            $query->where($db->quoteName('mc.context_id') . ' = ' . (int) $contextId);
        }

        // Lọc theo status
        $status = $this->getState('filter.status');
        if (is_numeric($status) && $status !== '') {
            $query->where($db->quoteName('mc.status') . ' = ' . (int) $status);
        }

        // Lọc theo người tạo
        $createdBy = $this->getState('filter.created_by');
        if (is_numeric($createdBy) && (int) $createdBy > 0) {
            $query->where($db->quoteName('mc.created_by') . ' = ' . (int) $createdBy);
        }

        // Lọc theo khoảng thời gian tạo (người dùng nhập Local Time → convert UTC)
        $dateFrom = trim((string) $this->getState('filter.date_from'));
        if ($dateFrom !== '') {
            $utcFrom = \Kma\Library\Kma\Helper\DatetimeHelper::convertToUtc($dateFrom . ' 00:00:00');
            if ($utcFrom !== null) {
                $query->where($db->quoteName('mc.created_at') . ' >= ' . $db->quote($utcFrom));
            }
        }

        $dateTo = trim((string) $this->getState('filter.date_to'));
        if ($dateTo !== '') {
            $utcTo = \Kma\Library\Kma\Helper\DatetimeHelper::convertToUtc($dateTo . ' 23:59:59');
            if ($utcTo !== null) {
                $query->where($db->quoteName('mc.created_at') . ' <= ' . $db->quote($utcTo));
            }
        }

        // ----- Ordering -----
        $orderCol = $db->escape($this->getState('list.ordering', 'mc.created_at'));
        $orderDir = $db->escape($this->getState('list.direction', 'DESC'));
        $query->order($db->quoteName($orderCol) . ' ' . $orderDir);

        return $query;
    }

    /**
     * @inheritDoc
     * @since 1.0.3
     */
    public function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.context_type');
        $id .= ':' . $this->getState('filter.context_id');
        $id .= ':' . $this->getState('filter.status');
        $id .= ':' . $this->getState('filter.created_by');
        $id .= ':' . $this->getState('filter.date_from');
        $id .= ':' . $this->getState('filter.date_to');

        return parent::getStoreId($id);
    }

	/**
	 * Trả về instance MailService đã được cấu hình cho component.
	 * Thường lấy từ Joomla DI Container.
	 *
	 * @return MailService|null
	 * @since  1.0.3
	 */
	protected function getMailService(): MailService|null
	{
		return ComponentHelper::getMailService();
	}

	/**
     * Bổ sung dữ liệu hiển thị vào từng item sau khi getItems() trả về.
     *
     * Thêm vào mỗi item:
     *   - status_label    : nhãn tiếng Việt của status
     *   - status_badge    : CSS class Bootstrap badge
     *   - context_label   : nhãn ngữ cảnh (gọi getContextLabel() của lớp con)
     *   - progress_pct    : phần trăm tiến độ gửi (0-100)
     *
     * Gọi trong View trước khi render, ví dụ:
     *   $items = $model->getItems();
     *   $model->enrichItems($items);
     *
     * @param  object[]  $items  Mảng item từ getItems()
     *
     * @return void
     * @since  1.0.3
     */
    public function enrichItems(array &$items): void
    {
        foreach ($items as $item) {
            // Status label + badge
            $statusEnum         = MailCampaignStatus::tryFrom((int) $item->status);
            $item->status_label = $statusEnum?->getLabel() ?? '?';
            $item->status_badge = $statusEnum?->getBadgeClass() ?? 'bg-secondary';

            // Context label — gọi lớp con để lấy tên thật
            $item->context_label = $this->getContextLabel(
                (int) $item->context_type,
                (int) $item->context_id
            );

            // Tiến độ gửi
            $total              = (int) $item->total_count;
            $done               = (int) $item->sent_count + (int) $item->failed_count;
            $item->progress_pct = $total > 0 ? (int) round($done / $total * 100) : 0;
        }
    }

    // =========================================================================
    // Query — delivery log (chi tiết queue của một campaign)
    // =========================================================================

    /**
     * Lấy danh sách email trong queue của một campaign (delivery log).
     *
     * Dùng cho layout 'log' trong view mailcampaigns.
     * Bổ sung thêm status_label và status_badge cho từng item.
     *
     * @param  int  $campaignId
     * @param  int  $limit       Số bản ghi tối đa (0 = không giới hạn)
     *
     * @return object[]
     * @since  1.0.3
     */
    public function getQueueItems(int $campaignId, int $limit = 0): array
    {
        $db         = $this->getDatabase();
        $mailService = $this->getMailService();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('q.id'),
                $db->quoteName('q.recipient_type'),
                $db->quoteName('q.recipient_id'),
                $db->quoteName('q.recipient_email'),
                $db->quoteName('q.subject'),
                $db->quoteName('q.status'),
                $db->quoteName('q.attempts'),
                $db->quoteName('q.last_attempt_at'),
                $db->quoteName('q.sent_at'),
                $db->quoteName('q.error_message'),
                $db->quoteName('q.created_at'),
            ])
            ->from($db->quoteName($mailService->getTableQueue(), 'q'))
            ->where($db->quoteName('q.campaign_id') . ' = ' . (int) $campaignId)
            ->order($db->quoteName('q.id') . ' ASC');

        if ($limit > 0) {
            $query->setLimit($limit);
        }

        $db->setQuery($query);
        $items = $db->loadObjectList() ?: [];

        // Bổ sung status label + badge
        foreach ($items as $item) {
            $statusEnum         = MailQueueStatus::tryFrom((int) $item->status);
            $item->status_label = $statusEnum?->getLabel() ?? '?';
            $item->status_badge = $statusEnum?->getBadgeClass() ?? 'bg-secondary';
        }

        return $items;
    }

    /**
     * Lấy thông tin một campaign theo ID.
     *
     * @param  int  $campaignId
     *
     * @return object|null
     * @since  1.0.3
     */
    public function getCampaignById(int $campaignId): ?object
    {
        $db          = $this->getDatabase();
        $mailService = $this->getMailService();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('mc.*'),
                $db->quoteName('u.name',     'creator_name'),
                $db->quoteName('u.username', 'creator_username'),
                $db->quoteName('t.title',    'template_title'),
                $db->quoteName('t.subject',  'template_subject'),
            ])
            ->from($db->quoteName($mailService->getTableCampaigns(), 'mc'))
            ->leftJoin(
                $db->quoteName('#__users', 'u') .
                ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('mc.created_by')
            )
            ->leftJoin(
                $db->quoteName($mailService->getTableTemplates(), 't') .
                ' ON ' . $db->quoteName('t.id') . ' = ' . $db->quoteName('mc.template_id')
            )
            ->where($db->quoteName('mc.id') . ' = ' . $campaignId);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    // =========================================================================
    // Tạo campaign mới
    // =========================================================================

    /**
     * Tạo một campaign mới và xây dựng hàng đợi email.
     *
     * Luồng xử lý:
     *   1. Lấy template theo templateId
     *   2. Insert bản ghi campaign vào DB (status = Pending, total_count = 0)
     *   3. Resolve danh sách người nhận qua resolveRecipients() (lớp con)
     *   4. Lấy placeholder resolver qua buildPlaceholderResolver() (lớp con)
     *   5. Gọi MailService::buildQueue() để render + insert queue
     *      (buildQueue() tự cập nhật total_count và status cho campaign)
     *
     * @param  int          $templateId       ID template đã chọn
     * @param  int          $contextType      Giá trị MailContextType enum
     * @param  int          $contextId        ID đối tượng ngữ cảnh
     * @param  string|null  $recipientFilter  JSON filter bổ sung (NULL = toàn bộ)
     *
     * @return int  ID của campaign vừa tạo
     * @throws Exception  Nếu template không tồn tại hoặc không có người nhận
     * @since  1.0.3
     */
    public function createCampaign(
        int     $templateId,
        int     $contextType,
        int     $contextId,
        ?string $recipientFilter = null
    ): int {
        $mailService    = $this->getMailService();
        $db             = $this->getDatabase();
        $tableTemplates = $mailService->getTableTemplates();
        $tableCampaigns = $mailService->getTableCampaigns();

        // 1. Lấy template
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'subject', 'body', 'context_type']))
            ->from($db->quoteName($tableTemplates))
            ->where($db->quoteName('id')        . ' = ' . $templateId)
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query);
        $template = $db->loadObject();

        if ($template === null) {
            throw new Exception('Không tìm thấy template có id = ' . $templateId);
        }

        // 2. Insert campaign (total_count = 0; buildQueue() sẽ cập nhật sau)
        $now    = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $userId = (int) Factory::getApplication()->getIdentity()->id;

        $query = $db->getQuery(true)
            ->insert($db->quoteName($tableCampaigns))
            ->columns($db->quoteName([
                'template_id', 'context_type', 'context_id',
                'recipient_filter', 'status',
                'total_count', 'sent_count', 'failed_count',
                'created_by', 'created_at',
            ]))
            ->values(implode(',', [
                (int) $templateId,
                (int) $contextType,
                (int) $contextId,
                $recipientFilter !== null ? $db->quote($recipientFilter) : 'NULL',
                MailCampaignStatus::Pending->value,
                0, 0, 0,
                $userId,
                $db->quote($now),
            ]));
        $db->setQuery($query);
        $db->execute();
        $campaignId = (int) $db->insertid();

        // 3. Resolve danh sách người nhận (lớp con thực hiện)
        $recipients = $this->resolveRecipients($contextType, $contextId, $recipientFilter);

        if (empty($recipients)) {
            throw new Exception('Không có người nhận nào phù hợp với ngữ cảnh đã chọn.');
        }

        // 4. Lấy placeholder resolver (lớp con thực hiện)
        $placeholderResolver = $this->buildPlaceholderResolver($contextType);

        // 5. Build queue — tự cập nhật total_count và status cho campaign
        $mailService->buildQueue(
            $campaignId,
            $template->subject,
            $template->body,
            $recipients,
            $placeholderResolver
        );

        return $campaignId;
    }

    // =========================================================================
    // Hủy campaign
    // =========================================================================

    /**
     * Hủy một campaign đang ở trạng thái Pending.
     *
     * @param  int  $campaignId
     *
     * @return void
     * @throws Exception
     * @since  1.0.3
     */
    public function cancelCampaign(int $campaignId): void
    {
        $this->getMailService()->cancelCampaign($campaignId);
    }

    // =========================================================================
    // Template helpers
    // =========================================================================

    /**
     * Lấy danh sách template phù hợp với một context type.
     *
     * Dùng trong Controller để quyết định Luồng A hay Luồng B.
     *
     * @param  int  $contextType
     *
     * @return object[]
     * @since  1.0.3
     */
    public function getTemplatesByContextType(int $contextType): array
    {
        return $this->getMailService()->getTemplatesByContextType($contextType);
    }
}
