<?php

namespace Kma\Component\Eqa\Administrator\Service;

defined('_JEXEC') or die();

use Joomla\CMS\Factory;

class CreditClassNameParser
{
	protected string $subject;
	protected string $term;
	protected string $year;
	protected string $subProgram;
	protected string $coursegroup;
	protected string $order;
	protected bool $isPrimaryClass;

	/*
	 * Có 3 dạng PATTERN cơ bản
	 * 1) Lớp chính
	 * 2) Lớp con
	 * 3) Lớp thuần túy thực hành
	 * Mỗi PATTERN gồm 6 groups
	 * - Group1: Tên môn học
	 * - Group2: Học kỳ
	 * - Group3: Năm (năm đầu của năm học)
	 * - Group4: Nhóm khóa học
	 * - Group5(*): Phân ngành
	 * - Group6: Số hiệu lớp
	 * Ví dụ:
	 * An toàn hệ thống nhúng-2-23 (DT4-HTN-01)
	 * Giáo dục thể chất 4-2-23- bóng bàn (C7D6-.01)
	 */

	protected const PATTERN1 = '/^([\s\S]+)-([1-3])-([0-9]{2})-?[\p{L}\s]*\(([A-Z0-9]+)-?([\p{L}\s]*)-?([0-9]{2})\)$/u';
	protected const PATTERN2 = '/^([\s\S]+)-([1-3])-([0-9]{2})-?[\p{L}\s]*\(([A-Z0-9]+)-?([\p{L}\s]*)-?([0-9]{2})\.[0-9]{1,2}\)$/u';
	protected const PATTERN3 = '/^([\s\S]+)-([1-3])-([0-9]{2})-?[\p{L}\s]*\(([A-Z0-9]+)-?([\p{L}\s]*)\.([0-9]{1,2})\)$/u';

	/*
	 * Ngoài ra có PATTERN thứ 4 dành riêng cho môn Giáo dục thể chất 5
	 * Ví dụ:   Giáo dục thể chất 5-1-24 (C7D601-bóng bàn)
	 * -Groups 1-4: như trên
	 * -Group5: số hiệu lớp
	 * -Group6: phân môn
	 */
	protected const PATTERN4 = '/^([\s\S]+)-([1-3])-([0-9]{2})\s*\(([A-Z0-9]+)\.?([0-9]{2})-([\p{L}\s0-9]*)\)$/u';

	/*
	 * Và có PARTTERN5 dành cho các lớp học lại
	 * Ví dụ: Phân tích, thiết kế hệ thống thông tin-1-24 (học lại01)
	 * - Groups 1-3: như trên
	 * - Group 4: số hiệu lớp
	 */
	protected const PATTERN5 = '/^([\s\S]+)-([1-3])-([0-9]{2})\s*\(học lại([0-9]{2})\)$/u';

	public function parse(string $name):bool
	{
		//init
		$this->isPrimaryClass = true;

		//Try PATTERN1 (Lớp Lý thuyết)
		$matched = preg_match(self::PATTERN1, $name, $matches);

		//Try PATTERN2 (Lớp con thực hành)
		if(!$matched) {
			$matched = preg_match(self::PATTERN2, $name, $matches);
			if($matched)
				$this->isPrimaryClass = false;
		}

		//Try PATTERN3 (Lớp thuần túy thực hành)
		if(!$matched){
			$matched = preg_match(self::PATTERN3, $name, $matches);
		}

		if($matched)
		{
			$this->subject = $matches[1];
			$this->term = $matches[2];
			$this->year = $matches[3];
			$this->coursegroup = $matches[4];
			$this->order = $matches[6];

			$s = trim($matches[5]);
			$this->subProgram = match ($s){
				'An toàn', 'AT hệ thống TT' => 'AT',
				'Công nghệ' => 'CN',
				'kỹ nghệ', 'Kỹ nghệ ATM' => 'KN',
				'HTN' => 'HTN',
				default => ''
			};
			return true;
		}


		//Kiểm tra pattern4
		$matched = preg_match(self::PATTERN4, $name, $matches);
		if($matched)
		{
			$this->subject = $matches[1];
			$this->term = $matches[2];
			$this->year = $matches[3];
			$this->coursegroup = $matches[4];
			$this->order = $matches[5];
			$this->subProgram='';
			return true;
		}

		//Kiểm tra pattern4
		$matched = preg_match(self::PATTERN5, $name, $matches);
		if($matched)
		{
			$this->subject = $matches[1];
			$this->term = $matches[2];
			$this->year = $matches[3];
			$this->coursegroup = 'ANY';
			$this->order = $matches[4];
			$this->subProgram='';
			return true;
		}

		return false;
	}
	public function getTerm(): int
	{
		return (int)$this->term;
	}
	public function getAcademicYearCode(): int
	{
		$year = (int)$this->year;
		return $year>=2000 ? $year : 2000 + $year;
	}

	public function getClassCodeTail(): string
	{
		$tail = $this->term . '-' . $this->year;
		$tail .= '(' . $this->coursegroup . '-' . $this->subProgram . $this->order . ')';
		return $tail;
	}
	public function getCourseGroup(): string
	{
		return $this->coursegroup;
	}
	public function isPrimaryClass():bool
	{
		return $this->isPrimaryClass;
	}
}
