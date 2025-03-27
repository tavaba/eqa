ALTER TABLE `#__eqa_examseasons` ADD `ppaa_req_enabled` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Được gửi yêu cầu phúc khảo' AFTER `finish`;
ALTER TABLE `#__eqa_examseasons` ADD `ppaa_req_deadline` DATETIME NULL COMMENT 'Thời hạn gửi yêu cầu phúc khảo' AFTER `ppaa_req_enabled`;
