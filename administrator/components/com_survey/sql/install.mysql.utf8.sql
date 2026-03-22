/*
 * com_survey
 * Version 1.0.1
 */
DROP TABLE IF EXISTS `#__survey_logs`;
CREATE TABLE `#__survey_logs` (
    `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED        NULL,
    `username`      VARCHAR(150)        NULL,
    `action`        SMALLINT UNSIGNED   NOT NULL,
    `is_success`    TINYINT(1)          NOT NULL DEFAULT 0,
    `error_message` VARCHAR(500)        NULL,
    `object_type`   SMALLINT UNSIGNED   NOT NULL,
    `object_id`     BIGINT UNSIGNED     NOT NULL,
    `object_title`  VARCHAR(500)        NULL,
    `old_value`     TEXT                NULL,
    `new_value`     TEXT                NULL,
    `extra_data`    TEXT                NULL,
    `ip_address`    BINARY(16)          NULL,
    `created_at`    DATETIME(3)         NOT NULL,

    PRIMARY KEY (`id`),
    INDEX `idx_action`      (`action`),
    INDEX `idx_object`      (`object_type`, `object_id`),
    INDEX `idx_user`        (`user_id`),
    INDEX `idx_created_at`  (`created_at`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4  COMMENT='Nhật ký thao tác người dùng';

CREATE TABLE IF NOT EXISTS `#__survey_units`(
	`id`				INT AUTO_INCREMENT,
	`type`				TINYINT NOT NULL			COMMENT 'Phân loại: Khóa đào tạo, Phòng/ban/khoa, Công ty...',
	`code`				VARCHAR(20) NOT NULL		COMMENT 'Ký hiệu',
	`name`				VARCHAR(255) NOT NULL,
	`note`				TEXT						COMMENT 'Ghi chú bổ sung (nếu cần)',
	`published` 		BOOLEAN NOT NULL DEFAULT TRUE,
	`created`			DATETIME 					COMMENT 'Ngày giờ khởi tạo',
	`created_by`		INT 						COMMENT 'ID người tạo (liên kết đến #__users.id)',
	`modified`			DATETIME 					COMMENT 'Ngày giờ cập nhật lần cuối',
	`modified_by`		INT 						COMMENT 'ID người cập nhật lần cuối',
	`check_out`			INT DEFAULT 0 				COMMENT 'ID của người dùng đang chỉnh sửa bản ghi (liên kết đến #__users.id)',
	`checked_out_time`	DATETIME 					COMMENT 'Thời điểm người đó mở bản ghi để chỉnh sửa',
    PRIMARY KEY (`id`),
	UNIQUE (`code`),
	INDEX idx_units_published (`published`)
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Đơn vị công tác';
CREATE TABLE IF NOT EXISTS `#__survey_respondents`(
	`id` INT AUTO_INCREMENT,
	`type`				TINYINT NOT NULL				COMMENT 'Phân loại: HVSV, CBGV,... ',
	`is_person`			BOOLEAN NOT NULL DEFAULT TRUE	COMMENT 'Nếu không phải cá nhân thì là một tổ chức',
	`code`				VARCHAR(50)						COMMENT 'Mã định danh dạng chữ (nếu là cá nhân)',
	`lastname`			VARCHAR(50) NOT NULL DEFAULT ''	COMMENT 'Họ đệm (nếu là cá nhân)',
	`firstname`			VARCHAR(50) NOT NULL DEFAULT ''	COMMENT 'Tên (nếu là cá nhân)',
	`gender`			TINYINT							COMMENT 'Giới tính (nếu là cá nhân)',
	`name`				VARCHAR(255) DEFAULT ''			COMMENT 'Tên gọi (nếu là tổ chức)',
	`email`				VARCHAR(255)					COMMENT 'Địa chỉ email liên hệ',
	`phone`				VARCHAR(50)						COMMENT 'Số điện thoại liên hệ',
	`unit_id`			INT								COMMENT 'FK: Unit',
	`note`				TEXT							COMMENT 'Ghi chú bổ sung (nếu cần)',
	`published`			BOOLEAN  NOT NULL DEFAULT TRUE,
	`created`			DATETIME 						COMMENT 'Ngày giờ khởi tạo',
	`created_by`		INT 							COMMENT 'ID người tạo (liên kết đến #__users.id)',
	`modified`			DATETIME 						COMMENT 'Ngày giờ cập nhật lần cuối',
	`modified_by`		INT 							COMMENT 'ID người cập nhật lần cuối',
	`check_out`			INT DEFAULT 0 					COMMENT 'ID của người dùng đang chỉnh sửa bản ghi (liên kết đến #__users.id)',
	`checked_out_time`	DATETIME 						COMMENT 'Thời điểm người đó mở bản ghi để chỉnh sửa',
    PRIMARY KEY (`id`),
	UNIQUE(`code`),
	CONSTRAINT fk_survey_respondents_unit FOREIGN KEY(`unit_id`)
		REFERENCES `#__survey_units`(`id`)
		ON DELETE RESTRICT,
	INDEX idx_respondents_unit (`unit_id`),
    INDEX idx_respondents_published (`published`)
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Người được khảo sát';

CREATE TABLE IF NOT EXISTS `#__survey_tokens`(
	`value`			CHAR(12) NOT NULL				COMMENT 'Giá trị của token',
	`survey_id`		INT NOT NULL					COMMENT 'One token is valid for one survey only',
	`respondent_id`	INT NOT NULL,
	UNIQUE (`value`),
	UNIQUE (`survey_id`, `respondent_id`)
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Danh mục token được sử dụng để tạo liên kết cá nhân hóa. Mỗi token chỉ có giá trị cho một cuộc khảo sát';

CREATE TABLE IF NOT EXISTS `#__survey_respondentgroups`(
	`id` 				INT AUTO_INCREMENT,
	`name` 				VARCHAR(255) NOT NULL		COMMENT 'Tên nhóm người được khảo sát',
	`description` 		TEXT 						COMMENT 'Mô tả ngắn về nhóm (mục đích, tiêu chí hình thành...)',
	`type` 				TINYINT NOT NULL			COMMENT 'Loại nhóm (VD: student, staff, employer, custom)',
	`asset_id` 			INT NOT NULL DEFAULT 0 		COMMENT 'Khóa ngoại liên kết đến bảng #__assets trong Joomla. Bắt buộc phải có default value',
	`created` 			DATETIME NOT NULL			COMMENT 'Thời điểm tạo nhóm',
	`created_by` 		INT NOT NULL				COMMENT 'ID người tạo (liên kết #__users.id)',
	`modified` 			DATETIME 					COMMENT 'Thời điểm cập nhật gần nhất',
	`modified_by` 		INT 						COMMENT 'ID người sửa lần cuối',
	`published` 		BOOLEAN NOT NULL DEFAULT TRUE,
	`checked_out` 		INT DEFAULT 0 				COMMENT 'ID người đang sửa nhóm (nếu có)',
	`checked_out_time`	DATETIME					COMMENT	'Thời điểm nhóm được check-out',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Nhóm người được khảo sát';

CREATE TABLE IF NOT EXISTS `#__survey_respondentgroup_respondent`(
    `respondent_id`		INT NOT NULL,
	`group_id`			INT NOT NULL,
    PRIMARY KEY (`respondent_id`, `group_id`),
	CONSTRAINT fk_survey_respondentgroup_respondent_respondent FOREIGN KEY(`respondent_id`)
		REFERENCES `#__survey_respondents`(`id`)
		ON DELETE CASCADE,
	CONSTRAINT fk_survey_respondentgroup_respondent_group FOREIGN KEY(`group_id`)
		REFERENCES `#__survey_respondentgroups`(`id`)
		ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `#__survey_classes`(
	`id` 				INT AUTO_INCREMENT,
	`code`				VARCHAR(100),
	`term`				INT NOT NULL,
	`academicyear`		INT NOT NULL,
	`subject`			VARCHAR(255),
	`lecturer`			VARCHAR(255),
	`size`				INT NOT NULL,
	`learners`			TEXT DEFAULT NULL COMMENT 'Danh sách mã HVSV được phân tách bởi dấu phẩy',
	`start_date`		DATE,
	`end_date`			DATE,
    PRIMARY KEY (`id`),
	UNIQUE(`code`),
	INDEX idx_classes_term (`term`),
	INDEX idx_classes_academicyear (`academicyear`)
) ENGINE=InnoDB  default charset = utf8mb4 COMMENT 'Một dạng Helper Table để thuận tiện cho việc lựa chọn lớp học phần khi khảo sát';

CREATE TABLE IF NOT EXISTS `#__survey_forms`(
	`id` 				INT AUTO_INCREMENT,
	`title` 			VARCHAR(255) NOT NULL		COMMENT 'Tiêu đề của phiếu khảo sát',
	`description` 		TEXT 						COMMENT 'Mô tả chi tiết về mẫu phiếu',
	`model` 			LONGTEXT NOT NULL			COMMENT 'SurveyJS model, lưu dưới dạng JSON',
	`published` 		BOOLEAN NOT NULL DEFAULT TRUE,
	`created` 			DATETIME NOT NULL			COMMENT 'Thời điểm tạo phiếu khảo sát',
	`created_by` 		INT NOT NULL 				COMMENT 'ID người tạo phiếu (liên kết đến #__users.id)',
	`modified` 			DATETIME 					COMMENT 'Thời điểm chỉnh sửa lần cuối',
	`modified_by` 		INT 						COMMENT 'ID người chỉnh sửa cuối cùng',
	`asset_id` 			INT NOT NULL DEFAULT 0		COMMENT 'ID bản ghi trong bảng #__assets để kiểm soát phân quyền. Bắt buộc phải có default value',
	`checked_out` 		INT DEFAULT 0 				COMMENT 'ID người đang chỉnh sửa bản ghi (nếu có)',
	`checked_out_time`	DATETIME					COMMENT 'Thời điểm bản ghi được check-out',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Mẫu phiếu khảo sát';
CREATE TABLE IF NOT EXISTS `#__survey_topics`(
	`id` 				INT AUTO_INCREMENT,
	`title` 			VARCHAR(255) NOT NULL					COMMENT 'Tiêu đề ngắn gọn',
	`description` 		TEXT 									COMMENT 'Mô tả chi tiết',
	`bg_color`			VARCHAR(7) NOT NULL DEFAULT '#3282F6'	COMMENT 'Màu nền để hiển thị trên View',
	`published` 		BOOLEAN NOT NULL DEFAULT TRUE,
	`created` 			DATETIME NOT NULL						COMMENT 'Thời điểm tạo phiếu khảo sát',
	`created_by` 		INT NOT NULL 							COMMENT 'ID người tạo phiếu (liên kết đến #__users.id)',
	`modified` 			DATETIME 								COMMENT 'Thời điểm chỉnh sửa lần cuối',
	`modified_by` 		INT 									COMMENT 'ID người chỉnh sửa cuối cùng',
	`checked_out` 		INT DEFAULT 0 							COMMENT 'ID người đang chỉnh sửa bản ghi (nếu có)',
	`checked_out_time`	DATETIME								COMMENT 'Thời điểm bản ghi được check-out',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Chủ đề khảo sát';
CREATE TABLE IF NOT EXISTS `#__survey_form_topic`(
    `form_id`			INT NOT NULL,
	`topic_id`		INT NOT NULL,
    PRIMARY KEY (`form_id`, `topic_id`),
	CONSTRAINT fk_survey_form_topic_form FOREIGN KEY(`form_id`)
		REFERENCES `#__survey_forms`(`id`)
		ON DELETE CASCADE,
	CONSTRAINT fk_survey_form_topic_topic FOREIGN KEY(`topic_id`)
		REFERENCES `#__survey_topics`(`id`)
		ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `#__survey_campaigns`(
	`id`					INT AUTO_INCREMENT,
	`title`					VARCHAR(255),
	`description` 			TEXT 								COMMENT 'Mô tả chi tiết',
	`form_id` 				INT NOT NULL						COMMENT 'Phiếu khảo sát mặc định cho các cuộc khảo sát',
	`start_time` 			DATETIME NOT NULL					COMMENT 'Thời điểm bắt đầu mặc định cho các cuộc khảo sát',
	`end_time` 				DATETIME NOT NULL					COMMENT 'Thời điểm kết thúc mặc định cho các cuộc khảo sát',
	`auth_mode` 			TINYINT(1) NOT NULL 				COMMENT 'Chế độ kiểm soát quyền phản hồi mặc định cho các cuộc khảo sát',
	`allow_edit_response` 	BOOLEAN NOT NULL DEFAULT FALSE		COMMENT 'Cho phép người được khảo sát xem, chỉnh sửa và gửi lại ý kiến',
	`strictly_anonymous` 	BOOLEAN NOT NULL DEFAULT FALSE		COMMENT 'Ẩn danh hoàn toàn. Không thể xác định được nội dung phản hồi của một người cụ thể',
	`state`					TINYINT(1) NOT NULL DEFAULT 1		COMMENT 'Published, Unpublished, Archived, Trashed',
	`asset_id` 				INT NOT NULL DEFAULT 0				COMMENT 'ID bản ghi trong bảng #__assets. Bắt buộc phải có default value',
	`created`				DATETIME NOT NULL					COMMENT 'Thời điểm tạo khảo sát',
	`created_by`			INT NOT NULL						COMMENT 'Người tạo khảo sát (liên kết #__users.id)',
	`modified`				DATETIME NOT NULL					COMMENT 'Thời điểm cập nhật gần nhất',
	`modified_by`			INT NOT NULL						COMMENT 'Người chỉnh sửa gần nhất',
	`checked_out` 		INT DEFAULT 0 							COMMENT 'ID người đang chỉnh sửa bản ghi (nếu có)',
	`checked_out_time`	DATETIME								COMMENT 'Thời điểm bản ghi được check-out',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Đợt khảo sát';

CREATE TABLE IF NOT EXISTS `#__survey_surveys`(
	`id` 					INT AUTO_INCREMENT,
	`title` 				VARCHAR(255) NOT NULL			COMMENT 'Tiêu đề của cuộc khảo sát',
	`description` 			TEXT 							COMMENT 'Mô tả hoặc hướng dẫn cho người tham gia',
	`form_id` 				INT NOT NULL					COMMENT 'Mã phiếu khảo sát sử dụng (liên kết #__survey_forms.id)',
	`campaign_id` 			INT DEFAULT NULL				COMMENT 'NULL if is a single survey, NOT NULL otherwise',
	`start_time` 			DATETIME NOT NULL				COMMENT 'Thời điểm bắt đầu khảo sát',
	`end_time` 				DATETIME NOT NULL				COMMENT 'Thời điểm kết thúc khảo sát',
	`auth_mode` 			TINYINT(1) NOT NULL 			COMMENT 'Chế độ kiểm soát quyền phản hồi',
	`allow_edit_response` 	BOOLEAN NOT NULL DEFAULT FALSE	COMMENT 'Cho phép người được khảo sát xem, chỉnh sửa và gửi lại ý kiến',
	`strictly_anonymous` 	BOOLEAN NOT NULL DEFAULT FALSE	COMMENT 'Ẩn danh hoàn toàn. Không thể xác định được nội dung phản hồi của một người cụ thể',
	`state`					TINYINT(1) NOT NULL DEFAULT 1	COMMENT 'Published, Unpublished, Archived, Trashed',
	`asset_id` 				INT NOT NULL DEFAULT 0			COMMENT 'ID bản ghi trong bảng #__assets. Bắt buộc phải có default value',
	`created`				DATETIME NOT NULL				COMMENT 'Thời điểm tạo khảo sát',
	`created_by`			INT NOT NULL					COMMENT 'Người tạo khảo sát (liên kết #__users.id)',
	`modified`				DATETIME NOT NULL				COMMENT 'Thời điểm cập nhật gần nhất',
	`modified_by`			INT NOT NULL					COMMENT 'Người chỉnh sửa gần nhất',
	`checked_out`			INT DEFAULT 0 					COMMENT 'Người đang chỉnh sửa (record locking)',
	`checked_out_time`		DATETIME						COMMENT 'Thời điểm check-out',
    PRIMARY KEY (`id`),
	CONSTRAINT fk_survey_surveys_form FOREIGN KEY(`form_id`)
		REFERENCES `#__survey_forms`(`id`)
		ON DELETE RESTRICT,
	CONSTRAINT fk_survey_surveys_campaign FOREIGN KEY(`campaign_id`)
		REFERENCES `#__survey_campaigns`(`id`)
		ON DELETE RESTRICT,
	INDEX idx_surveys_title(`title`)
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Cuộc khảo sát';

CREATE TABLE IF NOT EXISTS `#__survey_survey_respondent`(
	`id` 				INT AUTO_INCREMENT					COMMENT 'Để tiện cho việc xử lý trong View',
	`survey_id`			INT NOT NULL,
	`respondent_id`		INT NOT NULL,
	`responded`			BOOLEAN NOT NULL DEFAULT FALSE		COMMENT 'Đã phản hồi hay chưa',
	`response_id`		INT NULL							COMMENT 'NULL nếu cần ẩn danh hoàn toàn',
    PRIMARY KEY (`id`),
	CONSTRAINT fk_survey_survey_respondent_survey FOREIGN KEY(`survey_id`)
		REFERENCES `#__survey_surveys`(`id`)
		ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `#__survey_survey_respondent_archived`(
	`id` 				INT NOT NULL,
	`survey_id`			INT NOT NULL,
	`respondent_id`		INT NOT NULL,
	`responded`			BOOLEAN NOT NULL DEFAULT FALSE		COMMENT 'Đã phản hồi hay chưa',
	`response_id`		INT NULL							COMMENT 'NULL nếu cần ẩn danh hoàn toàn',
    PRIMARY KEY (`id`),
	CONSTRAINT fk_survey_survey_respondent_archived_survey FOREIGN KEY(`survey_id`)
		REFERENCES `#__survey_surveys`(`id`)
		ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `#__survey_responses`(
	`id` 				INT AUTO_INCREMENT,
	`survey_id`			INT NOT NULL,
	`data`				LONGTEXT NOT NULL								COMMENT 'Nội dung phản hồi (JSON) – chứa các câu trả lời',
	`received`			DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP		COMMENT 'Thời điểm gửi phản hồi',
	`submitted_by`		INT NULL										COMMENT 'Nếu phản hồi được ghi nhận bởi cán bộ khảo sát (nhập hộ), liên kết #__users.id',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB default charset = utf8mb4 COMMENT 'Ý kiến phản hồi';
CREATE TABLE IF NOT EXISTS `#__survey_responses_archived`(
	`id` 				INT NOT NULL,
	`survey_id`			INT NOT NULL,
	`data`				LONGTEXT NOT NULL		COMMENT 'Nội dung phản hồi (JSON) – chứa các câu trả lời',
	`received`			DATETIME NOT NULL		COMMENT 'Thời điểm gửi phản hồi',
	`submitted_by`		INT NULL				COMMENT 'Nếu phản hồi được ghi nhận bởi cán bộ khảo sát (nhập hộ), liên kết #__users.id',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB default charset = utf8mb4;
CREATE TABLE IF NOT EXISTS `#__survey_campaign_respondent`(
	`campaign_id` 				INT NOT NULL,
	`respondent_id`				INT NOT NULL,
	`survey_count`				INT NOT NULL		COMMENT 'Số lượng cuộc khảo sát của respondent_id trong campaign_id',
	`response_count`			INT NOT NULL		COMMENT 'Số lượng phản hồi đã gửi',
	INDEX idx_campaign_respondent_campaign(`campaign_id`),
	INDEX idx_campaign_respondent_respondent(`respondent_id`)
) ENGINE=InnoDB default charset = utf8mb4;
