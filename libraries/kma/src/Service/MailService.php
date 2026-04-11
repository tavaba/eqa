<?php

/**
 * @package     Kma.Library.Kma
 * @subpackage  Service
 *
 * @copyright   (C) 2025 KMA
 * @license     GNU General Public License version 2 or later
 *
 * @since       1.0.3
 */

namespace Kma\Library\Kma\Service;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\Database\DatabaseDriver;
use Kma\Library\Kma\Enum\MailCampaignStatus;
use Kma\Library\Kma\Enum\MailQueueStatus;
use Kma\Library\Kma\Enum\MailRecipientType;

/**
 * Service xử lý toàn bộ logic email thông báo.
 *
 * Được đăng ký vào Joomla DI Container trong Service Provider của component.
 * Mọi cấu hình (tên bảng, domain email, batch size...) được inject một lần
 * khi khởi tạo — component và Task Scheduler plugin không cần truyền lại.
 *
 * -------------------------------------------------------------------------
 * Đăng ký trong Service Provider của com_eqa (ví dụ):
 *
 *   $container->share(MailService::class, static function (Container $c): MailService {
 *       return new MailService(
 *           db             : $c->get(\Joomla\Database\DatabaseDriver::class),
 *           tableTemplates : '#__eqa_mail_templates',
 *           tableCampaigns : '#__eqa_mail_campaigns',
 *           tableQueue     : '#__eqa_mail_queue',
 *           emailDomain    : 'actvn.edu.vn',
 *           // batchSize, maxAttempts, retryIntervalMinutes: dùng default nếu không truyền
 *       );
 *   });
 *
 * Lấy ra từ Container trong Model hoặc Plugin:
 *
 *   $mailService = Factory::getContainer()->get(MailService::class);
 * -------------------------------------------------------------------------
 *
 * Quy ước callback $placeholderResolver truyền vào buildQueue():
 *   Signature: fn(object $recipient): array<string, string>
 *   - Nhận vào một phần tử từ mảng $recipients
 *   - Trả về mảng ĐẦY ĐỦ ['{placeholder}' => 'giá trị'] cho người nhận đó
 *   - Khuyến nghị: gọi MailService::buildCommonPlaceholders() bên trong
 *     rồi array_merge() với placeholder đặc thù của context
 *
 * Quy ước cấu trúc $recipient (phần tử của mảng $recipients):
 *   Mỗi phần tử PHẢI có:
 *     - $recipient->learner (object):
 *         - ->id        (int)
 *         - ->code      (string)
 *         - ->lastname  (string)
 *         - ->firstname (string)
 *   Các thuộc tính khác (context data) do component tự định nghĩa và
 *   xử lý bên trong $placeholderResolver.
 *
 * Quy ước placeholder:
 *   Dạng {snake_case}, ví dụ: {learner_name}, {exam_date}.
 *   Placeholder không có trong mảng data → thay bằng '' (chuỗi rỗng).
 *
 * @since 1.0.3
 */
class MailService
{
    // =========================================================================
    // Default values cho các tham số cấu hình tùy chọn
    // =========================================================================

    /** Số lần thử gửi tối đa (mặc định). */
    public const DEFAULT_MAX_ATTEMPTS = 3;

    /** Số phút tối thiểu giữa hai lần retry (mặc định). */
    public const DEFAULT_RETRY_INTERVAL_MINUTES = 5;

    /** Số email tối đa xử lý trong một batch (mặc định). */
    public const DEFAULT_BATCH_SIZE = 100;

    /** Domain email người học (mặc định). */
    public const DEFAULT_LEARNER_EMAIL_DOMAIN = 'actvn.edu.vn';

    // =========================================================================
    // Constructor
    // =========================================================================

    /**
     * @param  DatabaseDriver  $db                    Joomla Database driver
     * @param  string          $tableTemplates        Tên bảng email template (có tiền tố #__)
     * @param  string          $tableCampaigns        Tên bảng campaign (có tiền tố #__)
     * @param  string          $tableQueue            Tên bảng hàng đợi email (có tiền tố #__)
     * @param  string          $emailDomain           Domain email người nhận
     * @param  int             $batchSize             Số email tối đa mỗi batch
     * @param  int             $maxAttempts           Số lần thử tối đa trước khi Failed
     * @param  int             $retryIntervalMinutes  Số phút chờ giữa hai lần retry
     */
    public function __construct(
        private readonly DatabaseDriver $db,
        private readonly string         $tableTemplates,
        private readonly string         $tableCampaigns,
        private readonly string         $tableQueue,
        private readonly string         $emailDomain           = self::DEFAULT_LEARNER_EMAIL_DOMAIN,
        private readonly int            $batchSize             = self::DEFAULT_BATCH_SIZE,
        private readonly int            $maxAttempts           = self::DEFAULT_MAX_ATTEMPTS,
        private readonly int            $retryIntervalMinutes  = self::DEFAULT_RETRY_INTERVAL_MINUTES,
    ) {}

    // =========================================================================
    // Getters
    // =========================================================================

    public function getTableTemplates(): string
    {
        return $this->tableTemplates;
    }

    public function getTableCampaigns(): string
    {
        return $this->tableCampaigns;
    }

    public function getTableQueue(): string
    {
        return $this->tableQueue;
    }

    public function getEmailDomain(): string
    {
        return $this->emailDomain;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getRetryIntervalMinutes(): int
    {
        return $this->retryIntervalMinutes;
    }

    // =========================================================================
    // Pure functions (static) — component có thể gọi trực tiếp không cần inject
    // =========================================================================

    /**
     * Render một chuỗi template bằng cách thay thế tất cả placeholder.
     *
     * - Placeholder có trong $data → thay bằng giá trị tương ứng.
     * - Placeholder dạng {snake_case} KHÔNG có trong $data → thay bằng ''.
     *
     * Ví dụ:
     *   MailService::renderTemplate(
     *       'Kính gửi {learner_name}, môn thi: {exam_name}.',
     *       ['{learner_name}' => 'Nguyễn Văn A', '{exam_name}' => 'Toán cao cấp 1']
     *   )
     *   → 'Kính gửi Nguyễn Văn A, môn thi: Toán cao cấp 1.'
     *
     * @param  string               $template  Chuỗi template chứa placeholder dạng {key}
     * @param  array<string,string> $data      Mảng ['{placeholder}' => 'giá trị']
     *
     * @return string
     * @since  1.0.3
     */
    public static function renderTemplate(string $template, array $data): string
    {
        $rendered = str_replace(array_keys($data), array_values($data), $template);

        // Xóa các placeholder dạng {snake_case} không khớp còn sót lại
        return preg_replace('/\{[a-z_]+\}/', '', $rendered);
    }

    /**
     * Xây dựng mảng placeholder chung cho mọi context, từ thông tin người học.
     *
     * Placeholder được tạo ra:
     *   {learner_name} — họ tên đầy đủ (lastname + firstname)
     *   {learner_code} — mã HVSV
     *
     * Component cần array_merge() kết quả này với placeholder đặc thù
     * của context bên trong $placeholderResolver truyền vào buildQueue().
     *
     * @param  object  $learner  Cần có: code (string), lastname (string), firstname (string)
     *
     * @return array<string,string>
     * @since  1.0.3
     */
    public static function buildCommonPlaceholders(object $learner): array
    {
        return [
            '{learner_name}' => trim($learner->lastname . ' ' . $learner->firstname),
            '{learner_code}' => $learner->code,
        ];
    }

    /**
     * Tạo địa chỉ email người học từ learner code.
     * Sử dụng $this->emailDomain đã được inject.
     *
     * @param  string  $learnerCode
     *
     * @return string  Ví dụ: ct060101@actvn.edu.vn
     * @since  1.0.3
     */
    public function resolveLearnerEmail(string $learnerCode): string
    {
        return strtolower(trim($learnerCode)) . '@' . $this->emailDomain;
    }

    // =========================================================================
    // Template
    // =========================================================================

    /**
     * Lấy danh sách template email theo context_type và trạng thái published.
     *
     * Dùng trong Controller để quyết định Luồng A hay Luồng B:
     *   count = 0 → không có template phù hợp, báo lỗi
     *   count = 1 → Luồng A: dùng luôn template duy nhất, gửi không hỏi
     *   count > 1 → Luồng B: hiển thị modal cho người dùng chọn
     *
     * @param  int  $contextType  Giá trị của MailContextType enum
     *
     * @return object[]  [{id, title, subject, body}] sắp xếp theo title ASC
     * @since  1.0.3
     */
    public function getTemplatesByContextType(int $contextType): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'title', 'subject', 'body']))
            ->from($this->db->quoteName($this->tableTemplates))
            ->where($this->db->quoteName('context_type') . ' = ' . (int) $contextType)
            ->where($this->db->quoteName('published')    . ' = 1')
            ->order($this->db->quoteName('title') . ' ASC');
        $this->db->setQuery($query);

        return $this->db->loadObjectList() ?: [];
    }

    // =========================================================================
    // Queue management
    // =========================================================================

    /**
     * Tạo các bản ghi hàng đợi email cho một campaign.
     *
     * Toàn bộ INSERT thực hiện trong một transaction.
     * Sau khi INSERT xong, cập nhật total_count và status=Pending cho campaign.
     *
     * @param  int       $campaignId
     * @param  string    $subjectTemplate      Template tiêu đề (chưa render)
     * @param  string    $bodyTemplate         Template nội dung HTML (chưa render)
     * @param  array     $recipients           Danh sách người nhận (xem quy ước ở docblock class)
     * @param  callable  $placeholderResolver
     *             fn(object $recipient): array<string, string>
     *             Phải trả về mảng đầy đủ tất cả placeholder cho người nhận đó.
     *
     * @return int  Số bản ghi được insert vào queue
     * @throws Exception
     * @since  1.0.3
     */
    public function buildQueue(
        int      $campaignId,
        string   $subjectTemplate,
        string   $bodyTemplate,
        array    $recipients,
        callable $placeholderResolver
    ): int {
        if (empty($recipients)) {
            return 0;
        }

        $now   = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $count = 0;

        $this->db->transactionStart();

        try {
            foreach ($recipients as $recipient) {
                // Component chịu trách nhiệm resolve toàn bộ placeholder
                $placeholders    = $placeholderResolver($recipient);
                $renderedSubject = self::renderTemplate($subjectTemplate, $placeholders);
                $renderedBody    = self::renderTemplate($bodyTemplate, $placeholders);

                $learner        = $recipient->learner;
                $recipientEmail = $this->resolveLearnerEmail($learner->code);

                $query = $this->db->getQuery(true)
                    ->insert($this->db->quoteName($this->tableQueue))
                    ->columns($this->db->quoteName([
                        'campaign_id',
                        'recipient_type',
                        'recipient_id',
                        'recipient_email',
                        'subject',
                        'body',
                        'status',
                        'attempts',
                        'last_attempt_at',
                        'sent_at',
                        'error_message',
                        'created_at',
                    ]))
                    ->values(implode(',', [
                        (int) $campaignId,
                        MailRecipientType::Learner->value,
                        (int) $learner->id,
                        $this->db->quote($recipientEmail),
                        $this->db->quote($renderedSubject),
                        $this->db->quote($renderedBody),
                        MailQueueStatus::Pending->value,
                        0,
                        'NULL',  // last_attempt_at
                        'NULL',  // sent_at
                        'NULL',  // error_message
                        $this->db->quote($now),
                    ]));

                $this->db->setQuery($query);
                $this->db->execute();
                $count++;
            }

            // Cập nhật total_count và đảm bảo status = Pending
            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName($this->tableCampaigns))
                ->set($this->db->quoteName('total_count') . ' = ' . $count)
                ->set($this->db->quoteName('status') . ' = ' . MailCampaignStatus::Pending->value)
                ->where($this->db->quoteName('id') . ' = ' . (int) $campaignId);
            $this->db->setQuery($query);
            $this->db->execute();

            $this->db->transactionCommit();
        }
        catch (Exception $e) {
            $this->db->transactionRollback();
            throw $e;
        }

        return $count;
    }

    // =========================================================================
    // Dispatch (gửi email)
    // =========================================================================

    /**
     * Gửi một batch email từ hàng đợi.
     *
     * Được gọi bởi Task Scheduler plugin của component. Mỗi lần gọi xử lý
     * tối đa $this->batchSize email Pending đủ điều kiện retry.
     *
     * Điều kiện lấy email để gửi:
     *   - status = Pending
     *   - attempts < $this->maxAttempts
     *   - last_attempt_at IS NULL  HOẶC
     *     last_attempt_at < NOW() - INTERVAL $this->retryIntervalMinutes MINUTE
     *
     * Sau khi gửi xong batch, tự động đồng bộ thống kê cho các campaign liên quan.
     *
     * @return array{sent: int, failed: int}
     * @throws Exception
     * @since  1.0.3
     */
    public function dispatchBatch(): array
    {
        $result = ['sent' => 0, 'failed' => 0];

        $retryBoundary = (new \DateTime('now', new \DateTimeZone('UTC')))
            ->modify('-' . $this->retryIntervalMinutes . ' minutes')
            ->format('Y-m-d H:i:s');

        // Lấy batch email cần gửi — sắp xếp theo campaign_id ASC, id ASC
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName($this->tableQueue))
            ->where($this->db->quoteName('status')   . ' = ' . MailQueueStatus::Pending->value)
            ->where($this->db->quoteName('attempts') . ' < ' . $this->maxAttempts)
            ->where(
                '(' .
                $this->db->quoteName('last_attempt_at') . ' IS NULL' .
                ' OR ' .
                $this->db->quoteName('last_attempt_at') . ' < ' . $this->db->quote($retryBoundary) .
                ')'
            )
            ->order(
                $this->db->quoteName('campaign_id') . ' ASC, ' .
                $this->db->quoteName('id') . ' ASC'
            )
            ->setLimit($this->batchSize);
        $this->db->setQuery($query);
        $items = $this->db->loadObjectList();

        if (empty($items)) {
            return $result;
        }

        /** @var MailerFactoryInterface $mailerFactory */
        $mailerFactory       = Factory::getContainer()->get(MailerFactoryInterface::class);
        $affectedCampaignIds = [];

        foreach ($items as $item) {
            $now          = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $newAttempts  = (int) $item->attempts + 1;
            $sendSuccess  = false;
            $errorMessage = null;

            try {
                $mailer = $mailerFactory->createMailer();
                $mailer->addRecipient($item->recipient_email);
                $mailer->setSubject($item->subject);
                $mailer->setBody($item->body);
                $mailer->isHTML(true);

                $sendResult = $mailer->Send();

                if ($sendResult === true) {
                    $sendSuccess = true;
                }
                else {
                    $errorMessage = is_string($sendResult) ? $sendResult : 'Unknown mailer error';
                }
            }
            catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }

            // Xác định status mới cho bản ghi queue
            if ($sendSuccess) {
                $newStatus = MailQueueStatus::Sent->value;
                $result['sent']++;
            }
            elseif ($newAttempts >= $this->maxAttempts) {
                // Hết số lần thử → Failed, không retry nữa
                $newStatus = MailQueueStatus::Failed->value;
                $result['failed']++;
            }
            else {
                // Còn lần thử → giữ Pending, Task Scheduler retry sau khoảng nghỉ
                $newStatus = MailQueueStatus::Pending->value;
            }

            // Cập nhật bản ghi queue
            $updateQuery = $this->db->getQuery(true)
                ->update($this->db->quoteName($this->tableQueue))
                ->set($this->db->quoteName('status')          . ' = ' . $newStatus)
                ->set($this->db->quoteName('attempts')        . ' = ' . $newAttempts)
                ->set($this->db->quoteName('last_attempt_at') . ' = ' . $this->db->quote($now))
                ->where($this->db->quoteName('id') . ' = ' . (int) $item->id);

            if ($sendSuccess) {
                $updateQuery->set(
                    $this->db->quoteName('sent_at') . ' = ' . $this->db->quote($now)
                );
            }

            if ($errorMessage !== null) {
                $updateQuery->set(
                    $this->db->quoteName('error_message') . ' = ' .
                    $this->db->quote(mb_substr($errorMessage, 0, 65535))
                );
            }

            $this->db->setQuery($updateQuery);
            $this->db->execute();

            $affectedCampaignIds[(int) $item->campaign_id] = true;
        }

        // Đồng bộ thống kê cho tất cả campaign bị ảnh hưởng trong batch này
        foreach (array_keys($affectedCampaignIds) as $campaignId) {
            $this->syncCampaignStats($campaignId);
        }

        return $result;
    }

    /**
     * Đồng bộ sent_count, failed_count và status cho một campaign
     * dựa trên dữ liệu thực tế trong bảng queue.
     *
     * Campaign chuyển sang Done khi: sent_count + failed_count = total_count.
     * Campaign đang Cancelled sẽ không bị cập nhật.
     *
     * @param  int  $campaignId
     *
     * @return void
     * @since  1.0.3
     */
    public function syncCampaignStats(int $campaignId): void
    {
        $query = $this->db->getQuery(true)
            ->select([
                'SUM(CASE WHEN ' . $this->db->quoteName('status') . ' = ' . MailQueueStatus::Sent->value
                    . ' THEN 1 ELSE 0 END) AS cnt_sent',
                'SUM(CASE WHEN ' . $this->db->quoteName('status') . ' = ' . MailQueueStatus::Failed->value
                    . ' THEN 1 ELSE 0 END) AS cnt_failed',
                'COUNT(1) AS cnt_total',
            ])
            ->from($this->db->quoteName($this->tableQueue))
            ->where($this->db->quoteName('campaign_id') . ' = ' . $campaignId);
        $this->db->setQuery($query);
        $stats = $this->db->loadObject();

        $sentCount   = (int) ($stats->cnt_sent   ?? 0);
        $failedCount = (int) ($stats->cnt_failed ?? 0);
        $totalCount  = (int) ($stats->cnt_total  ?? 0);

        $newCampaignStatus = ($totalCount > 0 && ($sentCount + $failedCount) >= $totalCount)
            ? MailCampaignStatus::Done->value
            : MailCampaignStatus::Processing->value;

        $updateQuery = $this->db->getQuery(true)
            ->update($this->db->quoteName($this->tableCampaigns))
            ->set($this->db->quoteName('sent_count')   . ' = ' . $sentCount)
            ->set($this->db->quoteName('failed_count') . ' = ' . $failedCount)
            ->set($this->db->quoteName('status')       . ' = ' . $newCampaignStatus)
            ->where($this->db->quoteName('id') . ' = ' . $campaignId)
            // Không cập nhật campaign đã bị Cancelled
            ->where($this->db->quoteName('status') . ' != ' . MailCampaignStatus::Cancelled->value);
        $this->db->setQuery($updateQuery);
        $this->db->execute();
    }

    // =========================================================================
    // Campaign helpers
    // =========================================================================

    /**
     * Hủy một campaign đang ở trạng thái Pending.
     *
     * Chỉ hủy được khi campaign còn ở Pending (chưa có email nào được gửi).
     * Không xóa queue — dữ liệu được giữ lại để tra cứu lịch sử.
     *
     * @param  int  $campaignId
     *
     * @return void
     * @throws Exception
     * @since  1.0.3
     */
    public function cancelCampaign(int $campaignId): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('status'))
            ->from($this->db->quoteName($this->tableCampaigns))
            ->where($this->db->quoteName('id') . ' = ' . $campaignId);
        $this->db->setQuery($query);
        $currentStatus = $this->db->loadResult();

        if ($currentStatus === null) {
            throw new Exception('Không tìm thấy campaign có id = ' . $campaignId);
        }

        $statusEnum = MailCampaignStatus::from((int) $currentStatus);

        if (!$statusEnum->isCancellable()) {
            throw new Exception(sprintf(
                'Không thể hủy campaign đang ở trạng thái "%s". Chỉ hủy được khi đang ở trạng thái "%s".',
                $statusEnum->getLabel(),
                MailCampaignStatus::Pending->getLabel()
            ));
        }

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName($this->tableCampaigns))
            ->set($this->db->quoteName('status') . ' = ' . MailCampaignStatus::Cancelled->value)
            ->where($this->db->quoteName('id') . ' = ' . $campaignId);
        $this->db->setQuery($query);
        $this->db->execute();
    }
}
