<?php
/**
 * @package     Kma\Component\Eqa\Administrator\Enum
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Kma\Component\Eqa\Administrator\Enum;

use Kma\Library\Kma\Constant\Action as BaseAction;

/**
 * Định nghĩa các 'action' đặc thù nghiệp vụ của com_eqa, phục vụ việc ghi log
 * cho các tác vụ quản trị KHÔNG phải là CRUD chuẩn (những action CRUD chuẩn —
 * CREATE/EDIT/DELETE/PUBLISH/... — đã được định nghĩa và tự động ghi log sẵn
 * trong lớp cha {@see Action}).
 *
 * Lớp cha dành khoảng giá trị 0-1000 cho các action dùng chung. Do đó mọi
 * action định nghĩa ở đây PHẢI có giá trị > 1000.
 *
 * Các action được nhóm theo nghiệp vụ (không tạo action riêng cho từng
 * method/controller) theo phương án đã thống nhất tại tài liệu rà soát
 * logging ngày 2026-08-12.
 *
 * @since 1.0.4
 */
class Action extends BaseAction
{
	// ── Nhóm A: Bất thường & kỷ luật thi (Anomaly) ──────────────────────────
	const int DELAY          = 1001; // Hoãn thi
	const int UNDO_DELAY     = 1002; // Hủy hoãn thi
	const int APPLY_ANOMALY  = 1003; // Ghi nhận bất thường (vắng thi/trừ điểm/đình chỉ...)

	// ── Nhóm B: Khuyến khích (Stimulation) ──────────────────────────────────
	const int STIMULATE           = 1010; // Áp dụng khuyến khích
	const int UNDO_STIMULATE      = 1011; // Hủy khuyến khích
	const int CLEAR_STIMULATIONS  = 1012; // Xóa toàn bộ khuyến khích của một môn học

	// ── Nhóm C: Phúc tra (Regrading / Grade Correction) ─────────────────────
	const int ACCEPT_PPAA                  = 1020; // Chấp nhận yêu cầu phúc tra
	const int REJECT_PPAA                  = 1021; // Từ chối yêu cầu phúc tra
	const int CORRECT_GRADE                = 1022; // Sửa sai điểm (grade correction)
	const int ASSIGN_REGRADING_EXAMINERS   = 1023; // Phân công cán bộ chấm phúc khảo
	const int APPLY_REGRADING_EXAMINERS    = 1024; // Áp dụng phân công cán bộ chấm phúc khảo
	const int UPLOAD_REGRADING_RESULT      = 1025; // Nhập kết quả chấm phúc khảo
	const int ADD_REGRADING                = 1026; // Tạo yêu cầu phúc khảo

	// ── Nhóm D: Tài chính (lệ phí, công nợ) ──────────────────────────────────
	const int SET_PAYMENT_INFO          = 1030; // Thiết lập thông tin thu phí (sát hạch)
	const int SET_PAYMENT_STATUS        = 1031; // Thiết lập trạng thái đã thu phí (thi lại lần 2)
	const int SET_DEBT                  = 1032; // Ghi nợ/xóa nợ học phí cho thí sinh
	const int UPDATE_DEBT               = 1033; // Cập nhật hàng loạt trạng thái công nợ theo môn thi
	const int ADD_DEBTOR                = 1034; // Thêm người học vào danh sách nợ
	const int RESET_DEBT                = 1035; // Xóa trạng thái nợ
	// KHÔNG dùng cho tính năng mới: chức năng "Làm mới danh sách thi lại" đã bị bỏ
	// từ 2.1.8 (nó xóa mất cả thí sinh đã dự thi). Hằng được GIỮ LẠI để các bản ghi
	// log cũ vẫn tra cứu được nhãn hành động.
	const int REFRESH_RESIT    = 1036; // [Không còn dùng] Làm mới danh sách thí sinh của một đợt thi lại
	const int ADD_RESIT        = 1037; // Bổ sung thí sinh vào một đợt thi lại

	// ── Nhóm E: Sắp xếp phòng thi / ca thi ───────────────────────────────────
	const int DISTRIBUTE_ROOMS           = 1040; // Xếp phòng thi tự động
	const int CLEAR_ROOM_ASSIGNMENTS     = 1041; // Xóa việc xếp phòng thi
	const int ASSIGN_EXAMSESSION_STAFF   = 1042; // Phân công cán bộ coi/chấm thi cho ca thi
	const int SAVE_EXAMSESSION_BATCH     = 1043; // Tạo hàng loạt ca thi

	// ── Nhóm F: Danh sách người học / thí sinh ───────────────────────────────
	const int ADD_LEARNER      = 1050; // Thêm người học vào lớp/nhóm/khóa
	const int REMOVE_LEARNER   = 1051; // Xóa người học khỏi lớp/nhóm/khóa
	const int ADD_EXAMINEE     = 1052; // Thêm thí sinh vào môn thi/phòng thi
	const int REMOVE_EXAMINEE  = 1053; // Xóa thí sinh khỏi môn thi/phòng thi
	const int ALLOW_LEARNER    = 1054; // Cấp quyền dự thi
	const int DENY_LEARNER     = 1055; // Truất quyền dự thi

	// ── Nhóm G: Vòng đời kỳ thi / môn thi ─────────────────────────────────────
	const int COMPLETE_EXAMSEASON        = 1060; // Kết thúc kỳ thi
	const int UNDO_COMPLETE_EXAMSEASON   = 1061; // Mở lại kỳ thi đã kết thúc
	const int ADD_EXAM                   = 1062; // Thêm môn thi vào kỳ thi
	const int ADD_RESIT_EXAM            = 1063; // Thêm môn thi thi lại (lần 2)
	const int CONCLUDE_DISCIPLINE        = 1064; // Kết luận xử lý kỷ luật
	const int ENABLE_PPAA_REQ            = 1065; // Bật yêu cầu phúc tra
	const int DISABLE_PPAA_REQ           = 1066; // Tắt yêu cầu phúc tra

	// ── Nhóm H: Điểm quá trình / điểm chấm ────────────────────────────────────
	const int EDIT_PAM        = 1070; // Sửa điểm quá trình
	const int IMPORT_PAM      = 1071; // Nhập điểm quá trình từ file
	const int MASK_PAPER      = 1072; // Đánh phách bài thi giấy
	const int SAVE_EXAMINERS  = 1073; // Phân công cán bộ chấm thi giấy
	const int UPLOAD_MARK     = 1074; // Nhập điểm từ phiếu chấm (mask)

	// ── Nhóm I: Nhập liệu hàng loạt (Import) ──────────────────────────────────
	// Tách riêng theo LOẠI DỮ LIỆU được import (không dùng 1 action dùng chung),
	// để khi tra log có thể lọc/thống kê theo đúng loại nghiệp vụ bị ảnh hưởng.
	const int IMPORT_LEARNERS         = 1080; // Nhập danh sách người học (vào lớp, hoặc toàn hệ thống)
	const int IMPORT_STATEMENT        = 1081; // Nhập bảng kê (statement) xác nhận thanh toán/kết quả — dùng chung cho phúc khảo, thi lại lần 2, sát hạch
	const int IMPORT_SUBJECTS         = 1082; // Nhập danh mục môn học
	const int IMPORT_EXAMROOM_DATA    = 1083; // Nhập danh sách phòng thi / thí sinh theo phòng
	const int IMPORT_CONDUCTS         = 1084; // Nhập kết quả rèn luyện (conduct)
	const int IMPORT_MM_PRODUCTIONS   = 1085; // Nhập sản lượng chấm thi trên máy (machine marking)
	const int IMPORT_ITEST_RESULT     = 1086; // Nhập kết quả thi trắc nghiệm/sát hạch từ hệ thống iTest
	const int INIT_EMPLOYEE_ACCOUNTS  = 1087; // Khởi tạo tài khoản #__users cho employee (FixerController, chỉ chạy ở dev)
	const int IMPORT_CLASSES          = 1088; // Tạo hàng loạt lớp học phần + nhập danh sách người học từ Excel (ClassesController::import)

	// ── Nhóm K: Danh sách thi lần 2 ───────────────────────────────────────────
	// Việc tạo/sửa/xóa danh sách dùng các action CRUD chuẩn của lớp cha; ở đây
	// chỉ định nghĩa action đặc thù nghiệp vụ.
	const int ACTIVATE_RESIT = 1090; // Kích hoạt một đợt thi lại (mỗi cơ sở đào tạo có tối đa một đợt kích hoạt)
}
