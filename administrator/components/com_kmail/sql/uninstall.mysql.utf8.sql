-- Xóa bảng khi uninstall com_kmail
-- Thứ tự: xóa bảng con trước (có FK), rồi bảng cha sau

DROP TABLE IF EXISTS `#__kmail_queue`;
DROP TABLE IF EXISTS `#__kmail_campaigns`;
DROP TABLE IF EXISTS `#__kmail_templates`;
DROP TABLE IF EXISTS `#__kmail_logs`;
