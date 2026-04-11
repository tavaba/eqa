<?php
namespace Kma\Component\Eqa\Administrator\Enum;

use Kma\Library\Kma\Enum\EnumHelper;

enum ExamStatus: int
{
	use EnumHelper;

	// Nhóm 1: Chuẩn bị (Preparation)
	case Unknown = 0;                       // Chưa xác định
	case QuestionPendingPam = 10;           // Đã có đề, thiếu điểm quá trình (PAM: Process Assessment Mark)
	case PamPendingQuestion = 11;           // Đã có điểm quá trình, thiếu đề
	case QuestionAndPamReady = 12;          // Đã đủ đề và điểm quá trình

	// Nhóm 2: Tổ chức thi (Examination)
	case ReadyToExam = 20;                  // Đã chia phòng thi, sẵn sàng để thi
	case ExamConducted = 21;                // Đã tổ chức thi
	case PaperInfoPartial = 22;             // Đang nhập biên bản thi (một phần)
	case PaperInfoFull = 23;                // Hoàn thành nhập biên bản thi
	case MaskingDone = 25;                  // Đã làm phách, dồn túi
	case ExaminerAssigned = 26;             // Đã phân công cán bộ chấm thi
	case AnomalyInputted = 30;              // Đã nhập thông tin bất thường (Kỷ luật, vắng, hoãn...)

	// Nhóm 3: Chấm thi & Hoàn tất (Marking & Completion)
	case MarkingStarted = 50;               // Đã bắt đầu chấm thi
	case MarkPartial = 51;                  // Đã có một phần điểm
	case MarkFull = 52;                     // Đã có đủ điểm thi
	case AllConcluded=60;                   // Tất cả đã có kết luận (Đỗ, rớt, ...)
	case Completed = 100;                   // Đã hoàn tất toàn bộ quy trình

	/*
	 * Lấy mô tả tiếng Việt tương ứng cho giao diện
	 */
	public function getLabel(): string {
		return match($this) {
			self::Unknown => 'Chưa xác định',
			self::QuestionPendingPam => 'Đã có đề thi, thiếu điểm quá trình',
			self::PamPendingQuestion => 'Đã có điểm quá trình, thiếu đề thi',
			self::QuestionAndPamReady => 'Đã có đề và điểm quá trình',
			self::ReadyToExam => 'Đã chia phòng thi, sẵn sàng để thi',
			self::ExamConducted => 'Đã tổ chức thi',
			self::PaperInfoPartial => 'Đang nhập biên bản thi',
			self::PaperInfoFull => 'Đã hoàn thành biên bản thi',
			self::MaskingDone => 'Đã làm phách, dồn túi',
			self::ExaminerAssigned => 'Đã phân công chấm thi',
			self::AnomalyInputted => 'Đã nhập thông tin bất thường',
			self::MarkingStarted => 'Đã bắt đầu chấm thi',
			self::MarkPartial => 'Đã có một phần điểm',
			self::MarkFull => 'Đã có đủ điểm',
			self::AllConcluded => 'Tất cả đã có kết luận',
			self::Completed => 'Đã hoàn tất',
		};
	}
}
