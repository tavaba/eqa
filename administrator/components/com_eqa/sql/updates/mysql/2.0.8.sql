-- =============================================================================
-- Migration: Hệ thống gửi email thông báo cho com_eqa
-- Version  : 2.0.8
-- Date     : 2026
--
-- Tạo 3 bảng:
--   1. #__eqa_mail_templates  — Quản lý mẫu email theo ngữ cảnh
--   2. #__eqa_mail_campaigns  — Mỗi lần kích hoạt gửi = 1 campaign
--   3. #__eqa_mail_queue      — Hàng đợi email cá nhân hóa từng người nhận
--
-- Enum ánh xạ (PHP ↔ DB):
--   MailContextType    → mail_templates.context_type,  mail_campaigns.context_type
--   MailCampaignStatus → mail_campaigns.status
--   MailQueueStatus    → mail_queue.status
--   MailRecipientType  → mail_queue.recipient_type
-- =============================================================================


-- -----------------------------------------------------------------------------
-- Bảng 1: #__eqa_mail_templates
--
-- Lưu các mẫu (template) email được định nghĩa sẵn cho từng ngữ cảnh.
-- Một context_type có thể có nhiều template (không UNIQUE).
--
-- context_type → enum MailContextType:
--   1 = Exam (Môn thi)
--   2 = ExamSeason (Kỳ thi)
--   3 = Group (Lớp hành chính)
--   4 = Course (Khóa học)
--   5 = Manual (Danh sách thủ công)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__eqa_mail_templates` (
    `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,

    -- Tên template — hiển thị trong modal chọn template (Luồng B)
    `title`        VARCHAR(200)     NOT NULL,

    -- Ngữ cảnh áp dụng → MailContextType
    `context_type` TINYINT UNSIGNED NOT NULL,

    -- Tiêu đề email — có thể chứa placeholder, ví dụ: "Thông báo lịch thi {exam_name}"
    `subject`      VARCHAR(500)     NOT NULL,

    -- Nội dung email dạng HTML — chứa các placeholder như {learner_name}, {room_name}...
    `body`         LONGTEXT         NOT NULL,

    -- 0 = ẩn, 1 = hiển thị / có hiệu lực
    `published`    TINYINT          NOT NULL DEFAULT 1,

    `created_by`   INT UNSIGNED     NOT NULL DEFAULT 0,
    `created_at`   DATETIME         NOT NULL COMMENT 'UTC',
    `modified_by`  INT UNSIGNED     NOT NULL DEFAULT 0,
    `modified_at`  DATETIME         NULL     COMMENT 'UTC',

    PRIMARY KEY (`id`),

    -- Index để query nhanh template theo context khi hiển thị modal
    KEY `idx_context_published` (`context_type`, `published`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- Bảng 2: #__eqa_mail_campaigns
--
-- Mỗi lần người dùng kích hoạt "Gửi thông báo" = 1 bản ghi campaign.
-- Lưu đủ thông tin để:
--   - Theo dõi tiến độ gửi (total / sent / failed)
--   - Tra cứu lịch sử từ view ngữ cảnh (lọc theo context_type + context_id)
--   - Tra cứu từ view tập trung mailcampaigns
--
-- status → enum MailCampaignStatus:
--   0 = Pending    — đã tạo queue, chờ Task Scheduler xử lý
--   1 = Processing — Task Scheduler đang xử lý
--   2 = Done       — đã xử lý xong (sent_count + failed_count = total_count)
--   3 = Cancelled  — đã hủy trước khi gửi
--
-- recipient_filter (JSON, nullable):
--   Lưu điều kiện lọc người nhận bổ sung do controller action quyết định.
--   Ví dụ: {"has_room": true}  → chỉ gửi cho thí sinh đã có phòng thi
--            {"anomaly": 1}     → chỉ gửi cho thí sinh vắng thi
--   NULL = gửi cho toàn bộ đối tượng của context.
--   Trường này mang tính tham khảo/audit — logic resolve thực tế nằm trong
--   controller action tương ứng.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__eqa_mail_campaigns` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,

    -- Template đã dùng để tạo campaign này
    `template_id`      INT UNSIGNED     NOT NULL,

    -- Sao chép context_type từ template tại thời điểm tạo (để query không cần JOIN)
    -- → MailContextType
    `context_type`     TINYINT UNSIGNED NOT NULL,

    -- ID của đối tượng ngữ cảnh:
    --   context_type=1 (Exam)       → exam_id
    --   context_type=2 (ExamSeason) → examseason_id
    --   context_type=3 (Group)      → group_id
    --   context_type=4 (Course)     → course_id
    --   context_type=5 (Manual)     → NULL
    `context_id`       INT UNSIGNED     NULL,

    -- Điều kiện lọc bổ sung (JSON). NULL = toàn bộ đối tượng của context.
    `recipient_filter` TEXT             NULL,

    -- Trạng thái campaign → MailCampaignStatus
    `status`           TINYINT          NOT NULL DEFAULT 0,

    -- Thống kê tiến độ
    `total_count`      INT UNSIGNED     NOT NULL DEFAULT 0,
    `sent_count`       INT UNSIGNED     NOT NULL DEFAULT 0,
    `failed_count`     INT UNSIGNED     NOT NULL DEFAULT 0,

    `created_by`       INT UNSIGNED     NOT NULL DEFAULT 0,
    `created_at`       DATETIME         NOT NULL COMMENT 'UTC',

    PRIMARY KEY (`id`),

    -- Index phục vụ query lịch sử từ view ngữ cảnh
    KEY `idx_context` (`context_type`, `context_id`),

    -- Index phục vụ query theo người tạo + thời gian (view tập trung)
    KEY `idx_created` (`created_by`, `created_at`),

    CONSTRAINT `fk_mail_campaigns_template`
        FOREIGN KEY (`template_id`)
        REFERENCES `#__eqa_mail_templates` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- Bảng 3: #__eqa_mail_queue
--
-- Mỗi bản ghi = một email cụ thể, đã được render cá nhân hóa, chờ gửi.
-- Task Scheduler đọc các bản ghi có status=0 (Pending) và gửi theo batch.
--
-- recipient_type → enum MailRecipientType (lib_kma):
--   0 = Learner  (người học)      → recipient_id = #__eqa_learners.id
--   1 = Employee (người lao động) → recipient_id = #__eqa_employees.id
--   2 = External (ngoài hệ thống) → recipient_id = NULL
--
-- status → enum MailQueueStatus:
--   0 = Pending — chờ gửi (kể cả đang retry, attempts > 0)
--   1 = Sent    — đã gửi thành công; sent_at được set
--   2 = Failed  — thất bại sau attempts >= 3; error_message lưu lỗi cuối
--
-- Quan hệ giữa các trường thời gian:
--   created_at      — thời điểm bản ghi được tạo (không thay đổi)
--   last_attempt_at — thời điểm lần thử gửi gần nhất (NULL nếu chưa thử lần nào)
--                     Task Scheduler cập nhật sau mỗi lần thử (thành công hay thất bại)
--                     Dùng để kiểm soát khoảng cách tối thiểu giữa các lần retry
--   sent_at         — thời điểm gửi thành công (NULL nếu chưa gửi được)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__eqa_mail_queue` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,

    -- Campaign chứa email này
    `campaign_id`      INT UNSIGNED     NOT NULL,

    -- Loại người nhận → MailRecipientType
    `recipient_type`   TINYINT UNSIGNED NOT NULL DEFAULT 0,

    -- ID người nhận trong bảng tương ứng. NULL nếu recipient_type = External (2).
    `recipient_id`     INT UNSIGNED     NULL,

    -- Địa chỉ email thực tế để gửi — luôn được resolve và lưu khi tạo queue.
    -- Với learner: {learner_code}@actvn.edu.vn
    `recipient_email`  VARCHAR(255)     NOT NULL,

    -- Tiêu đề và nội dung email đã được render (placeholder đã được thay thế)
    `subject`          VARCHAR(500)     NOT NULL,
    `body`             LONGTEXT         NOT NULL,

    -- Trạng thái gửi → MailQueueStatus
    `status`           TINYINT          NOT NULL DEFAULT 0,

    -- Số lần đã thử gửi.
    -- Khi attempts >= 3 và vẫn thất bại → chuyển status = 2 (Failed).
    `attempts`         TINYINT UNSIGNED NOT NULL DEFAULT 0,

    -- Thời điểm lần thử gửi gần nhất (UTC).
    -- NULL = chưa thử lần nào (bản ghi mới được tạo vào queue).
    -- Task Scheduler cập nhật trường này sau mỗi lần thử gửi.
    -- Dùng để kiểm soát khoảng cách tối thiểu giữa các lần retry.
    `last_attempt_at`  DATETIME         NULL     COMMENT 'UTC',

    -- Thời điểm gửi thành công (UTC). NULL nếu chưa gửi được.
    `sent_at`          DATETIME         NULL     COMMENT 'UTC',

    -- Thông báo lỗi của lần thử gần nhất (nếu thất bại)
    `error_message`    TEXT             NULL,

    `created_at`       DATETIME         NOT NULL COMMENT 'UTC',

    PRIMARY KEY (`id`),

    -- Index chính — Task Scheduler query: WHERE campaign_id=X AND status=0
    KEY `idx_campaign_status` (`campaign_id`, `status`),

    -- Index phụ — Task Scheduler query toàn bộ pending, lọc theo last_attempt_at để retry
    KEY `idx_status_attempt` (`status`, `attempts`, `last_attempt_at`),

    CONSTRAINT `fk_mail_queue_campaign`
        FOREIGN KEY (`campaign_id`)
        REFERENCES `#__eqa_mail_campaigns` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
