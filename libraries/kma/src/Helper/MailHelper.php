<?php
namespace Kma\Library\Kma\Helper;
defined('_JEXEC') or die();


/**
 * @package     Kma.Library.Kma
 * @subpackage  Helper
 *
 * @copyright   (C) 2025 KMA
 * @license     GNU General Public License version 2 or later
 *
 * @since       1.0.3
 */

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Kma\Library\Kma\Enum\MailCampaignStatus;
use Kma\Library\Kma\Enum\MailQueueStatus;
use Kma\Library\Kma\Enum\MailRecipientType;

/**
 * Helper xử lý toàn bộ logic email thông báo của hệ thống:
 *   - Render template (thay thế placeholder bằng dữ liệu thực tế)
 *   - Resolve dữ liệu placeholder theo từng context
 *   - Tạo queue email cá nhân hóa từ campaign
 *   - Gửi một email qua Joomla Mailer
 *   - Cập nhật trạng thái queue và campaign sau khi gửi
 *
 * Class này là abstract — không khởi tạo trực tiếp, chỉ dùng qua static methods.
 *
 * Quy ước placeholder:
 *   Tất cả placeholder đều có dạng {snake_case}, ví dụ: {learner_name}, {exam_date}.
 *   Nếu một placeholder không có dữ liệu (null / rỗng), nó được thay bằng chuỗi rỗng '',
 *   KHÔNG giữ nguyên dạng {placeholder}.
 *
 * @since 1.0.3
 */
abstract class MailHelper
{
    // =========================================================================
    // Constants
    // =========================================================================

    /**
     * Số lần thử gửi tối đa cho một email trong queue.
     * Khi attempts >= MAX_ATTEMPTS mà vẫn thất bại → chuyển status = Failed.
     */
    public const MAX_ATTEMPTS = 3;

    /**
     * Số phút tối thiểu phải chờ giữa hai lần retry.
     * Task Scheduler kiểm tra: last_attempt_at < NOW() - INTERVAL {RETRY_INTERVAL_MINUTES} MINUTE
     */
    public const RETRY_INTERVAL_MINUTES = 5;

    /**
     * Số email tối đa xử lý trong một lần chạy của Task Scheduler (một batch).
     */
    public const BATCH_SIZE = 100;

    /**
     * Domain email của người học. Email = {learner_code}@{LEARNER_EMAIL_DOMAIN}
     */
    public const LEARNER_EMAIL_DOMAIN = 'actvn.edu.vn';

    // =========================================================================
    // Placeholder rendering
    // =========================================================================

    /**
     * Render một chuỗi template bằng cách thay thế tất cả placeholder bằng giá trị thực.
     *
     * Placeholder không có trong $data sẽ được thay bằng chuỗi rỗng ''.
     *
     * Ví dụ:
     *   renderTemplate('Kính gửi {learner_name}', ['{learner_name}' => 'Nguyễn Văn A'])
     *   → 'Kính gửi Nguyễn Văn A'
     *
     * @param  string               $template  Chuỗi template chứa placeholder dạng {key}
     * @param  array<string,string> $data      Mảng ['{placeholder}' => 'giá trị']
     *
     * @return string  Chuỗi đã được render
     * @since  1.0.3
     */
    public static function renderTemplate(string $template, array $data): string
    {
        // Tìm tất cả placeholder còn sót lại (chưa có trong $data) và thay bằng ''
        $rendered = str_replace(array_keys($data), array_values($data), $template);

        // Xóa các placeholder không khớp (dạng {anything}) còn sót lại
        return preg_replace('/\{[a-z_]+\}/', '', $rendered);
    }

    /**
     * Tạo địa chỉ email của người học từ learner code.
     *
     * @param  string  $learnerCode  Mã HVSV
     *
     * @return string  Địa chỉ email, ví dụ: ct060101@actvn.edu.vn
     * @since  1.0.3
     */
    public static function resolveLearnerEmail(string $learnerCode): string
    {
        return strtolower(trim($learnerCode)) . '@' . self::LEARNER_EMAIL_DOMAIN;
    }

    /**
     * Xây dựng mảng placeholder chung cho mọi context, từ thông tin người học.
     *
     * @param  object  $learner  Object có các thuộc tính: code, lastname, firstname
     *
     * @return array<string,string>
     * @since  1.0.3
     */
    public static function buildCommonPlaceholders(object $learner): array
    {
        $fullName = trim($learner->lastname . ' ' . $learner->firstname);

        return [
            '{learner_name}' => $fullName,
            '{learner_code}' => $learner->code,
        ];
    }

    /**
     * Xây dựng mảng placeholder cho context Exam (môn thi).
     *
     * Dữ liệu exam của thí sinh bao gồm: tên môn thi, ngày thi, giờ thi, phòng thi.
     * Nếu thí sinh chưa được xếp phòng thì {exam_date}, {exam_time}, {room_name} = ''.
     *
     * @param  object  $learner       Object learner (code, lastname, firstname)
     * @param  object  $examContext   Object chứa thông tin context exam của thí sinh:
     *                                - exam_name  (string)
     *                                - exam_start (string|null) UTC datetime 'Y-m-d H:i:s'
     *                                - room_name  (string|null)
     *
     * @return array<string,string>
     * @since  1.0.3
     */
    public static function buildExamPlaceholders(object $learner, object $examContext): array
    {
        $placeholders = self::buildCommonPlaceholders($learner);

        $placeholders['{exam_name}'] = $examContext->exam_name ?? '';

        // exam_start lưu UTC → chuyển sang Local Time để hiển thị
        if (!empty($examContext->exam_start)) {
            $placeholders['{exam_date}'] = DatetimeHelper::getFullDate($examContext->exam_start);
            $placeholders['{exam_time}'] = DatetimeHelper::getHourAndMinute($examContext->exam_start);
        } else {
            $placeholders['{exam_date}'] = '';
            $placeholders['{exam_time}'] = '';
        }

        $placeholders['{room_name}'] = $examContext->room_name ?? '';

        return $placeholders;
    }

    /**
     * Xây dựng mảng placeholder cho context ExamSeason (kỳ thi).
     *
     * @param  object  $learner        Object learner
     * @param  object  $seasonContext  Object chứa: examseason_name (string)
     *
     * @return array<string,string>
     * @since  1.0.3
     */
    public static function buildExamSeasonPlaceholders(object $learner, object $seasonContext): array
    {
        $placeholders = self::buildCommonPlaceholders($learner);
        $placeholders['{examseason_name}'] = $seasonContext->examseason_name ?? '';

        return $placeholders;
    }

    // =========================================================================
    // Queue management
    // =========================================================================

    /**
     * Tạo các bản ghi hàng đợi email (#__eqa_mail_queue) cho một campaign.
     *
     * Mỗi phần tử trong $recipients là một object gồm:
     *   - learner   (object): thông tin người học (code, lastname, firstname)
     *   - context   (object|null): dữ liệu context để build placeholder (tuỳ context_type)
     *   - placeholders (array|null): nếu đã có sẵn placeholder thì truyền trực tiếp,
     *                                bỏ qua learner + context
     *
     * Hàm này INSERT batch toàn bộ queue trong một transaction.
     *
     * @param  DatabaseInterface  $db           Joomla Database driver
     * @param  int                $campaignId   ID của campaign
     * @param  string             $subject      Template tiêu đề (chưa render)
     * @param  string             $body         Template nội dung (chưa render)
     * @param  array              $recipients   Danh sách người nhận (xem mô tả trên)
     * @param  callable           $placeholderResolver
     *                            Callback nhận vào một phần tử của $recipients,
     *                            trả về array<string,string> placeholder đã resolve.
     *                            Signature: fn(object $recipient): array
     *
     * @return int  Số bản ghi được insert vào queue
     * @throws Exception
     * @since  1.0.3
     */
    public static function buildQueue(
        DatabaseInterface $db,
        int               $campaignId,
        string            $subject,
        string            $body,
        array             $recipients,
        callable          $placeholderResolver
    ): int {
        if (empty($recipients)) {
            return 0;
        }

        $now    = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $count  = 0;

        $db->transactionStart();

        try {
            foreach ($recipients as $recipient) {
                // Resolve placeholder cho từng người nhận
                $placeholders = $placeholderResolver($recipient);

                // Render subject và body
                $renderedSubject = self::renderTemplate($subject, $placeholders);
                $renderedBody    = self::renderTemplate($body, $placeholders);

                // Xác định email, recipient_type, recipient_id
                $learner        = $recipient->learner;
                $recipientEmail = self::resolveLearnerEmail($learner->code);
                $recipientType  = MailRecipientType::Learner->value;
                $recipientId    = (int) $learner->id;

                // Insert vào queue
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__eqa_mail_queue'))
                    ->columns($db->quoteName([
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
                        (int) $recipientType,
                        (int) $recipientId,
                        $db->quote($recipientEmail),
                        $db->quote($renderedSubject),
                        $db->quote($renderedBody),
                        MailQueueStatus::Pending->value,
                        0,       // attempts
                        'NULL',  // last_attempt_at
                        'NULL',  // sent_at
                        'NULL',  // error_message
                        $db->quote($now),
                    ]));

                $db->setQuery($query);
                $db->execute();
                $count++;
            }

            // Cập nhật total_count cho campaign
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__eqa_mail_campaigns'))
                ->set($db->quoteName('total_count') . ' = ' . $count)
                ->set($db->quoteName('status') . ' = ' . MailCampaignStatus::Pending->value)
                ->where($db->quoteName('id') . ' = ' . (int) $campaignId);
            $db->setQuery($query);
            $db->execute();

            $db->transactionCommit();

        } catch (Exception $e) {
            $db->transactionRollback();
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
     * Được gọi bởi Task Scheduler plugin. Mỗi lần gọi xử lý tối đa BATCH_SIZE email.
     * Logic retry: chỉ lấy các bản ghi Pending có:
     *   - attempts < MAX_ATTEMPTS
     *   - last_attempt_at IS NULL hoặc last_attempt_at < NOW() - INTERVAL RETRY_INTERVAL_MINUTES
     *
     * Sau khi gửi xong batch, tự động kiểm tra và cập nhật status các campaign liên quan.
     *
     * @param  DatabaseInterface  $db  Joomla Database driver
     *
     * @return array{sent: int, failed: int}  Số email đã gửi thành công và thất bại trong batch này
     * @throws Exception
     * @since  1.0.3
     */
    public static function dispatchBatch(DatabaseInterface $db): array
    {
        $result = ['sent' => 0, 'failed' => 0];

        // Lấy batch email cần gửi
        $retryBoundary = (new \DateTime('now', new \DateTimeZone('UTC')))
            ->modify('-' . self::RETRY_INTERVAL_MINUTES . ' minutes')
            ->format('Y-m-d H:i:s');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__eqa_mail_queue'))
            ->where($db->quoteName('status') . ' = ' . MailQueueStatus::Pending->value)
            ->where($db->quoteName('attempts') . ' < ' . self::MAX_ATTEMPTS)
            ->where(
                '(' .
                $db->quoteName('last_attempt_at') . ' IS NULL' .
                ' OR ' . $db->quoteName('last_attempt_at') . ' < ' . $db->quote($retryBoundary) .
                ')'
            )
            ->order($db->quoteName('campaign_id') . ' ASC, ' . $db->quoteName('id') . ' ASC')
            ->setLimit(self::BATCH_SIZE);
        $db->setQuery($query);
        $items = $db->loadObjectList();

        if (empty($items)) {
            return $result;
        }

        // Lấy Joomla Mailer
        /** @var MailerFactoryInterface $mailerFactory */
        $mailerFactory = Factory::getContainer()->get(MailerFactoryInterface::class);

        // Tập hợp campaign_id cần kiểm tra sau khi dispatch
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
                } else {
                    $errorMessage = is_string($sendResult) ? $sendResult : 'Unknown mailer error';
                }
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }

            // Xác định status mới
            if ($sendSuccess) {
                $newStatus = MailQueueStatus::Sent->value;
                $result['sent']++;
            } elseif ($newAttempts >= self::MAX_ATTEMPTS) {
                // Đã hết số lần thử → Failed
                $newStatus = MailQueueStatus::Failed->value;
                $result['failed']++;
            } else {
                // Còn lần thử → giữ Pending, chờ retry sau RETRY_INTERVAL_MINUTES phút
                $newStatus = MailQueueStatus::Pending->value;
            }

            // Cập nhật bản ghi queue
            $updateQuery = $db->getQuery(true)
                ->update($db->quoteName('#__eqa_mail_queue'))
                ->set($db->quoteName('status')           . ' = ' . $newStatus)
                ->set($db->quoteName('attempts')         . ' = ' . $newAttempts)
                ->set($db->quoteName('last_attempt_at')  . ' = ' . $db->quote($now))
                ->where($db->quoteName('id') . ' = ' . (int) $item->id);

            if ($sendSuccess) {
                $updateQuery->set($db->quoteName('sent_at') . ' = ' . $db->quote($now));
            }

            if ($errorMessage !== null) {
                $updateQuery->set(
                    $db->quoteName('error_message') . ' = ' . $db->quote(mb_substr($errorMessage, 0, 65535))
                );
            }

            $db->setQuery($updateQuery);
            $db->execute();

            $affectedCampaignIds[(int) $item->campaign_id] = true;
        }

        // Cập nhật thống kê và status cho các campaign bị ảnh hưởng
        foreach (array_keys($affectedCampaignIds) as $campaignId) {
            self::syncCampaignStats($db, $campaignId);
        }

        return $result;
    }

    /**
     * Đồng bộ sent_count, failed_count và status cho một campaign
     * dựa trên thực tế trong bảng #__eqa_mail_queue.
     *
     * Điều kiện chuyển campaign sang Done:
     *   sent_count + failed_count = total_count  (toàn bộ queue đã được xử lý xong)
     *
     * @param  DatabaseInterface  $db          Joomla Database driver
     * @param  int                $campaignId
     *
     * @return void
     * @since  1.0.3
     */
    public static function syncCampaignStats(DatabaseInterface $db, int $campaignId): void
    {
        // Đếm số sent và failed từ queue
        $query = $db->getQuery(true)
            ->select([
                'SUM(CASE WHEN ' . $db->quoteName('status') . ' = ' . MailQueueStatus::Sent->value   . ' THEN 1 ELSE 0 END) AS cnt_sent',
                'SUM(CASE WHEN ' . $db->quoteName('status') . ' = ' . MailQueueStatus::Failed->value . ' THEN 1 ELSE 0 END) AS cnt_failed',
                'COUNT(1) AS cnt_total',
            ])
            ->from($db->quoteName('#__eqa_mail_queue'))
            ->where($db->quoteName('campaign_id') . ' = ' . $campaignId);
        $db->setQuery($query);
        $stats = $db->loadObject();

        $sentCount   = (int) ($stats->cnt_sent   ?? 0);
        $failedCount = (int) ($stats->cnt_failed ?? 0);
        $totalCount  = (int) ($stats->cnt_total  ?? 0);

        // Xác định status mới của campaign
        $newCampaignStatus = ($totalCount > 0 && ($sentCount + $failedCount) >= $totalCount)
            ? MailCampaignStatus::Done->value
            : MailCampaignStatus::Processing->value;

        $updateQuery = $db->getQuery(true)
            ->update($db->quoteName('#__eqa_mail_campaigns'))
            ->set($db->quoteName('sent_count')   . ' = ' . $sentCount)
            ->set($db->quoteName('failed_count') . ' = ' . $failedCount)
            ->set($db->quoteName('status')       . ' = ' . $newCampaignStatus)
            ->where($db->quoteName('id') . ' = ' . $campaignId)
            // Không cập nhật nếu campaign đã bị Cancelled
            ->where($db->quoteName('status') . ' != ' . MailCampaignStatus::Cancelled->value);
        $db->setQuery($updateQuery);
        $db->execute();
    }

    // =========================================================================
    // Campaign helpers
    // =========================================================================

    /**
     * Hủy một campaign đang ở trạng thái Pending.
     *
     * Chỉ hủy được khi campaign còn ở Pending (chưa có email nào được gửi).
     * Các bản ghi Pending trong queue sẽ không được gửi (Task Scheduler
     * bỏ qua campaign bị Cancelled khi query).
     *
     * Lưu ý: không xóa queue, chỉ đổi status campaign → Cancelled.
     * Dữ liệu queue được giữ lại để tra cứu lịch sử.
     *
     * @param  DatabaseInterface  $db          Joomla Database driver
     * @param  int                $campaignId
     *
     * @return void
     * @throws Exception  Nếu campaign không ở trạng thái Pending
     * @since  1.0.3
     */
    public static function cancelCampaign(DatabaseInterface $db, int $campaignId): void
    {
        // Kiểm tra trạng thái hiện tại
        $query = $db->getQuery(true)
            ->select($db->quoteName('status'))
            ->from($db->quoteName('#__eqa_mail_campaigns'))
            ->where($db->quoteName('id') . ' = ' . $campaignId);
        $db->setQuery($query);
        $currentStatus = $db->loadResult();

        if ($currentStatus === null) {
            throw new Exception('Không tìm thấy campaign có id = ' . $campaignId);
        }

        $statusEnum = MailCampaignStatus::from((int) $currentStatus);
        if (!$statusEnum->isCancellable()) {
            throw new Exception(
                sprintf(
                    'Không thể hủy campaign đang ở trạng thái "%s". Chỉ hủy được khi đang ở trạng thái "Chờ gửi".',
                    $statusEnum->getLabel()
                )
            );
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__eqa_mail_campaigns'))
            ->set($db->quoteName('status') . ' = ' . MailCampaignStatus::Cancelled->value)
            ->where($db->quoteName('id') . ' = ' . $campaignId);
        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Lấy danh sách template email theo context_type và trạng thái published.
     *
     * Dùng trong Controller để quyết định Luồng A (1 template) hay Luồng B (nhiều template).
     *
     * @param  DatabaseInterface  $db           Joomla Database driver
     * @param  int                $contextType  Giá trị của MailContextType enum
     *
     * @return object[]  Mảng object [{id, title, subject, body}]
     * @since  1.0.3
     */
    public static function getTemplatesByContextType(DatabaseInterface $db, int $contextType): array
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'subject', 'body']))
            ->from($db->quoteName('#__eqa_mail_templates'))
            ->where($db->quoteName('context_type') . ' = ' . (int) $contextType)
            ->where($db->quoteName('published')    . ' = 1')
            ->order($db->quoteName('title') . ' ASC');
        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }
}
