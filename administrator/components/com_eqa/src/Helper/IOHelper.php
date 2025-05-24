<?php
namespace Kma\Component\Eqa\Administrator\Helper;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use Exception;
use JComponentHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Kma\Component\Eqa\Administrator\Interface\ExamInfo;
use Kma\Component\Eqa\Administrator\Interface\ExamroomInfo;
use Kma\Component\Eqa\Administrator\Interface\GradeCorrectionInfo;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use phpseclib3\Exception\BadConfigurationException;

abstract class IOHelper
{
	protected const INCH = 73;
	protected const PAGE_WIDTH_A4 = 8.267;  //inch
	static public function loadSpreadsheet(string $fileName): Spreadsheet
	{
		try {
			if (str_ends_with($fileName, '.xls')) {
				$reader = new Xls();
			} else {
				// Assume it's an Excel 2007 or later (.xlsx)
				$reader = IOFactory::createReader('Xlsx');
			}
			$spreadsheet = $reader->load($fileName);
		}
		catch (Exception $e){
			$shortName = pathinfo($fileName, PATHINFO_FILENAME);
			$msg = Text::sprintf('Loading &quot;%s&qout; faied: %s', $shortName, $e->getMessage());
			throw new Exception($msg);
		}
		return $spreadsheet;
	}
	static public function sendHttpXlsx(Spreadsheet $spreadsheet, string $fileName, bool $includeCharts=false)
	{
		// Clean output buffer to prevent corruption
		if (ob_get_length()) {
			ob_clean();
		}
		flush();

		//Sanitze the file name
		$fileName = preg_replace('/[\\\\\/:*?"<>|]/u', '_', $fileName);
		$fileName = trim($fileName);
		$fileName = mb_substr($fileName, 0, 255); // Most OS allow 255-char filenames

		// Force download of the Excel file
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="' . basename($fileName) . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
		header('Cache-Control: max-age=0');
		header('Expires: 0');

		$writer = new Xlsx($spreadsheet);
		if($includeCharts)
			$writer->setIncludeCharts(true);
		$writer->save('php://output');
	}
	static public function writeExamroomExaminees(Worksheet $sheet, ExamroomInfo $examroom, $examinees ): void
	{
		$COLS = 10;

		// Set page margins (values are in inches)
		$sheet->getPageMargins()->setTop(0.5);
		$sheet->getPageMargins()->setBottom(0.75);
		$sheet->getPageMargins()->setLeft(0.45);
		$sheet->getPageMargins()->setRight(0.45);
		$sheet->getPageMargins()->setHeader(0.3);
		$sheet->getPageMargins()->setFooter(0.3);

		//Set column width
		$widths = [5, 5, 12, 20, 8, 7, 7, 8, 8, 15];
		for ($col=1; $col<=$COLS; $col++)
		{
			$columnLetter = Coordinate::stringFromColumnIndex($col);
			$sheet->getColumnDimension($columnLetter)->setWidth($widths[$col-1]);
		}

		//Create information rows - Part 1
		$row=1;
		$sheet->mergeCells([1,$row, 4, $row]);
		$sheet->setCellValue([1, $row],'HỌC VIỆN KỸ THUẬT MẬT MÃ');

		$sheet->mergeCells([1,$row+1, 4, $row+1]);
		$sheet->setCellValue([1, $row+1],'PHÒNG KT&ĐBCLĐT');
		$sheet->getStyle([1, $row+1, 4, $row+1])->getFont()->setBold(true);

		$sheet->mergeCells([5, $row, $COLS, $row]);
		$sheet->setCellValue([5, $row],'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM');

		$sheet->mergeCells([5, $row+1, $COLS, $row+1]);
		$sheet->setCellValue([5, $row+1],'Độc lập - Tự do - Hạnh phúc');
		$sheet->getStyle([5, $row, $COLS, $row+2])->getFont()->setBold(true);
		$sheet->getStyle([1, $row+1, $COLS, $row+1])->getFont()->setUnderline(Font::UNDERLINE_SINGLE);

		$row += 3;
		$sheet->mergeCells([1, $row, $COLS, $row]);
		$sheet->setCellValue([1,$row],'DANH SÁCH THÍ SINH DỰ THI');
		$sheet->getStyle([1, $row, $COLS, $row])->getFont()->setBold(true);
		$row++;
		$sheet->mergeCells([1, $row, $COLS, $row]);
		$value = 'Năm học ' . $examroom->academicyear . '.  Học kỳ ' . $examroom->term;
		$sheet->setCellValue([1, $row],$value);
		$sheet->getStyle([1, $row, $COLS, $row])->getFont()->setBold(true);

		$sheet->getStyle([1, 1, $COLS, $row])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$sheet->getStyle([1, 1, $COLS, $row])->getFont()->setSize(12);

		//Create information rows - Part 2
		$fontSize=12;
		$row++;
		$value = new RichText();
		$part = $value->createTextRun('Môn thi: ');
		$part->getFont()->setSize($fontSize)->setName('Times New Roman');
		$part = $value->createTextRun(implode(', ', $examroom->exams));
		$part->getFont()->setBold(true)->setSize($fontSize)->setName('Times New Roman');
		$sheet->mergeCells([1, $row, $COLS, $row]);
		$sheet->setCellValue([1,$row],$value);

		$row++;
		$value = 'Lần thi: ';
		$value .= DatabaseHelper::getExamroomAttempt($examroom->id);
		$sheet->setCellValue([1, $row], $value);
		$sheet->mergeCells([1, $row, 3, $row]);

		$value = 'Hình thức thi: ';
		$value .= ExamHelper::getTestType($examroom->testtype);
		$sheet->setCellValue([4, $row], $value);
		$sheet->mergeCells([4, $row, 6, $row]);

		$value = 'Thời gian làm bài: ';
		$value .= $examroom->testDuration . ' (phút)';
		$sheet->setCellValue([7, $row], $value);
		$sheet->mergeCells([7, $row, $COLS, $row]);
		$sheet->getStyle([1, $row, $COLS, $row])->getFont()->setSize($fontSize);

		$value = new RichText();
		$part = $value->createTextRun('Ngày thi: ');
		$part->getFont()->setSize($fontSize)->setName('Times New Roman');
		$part = $value->createTextRun(DatetimeHelper::getFullDate($examroom->examTime));
		$part->getFont()->setBold(true)->setSize($fontSize)->setName('Times New Roman')->setColor(new Color('FFFF0000'));
		$part = $value->createTextRun('   Giờ thi: ');
		$part->getFont()->setSize($fontSize)->setName('Times New Roman');
		$part = $value->createTextRun(DatetimeHelper::getHourAndMinute($examroom->examTime));
		$part->getFont()->setBold(true)->setSize($fontSize)->setName('Times New Roman')->setColor(new Color('FFFF0000'));
		$part = $value->createTextRun('    Phòng thi: ');
		$part->getFont()->setSize($fontSize)->setName('Times New Roman');
		$part = $value->createTextRun($examroom->name);
		$part->getFont()->setBold(true)->setSize($fontSize)->setName('Times New Roman')->setColor(new Color('FFFF0000'));
		$part = $value->createTextRun('   Mã phòng thi: ' . $examroom->id);
		$part->getFont()->setSize($fontSize)->setName('Times New Roman');
		$sheet->mergeCells('A9:J9');
		$sheet->setCellValue('A9',$value);

		$value = 'Tổng số thí sinh: ' . $examroom->examineeCount;
		$value .= '    Có mặt:......   Vắng: ......    Có lý do: ......    Không lý do: .......';
		$sheet->getStyle('A10')->getFont()->setSize($fontSize);
		$sheet->mergeCells('A10:J10');
		$sheet->setCellValue('A10', $value);

		//Create table heading row
		$headers = [
			'STT', 'SBD', 'Mã HVSV', 'Họ đệm', 'Tên', 'Lớp', 'Mã đề',
			$examroom->testtype == ExamHelper::TEST_TYPE_PAPER ? 'Số tờ' : 'Điểm',
			'Ký tên', 'Ghi chú'
		];
		$sheet->setCellValue('A12', 'STT');
		$sheet->setCellValue('B12', 'SBD');
		$sheet->setCellValue('C12', 'Mã HVSV');
		$sheet->setCellValue('D12', 'Họ đệm');
		$sheet->setCellValue('E12', 'Tên');
		$sheet->setCellValue('F12', 'Lớp');
		$sheet->setCellValue('G12', 'Mã đề');
		if($examroom->testtype == ExamHelper::TEST_TYPE_PAPER)
			$sheet->setCellValue('H12', 'Số tờ');
		else
			$sheet->setCellValue('H12', 'Điểm');
		$sheet->setCellValue('I12', 'Ký tên');
		$sheet->setCellValue('J12', 'Ghi chú');
		$sheet->getStyle('A12:J12')->getFont()->setBold(true);
		$sheet->getStyle('A12:J12')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Create learners
		$seq=1;
		$row=13;
		foreach ($examinees as $examinee){
			$sheet->setCellValue('A'.$row, $seq);
			$sheet->setCellValue('B'.$row, $examinee->code);
			$sheet->setCellValue('C'.$row, $examinee->learner_code);
			$sheet->setCellValue('D'.$row, $examinee->lastname);
			$sheet->setCellValue('E'.$row, $examinee->firstname);
			$sheet->setCellValue('F'.$row, $examinee->group);
			if(!$examinee->allowed)
				$sheet->setCellValue('J'.$row, 'Cấm thi');
			$row++;
			$seq++;
		}

		//Format the table
		$lastTalbeRow = 12+sizeof($examinees);
		$sheet->getStyle('A12:J'.$lastTalbeRow)->getFont()->setSize(12);
		$sheet->getStyle('A12:C'.$lastTalbeRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$sheet->getStyle('F12:J'.$lastTalbeRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$sheet->getStyle('A12:J'.$lastTalbeRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);


		//Create the ending rows
		if($examroom->testtype == ExamHelper::TEST_TYPE_PAPER)
		{
			$row++;
			$sheet->mergeCells('A' . $row . ':D' . $row);
			$sheet->setCellValue('A' . $row, 'Tổng số bài thi: .............');
			$sheet->mergeCells('E' . $row . ':J' . $row);
			$sheet->setCellValue('E' . $row, 'Tổng số tờ giấy thi: .............');
		}

		$row ++;
		$sheet->mergeCells('A'.$row.':J'.$row);
		$sheet->setCellValue('A'.$row,'Hà Nội, ngày ..... tháng ..... năm 20....');
		$sheet->getStyle('A'.$row)->getFont()->setItalic(true);
		$sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

		$row++;
		switch ($examroom->testtype){
			case ExamHelper::TEST_TYPE_PROJECT:
			case ExamHelper::TEST_TYPE_THESIS:
			case ExamHelper::TEST_TYPE_PRACTICE:
			case ExamHelper::TEST_TYPE_COMBO_OBJECTIVE_PRACTICE:
			case ExamHelper::TEST_TYPE_DIALOG:
				$signer1 = 'CBCTChT thứ nhất';
				$signer2 = 'CBCTChT thứ hai';
				break;
			default:
				$signer1 = 'CBCT thứ nhất';
				$signer2 = 'CBCT thứ hai';
		}
		$sheet->mergeCells('A'.$row.':C'.$row);
		$sheet->setCellValue('A'.$row, $signer1);
		$sheet->mergeCells('D'.$row.':F'.$row);
		$sheet->setCellValue('D'.$row, $signer2);
		$sheet->mergeCells('G'.$row.':J'.$row);
		$sheet->setCellValue('G'.$row, 'Đại diện Phòng KT&ĐBCLĐT');
		$sheet->getStyle('A'.$row.':J'.$row)->getFont()->setBold(true);
		$sheet->getStyle('A'.$row.':J'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);


		//Change font for all the sheet
		$sheet->getStyle($sheet->calculateWorksheetDimension())->getFont()->setName('Times New Roman');
	}
	static public function writeExamExaminees(Worksheet $sheet, $exam, $examinees) : void
	{
		//Lấy tham số cấu hình của component
		$organizationName = ConfigHelper::getOrganization();
		$examinationUnitName = ConfigHelper::getExaminationUnit();

		// Set page margins (values are in inches)
		$sheet->getPageMargins()->setTop(0.5);
		$sheet->getPageMargins()->setBottom(0.75);
		$sheet->getPageMargins()->setLeft(0.45);
		$sheet->getPageMargins()->setRight(0.45);
		$sheet->getPageMargins()->setHeader(0.3);
		$sheet->getPageMargins()->setFooter(0.3);

		//Set column width
		$sheet->getColumnDimension('A')->setWidth(5);
		$sheet->getColumnDimension('B')->setWidth(5);
		$sheet->getColumnDimension('C')->setWidth(12);
		$sheet->getColumnDimension('D')->setWidth(20);
		$sheet->getColumnDimension('E')->setWidth(8);
		$sheet->getColumnDimension('F')->setWidth(7);
		$sheet->getColumnDimension('G')->setWidth(7);
		$sheet->getColumnDimension('H')->setWidth(8);
		$sheet->getColumnDimension('I')->setWidth(8);
		$sheet->getColumnDimension('J')->setWidth(12);
		$sheet->getColumnDimension('K')->setWidth(10);
		$sheet->getColumnDimension('L')->setWidth(10);
		$sheet->getColumnDimension('M')->setWidth(15);

		//Create information rows - Part 1
		$sheet->mergeCells('A1:D1');
		$sheet->setCellValue('A1', mb_strtoupper($organizationName));

		$sheet->mergeCells('A2:D2');
		$sheet->setCellValue('A2', mb_strtoupper($examinationUnitName));
		$sheet->getStyle('A2')->getFont()->setBold(true);

		$sheet->mergeCells('H1:M1');
		$sheet->setCellValue('H1','CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM');

		$sheet->mergeCells('H2:M2');
		$sheet->setCellValue('H2','Độc lập - Tự do - Hạnh phúc');
		$sheet->getStyle('H1:M2')->getFont()->setBold(true);
		$sheet->getStyle('A2:M2')->getFont()->setUnderline(Font::UNDERLINE_SINGLE);

		$sheet->mergeCells('A4:M4');
		$sheet->setCellValue('A4','DANH SÁCH THÍ SINH');
		$sheet->mergeCells('A5:M5');
		$value = 'Năm học ' . $exam->academicyear . '.  Học kỳ ' . $exam->term;
		$sheet->setCellValue('A5',$value);
		$sheet->getStyle('A4:M5')->getFont()->setBold(true);

		$sheet->getStyle('A1:M6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$sheet->getStyle('A1:M6')->getFont()->setSize(12);

		//Create information rows - Part 2
		$fontSize=12;
		$value = new RichText();
		$part = $value->createTextRun('Môn thi: ');
		$part->getFont()->setSize($fontSize)->setName('Times New Roman');
		$part = $value->createTextRun($exam->name);
		$part->getFont()->setBold(true)->setSize($fontSize)->setName('Times New Roman');
		$sheet->mergeCells('A7:J7');
		$sheet->setCellValue('A7',$value);

		$sheet->mergeCells('A8:D8');
		$value = 'Hình thức thi: ';
		$value .= ExamHelper::getTestType($exam->testtype);
		$sheet->setCellValue('A8', $value);
		$sheet->mergeCells('E8:J8');
		$value = 'Thời gian làm bài: ';
		$value .= $exam->duration . ' (phút)';
		$sheet->setCellValue('E8', $value);
		$sheet->getStyle('A8:J8')->getFont()->setSize($fontSize);

		$value = 'Tổng số thí sinh: ' . $exam->countTotal;
		$sheet->getStyle('A10')->getFont()->setSize($fontSize);
		$sheet->mergeCells('A10:J10');
		$sheet->setCellValue('A10', $value);

		//Create table heading row
		$sheet->setCellValue('A12', 'STT');
		$sheet->setCellValue('B12', 'SBD');
		$sheet->setCellValue('C12', 'Mã HVSV');
		$sheet->setCellValue('D12', 'Họ đệm');
		$sheet->setCellValue('E12', 'Tên');
		$sheet->setCellValue('F12', 'TP1');
		$sheet->setCellValue('G12', 'TP2');
		$sheet->setCellValue('H12', 'ĐQT');
		$sheet->setCellValue('I12', 'Lần thi');
		$sheet->setCellValue('J12', 'Ngày thi');
		$sheet->setCellValue('K12', 'Ca thi');
		$sheet->setCellValue('L12', 'Phòng');
		$sheet->setCellValue('M12', 'Ghi chú');
		$sheet->getStyle('A12:M12')->getFont()->setBold(true);
		$sheet->getStyle('A12:M12')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Create learners
		$seq=1;
		$row=13;
		foreach ($examinees as $examinee){
			$sheet->setCellValue('A'.$row, $seq);
			$sheet->setCellValue('B'.$row, $examinee->code);
			$sheet->setCellValue('C'.$row, $examinee->learner_code);
			$sheet->setCellValue('D'.$row, $examinee->lastname);
			$sheet->setCellValue('E'.$row, $examinee->firstname);
			$sheet->setCellValue('F'.$row, $examinee->pam1);
			$sheet->setCellValue('G'.$row, $examinee->pam2);
			$sheet->setCellValue('H'.$row, $examinee->pam);
			$sheet->setCellValue('I'.$row, $examinee->attempt);
			if(!empty($examinee->examstart))
				$sheet->setCellValue('J'.$row, DatetimeHelper::getFullDate($examinee->examstart));
			$sheet->setCellValue('K'.$row, $examinee->examsession);
			$sheet->setCellValue('L'.$row, $examinee->examroom);
			if(!$examinee->allowed && $examinee->debtor)
				$sheet->setCellValue('M'.$row, 'Cấm thi; Nợ HP');
			elseif(!$examinee->allowed)
				$sheet->setCellValue('M'.$row, 'Cấm thi');
			elseif($examinee->debtor)
				$sheet->setCellValue('M'.$row, 'Nợ HP');
			$row++;
			$seq++;
		}

		//Format the table
		$lastTalbeRow = 12+sizeof($examinees);
		$sheet->getStyle('A12:M'.$lastTalbeRow)->getFont()->setSize(12);
		$sheet->getStyle('A12:C'.$lastTalbeRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$sheet->getStyle('F12:M'.$lastTalbeRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$sheet->getStyle('A12:M'.$lastTalbeRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);


		//Create the ending rows
		$row += 2;
		$sheet->mergeCells('J'.$row.':M'.$row);
		$sheet->setCellValue('J'.$row,DatetimeHelper::getSigningDateString());
		$sheet->getStyle('J'.$row)->getFont()->setItalic(true);
		$sheet->getStyle('J'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		$row++;
		$sheet->mergeCells('J'.$row.':M'.$row);
		$sheet->setCellValue('J'.$row, mb_strtoupper($examinationUnitName));
		$sheet->getStyle('J'.$row)->getFont()->setBold(true);
		$sheet->getStyle('J'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);


		//Change font for all the sheet
		$sheet->getStyle($sheet->calculateWorksheetDimension())->getFont()->setName('Times New Roman');
	}

	static public function writeExamResultForLearners(Worksheet $sheet, ExamInfo $examInfo, array $examResult)
	{
		// Set page margins (values are in inches)
		$sheet->getPageMargins()->setTop(0.5);
		$sheet->getPageMargins()->setBottom(0.5);
		$sheet->getPageMargins()->setLeft(0.25);
		$sheet->getPageMargins()->setRight(0.25);
		$sheet->getPageMargins()->setHeader(0.3);
		$sheet->getPageMargins()->setFooter(0.3);

		$headers = ['STT', 'SBD', 'Mã HVSV', 'Họ đệm', 'Tên', 'Lớp', 'TP1', 'TP2', 'THI', 'HP', 'Chữ', 'Ghi chú'];
		$widths =  [6,      6,     11,         18,       8,     8,     6,     6,    6,      6,     6,    14];
		$COLS = sizeof($headers);

		for($i=1; $i<=$COLS; $i++)
		{
			$columnLetter = Coordinate::stringFromColumnIndex($i);
			$sheet->getColumnDimension($columnLetter)->setWidth($widths[$i-1]);
		}

		//Init
		$row=0;

		//Thông tin cơ quan
		$row++;
		$midCol = 4;
		$organizationName = mb_strtoupper(ConfigHelper::getOrganization());
		$sheet->setCellValue([1,$row], $organizationName);
		$sheet->mergeCells([1,$row, $midCol, $row]);
		$sheet->setCellValue([$midCol+2, $row], 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM');
		$sheet->mergeCells([$midCol+2, $row, $COLS, $row]);

		$cellStyle = $sheet->getStyle([1,$row, $COLS, $row]);
		$cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		$row++;
		$unitName = mb_strtoupper(ConfigHelper::getExaminationUnit());
		$sheet->setCellValue([1, $row], $unitName);
		$sheet->mergeCells([1,$row, $midCol, $row]);
		$sheet->setCellValue([$midCol+2, $row], 'Độc lập - Tự do - Hạnh phúc');
		$sheet->mergeCells([$midCol+2,$row, $COLS, $row]);

		$cellStyle = $sheet->getStyle([1,$row, $COLS, $row]);
		$cellStyle->getFont()->setBold(true);
		$cellStyle->getFont()->setUnderline(true);
		$cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Title và Subtitle
		$row  += 2;
		$subTitle = Text::sprintf('HỌC KỲ %d - NĂM HỌC %s', $examInfo->term, $examInfo->academicyear);
		$sheet->setCellValue([1, $row], "KẾT QUẢ ĐÁNH GIÁ HỌC PHẦN");
		$sheet->mergeCells([1,$row, $COLS, $row]);
		$sheet->setCellValue([1, $row+1], $subTitle);
		$sheet->mergeCells([1,$row+1, $COLS, $row+1]);
		$style = $sheet->getStyle([1, $row, $COLS, $row+1]);
		$style->getFont()->setBold(true);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Thông tin môn thi
		$row += 3;
		$sheet->setCellValue([1, $row], 'Môn thi:');
		$sheet->setCellValue([3, $row], $examInfo->name);

		//Dòng heading
		$row++;
		$headingRow = $row;
		foreach ($headers as $index=>$header)
		{
			$sheet->setCellValue([$index+1, $headingRow], $header);
		}
		$style = $sheet->getStyle([1,$headingRow, $COLS, $headingRow]);
		$style->getFont()->setBold(true);

		//Các dòng dữ liệu
		$seq=0;
		foreach ($examResult as $item)
		{
			$seq++;
			$row++;
			$data = [
				$seq,
				$item->code,
				$item->learner_code,
				$item->lastname,
				$item->firstname,
				$item->group,
				$item->stimulation_type==StimulationHelper::TYPE_TRANS ? $item->module_mark : ExamHelper::markToText($item->pam1),
				$item->stimulation_type==StimulationHelper::TYPE_TRANS ? $item->module_mark : ExamHelper::markToText($item->pam2),
				($item->anomaly==ExamHelper::EXAM_ANOMALY_DELAY || $item->anomaly==ExamHelper::EXAM_ANOMALY_ABSENT) ? 'K' : $item->mark_final,
				$item->module_mark,
				$item->module_grade,
				(!empty($item->description)) ? explode(';', $item->description)[0] : null
			];
			foreach ($data as $index=>$value)
			{
				$sheet->setCellValue([$index+1, $row], $value);
			}
		}

		//Kẻ bảng và căn lề
		$style = $sheet->getStyle([1, $headingRow, $COLS, $row]);
		$style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
		$style = $sheet->getStyle([1, $headingRow, 3, $row]);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$style = $sheet->getStyle([6, $headingRow, $COLS-1, $row]);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Phông chữ
		$style = $sheet->getStyle($sheet->calculateWorksheetDimension());
		$style->getFont()->setName('Times New Roman');
		$style = $sheet->getStyle([9, $headingRow+1, 9, $row]);
		$style->getFont()->setBold(true);

	}
	static public function writeExamResultForEms(Worksheet $sheet, ExamInfo $examInfo, array $examResult)
	{
		// Set page margins (values are in inches)
		$sheet->getPageMargins()->setTop(0.5);
		$sheet->getPageMargins()->setBottom(0.5);
		$sheet->getPageMargins()->setLeft(0.25);
		$sheet->getPageMargins()->setRight(0.25);
		$sheet->getPageMargins()->setHeader(0.3);
		$sheet->getPageMargins()->setFooter(0.3);

		$headers = ['STT', 'Mã HVSV', 'Họ đệm', 'Tên', 'Lớp', 'TP1', 'TP2', 'THI', 'HP', 'Chữ'];
		$widths =  [6,      11,         18,       8,     8,     6,     6,    6,      6,     6];
		$COLS = sizeof($headers);

		for($i=1; $i<=$COLS; $i++)
		{
			$columnLetter = Coordinate::stringFromColumnIndex($i);
			$sheet->getColumnDimension($columnLetter)->setWidth($widths[$i-1]);
		}

		//Init
		$row=0;

		//Thông tin cơ quan
		$row++;
		$midCol = 3;
		$organizationName = mb_strtoupper(ConfigHelper::getOrganization());
		$sheet->setCellValue([1,$row], $organizationName);
		$sheet->mergeCells([1,$row, $midCol, $row]);
		$sheet->setCellValue([$midCol+1, $row], 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM');
		$sheet->mergeCells([$midCol+1, $row, $COLS, $row]);

		$cellStyle = $sheet->getStyle([1,$row, $COLS, $row]);
		$cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		$row++;
		$unitName = mb_strtoupper(ConfigHelper::getExaminationUnit());
		$sheet->setCellValue([1, $row], $unitName);
		$sheet->mergeCells([1,$row, $midCol, $row]);
		$sheet->setCellValue([$midCol+1, $row], 'Độc lập - Tự do - Hạnh phúc');
		$sheet->mergeCells([$midCol+1,$row, $COLS, $row]);

		$cellStyle = $sheet->getStyle([1,$row, $COLS, $row]);
		$cellStyle->getFont()->setBold(true);
		$cellStyle->getFont()->setUnderline(true);
		$cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Title và Subtitle
		$row  = 4;  //Theo đúng mẫu
		$subTitle = Text::sprintf('HỌC KỲ %d - NĂM HỌC %s', $examInfo->term, $examInfo->academicyear);
		$sheet->setCellValue([1, $row], "KẾT QUẢ ĐÁNH GIÁ HỌC PHẦN");
		$sheet->mergeCells([1,$row, $COLS, $row]);
		$sheet->setCellValue([1, $row+1], $subTitle);
		$sheet->mergeCells([1,$row+1, $COLS, $row+1]);
		$style = $sheet->getStyle([1, $row, $COLS, $row+1]);
		$style->getFont()->setBold(true);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Thông tin môn thi
		$row = 6; //Theo đúng mẫu
		$sheet->setCellValue([1, $row], 'Học phần:');
		$sheet->setCellValue([3, $row], $examInfo->name);
		$sheet->getStyle([2,$row])->getFont()->setBold(true);
		$sheet->setCellValue([7, $row], 'Số TC:');
		$sheet->setCellValue([8, $row], $examInfo->credits);
		$sheet->setCellValue([9, $row], 'Mã học phần: ' . $examInfo->code);

		//Dòng heading
		$row=12;    //Theo đúng mẫu
		$headingRow = $row;
		foreach ($headers as $index=>$header)
		{
			$sheet->setCellValue([$index+1, $headingRow], $header);
		}
		$style = $sheet->getStyle([1,$headingRow, $COLS, $headingRow]);
		$style->getFont()->setBold(true);

		//Các dòng dữ liệu
		$seq=0;
		foreach ($examResult as $item)
		{
			$seq++;
			$row++;
			$data = [
				$seq,
				$item->learner_code,
				$item->lastname,
				$item->firstname,
				$item->group,
				$item->stimulation_type==StimulationHelper::TYPE_TRANS ? $item->module_mark : ExamHelper::markToText($item->pam1),
				$item->stimulation_type==StimulationHelper::TYPE_TRANS ? $item->module_mark : ExamHelper::markToText($item->pam2),
				($item->anomaly==ExamHelper::EXAM_ANOMALY_DELAY || $item->anomaly==ExamHelper::EXAM_ANOMALY_ABSENT) ? 'K' : $item->mark_final,
				$item->module_mark,
				$item->module_grade
			];
			foreach ($data as $index=>$value)
			{
				$sheet->setCellValue([$index+1, $row], $value);
			}
		}

		//Kẻ bảng và căn lề
		$style = $sheet->getStyle([1, $headingRow, $COLS, $row]);
		$style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
		$style = $sheet->getStyle([1, $headingRow, 2, $row]);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$style = $sheet->getStyle([5, $headingRow, $COLS, $row]);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Phông chữ
		$style = $sheet->getStyle($sheet->calculateWorksheetDimension());
		$style->getFont()->setName('Times New Roman');
		$style = $sheet->getStyle([9, $headingRow+1, 9, $row]);
		$style->getFont()->setBold(true);

	}

	static public function writeMaskMap(Worksheet $sheet, array $map, $examInfo):void
	{
		/**
		 * Sơ đồ phách được xuất ra phục vụ cho việc đánh phách trực tiếp lên bài thi
		 * đồng thời có thể lưu trữ như một phần của hồ sơ thi.
		 * $map là một mảng thông thường. Mỗi phần tử là một mảng liên kết ['mask', 'code'].
		 */
		$FONT_SIZE = 14;
		$COLS = 7;

		// Set page margins (values are in inches)
		$sheet->getPageMargins()->setTop(0.5);
		$sheet->getPageMargins()->setBottom(0.75);
		$sheet->getPageMargins()->setLeft(0.45);
		$sheet->getPageMargins()->setRight(0.45);
		$sheet->getPageMargins()->setHeader(0.3);
		$sheet->getPageMargins()->setFooter(0.3);

		$widths = [8, 10, 10, 15, 25, 12, 10];
		for($i=1; $i<=$COLS; $i++)
		{
			$columnLetter = Coordinate::stringFromColumnIndex($i);
			$sheet->getColumnDimension($columnLetter)->setWidth($widths[$i-1]);
		}

		//Init
		$row=0;

		//Thông tin cơ quan
		$row++;
		$midCol = intdiv($COLS,2) + $COLS % 2;
		$organizationName = ConfigHelper::getOrganization();
		$organizationName = mb_strtoupper($organizationName);
		$sheet->getCell('A'.$row)->setValue($organizationName);
		$sheet->mergeCells([1,$row, $midCol, $row]);
		$cellStyle = $sheet->getStyle([1,$row, $midCol, $row]);
		$cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		$row++;
		$unitName = ConfigHelper::getExaminationUnit();
		$unitName = mb_strtoupper($unitName);
		$sheet->getCell('A'.$row)->setValue($unitName);
		$sheet->mergeCells([1,$row, $midCol, $row]);
		$cellStyle = $sheet->getStyle([1,$row, $midCol, $row]);
		$cellStyle->getFont()->setBold(true);
		$cellStyle->getFont()->setUnderline(true);
		$cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Dòng tiêu đề
		$row  += 2;
		$cell = $sheet->getCell([1,$row]);
		$cell->setValue("SƠ ĐỒ PHÁCH");
		$cell->getStyle()->getFont()->setBold(true);
		$cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$sheet->mergeCells([1,$row, $COLS, $row]);

		//Thông tin môn thi
		$row += 2;
		$value = new RichText();
		$part = $value->createTextRun('Môn thi: ');
		$part->getFont()->setSize($FONT_SIZE)->setName('Times New Roman');

		$part = $value->createTextRun($examInfo->name);
		$part->getFont()->setBold(true)->setSize($FONT_SIZE)->setName('Times New Roman');

		$part = $value->createTextRun('    (Mã môn thi: ' . $examInfo->id . ')');
		$part->getFont()->setSize($FONT_SIZE)->setName('Times New Roman');

		$sheet->setCellValue('A'.$row, $value);
		$sheet->mergeCells([1,$row, $COLS, $row]);

		//Kỳ thi
		$row++;
		$value = 'Kỳ thi: ' . $examInfo->examseason;
		$sheet->getCell('A'.$row)->setValue($value);
		$sheet->mergeCells([1,$row, $COLS, $row]);

		//Năm học và học kỳ
		$row++;
		$value = 'Năm học: ' . $examInfo->academicyear . '       Học kỳ: ' . $examInfo->term;
		$sheet->getCell('A'.$row)->setValue($value);
		$sheet->mergeCells([1,$row, $COLS, $row]);


		//Heading row
		$row+=2;
		$headingRow = $row;
		$values = ['STT',	'SBD',	'Phách',	'Mã HVSV',	'Họ đệm',	'Tên',	'Số tờ'];
		for($col=1; $col<=sizeof($values); $col++)
			$sheet->getCell([$col, $row])->setValue($values[$col-1]);
		$sheet->getStyle([1,$row,$COLS,$row])->getFont()->setBold(true);

		//Data rows
		$size = sizeof($map);
		$lastMask=0;
		for($i=0; $i<$size; $i++)
		{
			$row++;
			$item=$map[$i];
			$values = [
				$i+1,
				$item->code,
				empty($item->mask) ? '' : $item->mask,
				$item->learner_code,
				$item->lastname,
				$item->firstname,
				empty($item->nsheet) ? '' : $item->nsheet
			];
			for($col=1; $col<=sizeof($values); $col++)
				$sheet->getCell([$col, $row])->setValue($values[$col-1]);

			//Nếu bắt đầu đoạn phách mới thì đánh đấu
			if($lastMask!=0 && !empty($item->mask) && ($item->mask - $lastMask !=1))
			{
				$style = $sheet->getStyle([1, $row, $COLS, $row]);
				$style->getFont()->setBold(true);
				$style->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('A9A9A9')); //Dark gray
			}
			if(!empty($item->mask))
				$lastMask = $item->mask;
		}
		$bottomRow = $row;

		//Format the table
		$sheet->getStyle([1, $headingRow, $COLS, $bottomRow])->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
		$sheet->getStyle([1, $headingRow, 4, $bottomRow])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$sheet->getStyle([$COLS, $headingRow, $COLS, $bottomRow])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Format
		$font = $sheet->getStyle($sheet->calculateWorksheetDimension())->getFont();
		$font->setName('Times New Roman');
		$font->setSize($FONT_SIZE);

	}
	static public function writeMarkingSheet(Worksheet $sheet, GradeCorrectionInfo $packageInfo):void
	{
		$params = JComponentHelper::getParams('com_eqa');
		$PARTS = 2;
		$PART_WIDTH = 3;  //Mỗi part gồm 3 cột: Số phách, Điểm bằng số, Điểm bằng chữ
		$FONT_SIZE = 14;
		$COLS = $PARTS * ($PART_WIDTH+1) - 1;      // Giữa 2 part có một cột trống

		// Set page margins (values are in inches)
		$sheet->getPageMargins()->setTop(0.5);
		$sheet->getPageMargins()->setBottom(0.75);
		$sheet->getPageMargins()->setLeft(0.45);
		$sheet->getPageMargins()->setRight(0.45);
		$sheet->getPageMargins()->setHeader(0.3);
		$sheet->getPageMargins()->setFooter(0.3);

		$remainWidth = self::PAGE_WIDTH_A4 - 0.45 - 0.45 - 0.5;
		$averageColumnWidth = $remainWidth/$COLS;
		for($i=1; $i<=$COLS; $i++)
		{
			$columnLetter = Coordinate::stringFromColumnIndex($i);
			$sheet->getColumnDimension($columnLetter)->setWidth($averageColumnWidth,'in');
		}

		//Init
		$row=0;

		//Thông tin cơ quan
		$row++;
		$midCol = intdiv($COLS,2) + $COLS % 2;
		$organizationName = $params->get('params.organization','Học viện Kỹ thuật mật mã');
		$organizationName = mb_strtoupper($organizationName);
		$sheet->getCell('A'.$row)->setValue($organizationName);
		$sheet->mergeCells([1,$row, $midCol, $row]);
		$cellStyle = $sheet->getStyle([1,$row, $midCol, $row]);
		$cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		$row++;
		$unitName = $params->get('params.examination_unit','Phòng KT&ĐBCLĐT');
		$unitName = mb_strtoupper($unitName);
		$sheet->getCell('A'.$row)->setValue($unitName);
		$sheet->mergeCells([1,$row, $midCol, $row]);
		$cellStyle = $sheet->getStyle([1,$row, $midCol, $row]);
		$cellStyle->getFont()->setBold(true);
		$cellStyle->getFont()->setUnderline(true);
		$cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Dòng tiêu đề
		$row  += 2;
		$cell = $sheet->getCell([1,$row]);
		$cell->setValue("PHIẾU CHẤM THI VIẾT");
		$cell->getStyle()->getFont()->setBold(true);
		$cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$sheet->mergeCells([1,$row, $COLS, $row]);

		//Thông tin môn thi
		$row += 2;
		$value = new RichText();
		$part = $value->createTextRun('Môn thi: ');
		$part->getFont()->setSize($FONT_SIZE)->setName('Times New Roman');

		$part = $value->createTextRun($packageInfo->examName);
		$part->getFont()->setBold(true)->setSize($FONT_SIZE)->setName('Times New Roman');

		$part = $value->createTextRun('    (Mã môn thi: ' . $packageInfo->examId . ')');
		$part->getFont()->setSize($FONT_SIZE)->setName('Times New Roman');

		$sheet->setCellValue('A'.$row, $value);
		$sheet->mergeCells([1,$row, $COLS, $row]);

		//Kỳ thi
		$row++;
		$value = 'Kỳ thi: ' . $packageInfo->examseasonName;
		$sheet->getCell('A'.$row)->setValue($value);
		$sheet->mergeCells([1,$row, $COLS, $row]);

		//Năm học và học kỳ
		$row++;
		$value = 'Năm học: ' . $packageInfo->academicyearCode . '       Học kỳ: ' . $packageInfo->term;
		$sheet->getCell('A'.$row)->setValue($value);
		$sheet->mergeCells([1,$row, $COLS, $row]);

		//Thông tin túi bài thi
		$row++;
		$value = 'Túi số: ' . $packageInfo->number
			. '         Số bài: ' . $packageInfo->paperCount
			. '         Số tờ: ' . $packageInfo->sheetCount;
		$sheet->getCell('A'.$row)->setValue($value);
		$sheet->mergeCells([1,$row, $COLS, $row]);

		//Tính toán số phách cho mỗi cột
		//  1. Chia đều số bài cho số cột
		//  2. Nếu dư R bài thì rải vào R cột bên trái
		$colSizes = [];
		$m = intdiv($packageInfo->paperCount, $PARTS);
		$r = $packageInfo->paperCount % $PARTS;
		for($i=0; $i<$PARTS; $i++)
		{
			if($i < $r)
				$colSizes[] = $m+1;
			else
				$colSizes[] = $m;
		}

		//Ghi các PART vào sheet
		$row += 2;
		$headingRow = $row;         //Lưu lại vị trí này để còn trở lại và lưu các PART sau
		$lastRow=0;
		$mask = $packageInfo->firstMask;
		for($p=0; $p<$PARTS; $p++)
		{
			//Ghi Heading của mỗi cột (part)
			$leftCol = $p * ($PART_WIDTH+1) + 1; //Đánh số 1-based
			$sheet->getCell([$leftCol,$row])->setValue('Số phách');
			$sheet->mergeCells([$leftCol, $row, $leftCol, $row+1]);
			$sheet->getStyle([$leftCol, $row, $leftCol, $row+1])->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

			$sheet->getCell([$leftCol+1,$row])->setValue('Điểm');
			$sheet->mergeCells([$leftCol+1, $row, $leftCol+2, $row]);

			$sheet->getCell([$leftCol+1, $row+1])->setValue('Bằng số');
			$sheet->getCell([$leftCol+2, $row+1])->setValue('Bằng chữ');

			$sheet->getStyle([$leftCol,$row,$leftCol+2,$row+1])->getFont()->setBold(true);

			//Ghi số phách (Lùi để tiến)
			$row = $row + 2 -1;
			for($i=0; $i<$colSizes[$p]; $i++)
			{
				$row++;
				$sheet->getCell([$leftCol,$row])->setValue($mask);
				$mask++;
			}

			//Ghi xong mỗi cột (part) thì trở lại dòng tiêu đề
			$rangeStyle = $sheet->getStyle([$leftCol, $headingRow, $leftCol+2, $row]);
			$rangeStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
			$rangeStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
			if($COLS < $row)
				$lastRow = $row;
			$row = $headingRow;
		}

		//Ngày tháng
		$row = $lastRow+2;
		$city = $params->get('params.city','Hà Nội');
		$value = $city . ', ngày .... tháng ..... năm 20.....';
		$cell = $sheet->getCell('A'.$row);
		$cell->setValue($value);
		$cell->getStyle()->getFont()->setItalic(true);
		$cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
		$sheet->mergeCells([1,$row, $COLS, $row]);

		//Ký tên
		$midCol = intdiv($COLS, 2);
		$row++;
		$sheet->getCell('A'.$row)->setValue('CÁN BỘ CHẤM THI 1');
		$sheet->mergeCells([1,$row, $midCol, $row]);
		$sheet->getCell([$midCol+2, $row])->setValue('CÁN BỘ CHẤM THI 2');
		$sheet->mergeCells([$midCol+2, $row, $COLS, $row]);
		$cellStyle = $sheet->getStyle([1,$row, $COLS, $row]);
		$cellStyle->getFont()->setBold(true);
		$cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		$row += 4;
		$sheet->getCell('A'.$row)->setValue($packageInfo->firstExaminerFullname);
		$sheet->mergeCells([1,$row, $midCol, $row]);
		$sheet->getCell([$midCol+2, $row])->setValue($packageInfo->secondExaminerFullname);
		$sheet->mergeCells([$midCol+2, $row, $COLS, $row]);
		$cellStyle = $sheet->getStyle([1,$row, $COLS, $row]);
		$cellStyle->getFont()->setBold(true);
		$cellStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Set font for all the sheet
		$font = $sheet->getStyle($sheet->calculateWorksheetDimension())->getFont();
		$font->setName('Times New Roman');
		$font->setSize($FONT_SIZE);
	}
	static public function writeExamseasonExaminees(Worksheet $sheet, array $examinees)
	{
		if(empty($examinees))
			return;

		//Ghi dòng tiêu đề
		$row=1;
		$keys = array_keys($examinees[0]);
		foreach ($keys as $index=>$key)
		{
			$sheet->setCellValue([$index+1,$row],$key);
		}

		//Ghi dữ liệu
		foreach ($examinees as $examinee)
		{
			$row++;
			$col=0;
			foreach ($examinee as $key=>$value)
			{
				$col++;
				$sheet->setCellValue([$col,$row],$value);
			}
		}
	}

	/**
	 * Thống kê sản lượng ra đề thi
	 * @param   Worksheet  $sheet
	 * @param   array      $questionProduction
	 *
	 *
	 * @since version
	 */
	static public function writeExamseasonQuestionProductions(Worksheet $sheet, array $questionProduction)
	{
		$headers = ['STT', 'Họ đệm', 'Tên', 'Đơn vị', 'Số đề', 'Diễn giải'];
		$widths =  [6,      20,       8,          10,     10,    50];
		$COLS = sizeof($headers);
		for($i=1; $i<=$COLS; $i++)
		{
			$columnLetter = Coordinate::stringFromColumnIndex($i);
			$sheet->getColumnDimension($columnLetter)->setWidth($widths[$i-1]);
		}

		$row=1;
		$sheet->setCellValue([1,$row], 'THỐNG KÊ SẢN LƯỢNG RA ĐỀ THI');
		$sheet->mergeCells([1,$row, $COLS, $row]);
		$style = $sheet->getStyle([1,$row, $COLS, $row]);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$style->getFont()->setBold(true);

		$row += 2;
		$headingRow = $row;
		foreach ($headers as $index => $header)
			$sheet->setCellValue([$index+1, $row], $header);
		$style = $sheet->getStyle([1,$row,$COLS,$row]);
		$style->getFont()->setBold(true);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);


		$seq=0;
		foreach ($questionProduction as $employee)
		{
			$seq++;
			$row++;
			$data = [
				$seq,
				$employee['lastname'],
				$employee['firstname'],
				$employee['unit'],
				$employee['count'],
				$employee['details']
			];
			foreach ($data as $index => $value)
				$sheet->setCellValue([$index+1, $row], $value);
		}

		//Kẻ bảng
		$style = $sheet->getStyle([1,$headingRow, $COLS, $row]);
		$style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
	}
	static public function writeExamseasonMonitoringProductions(Worksheet $sheet, array $monitoringProductions)
	{
		$headers = ['STT', 'Họ đệm', 'Tên', 'Đơn vị', 'Số ca', 'Quy đổi'];
		$widths =  [6,      20,       8,          10,     10,    10];
		$COLS = sizeof($headers);
		for($i=1; $i<=$COLS; $i++)
		{
			$columnLetter = Coordinate::stringFromColumnIndex($i);
			$sheet->getColumnDimension($columnLetter)->setWidth($widths[$i-1]);
		}

		$row=1;
		$sheet->setCellValue([1,$row], 'THỐNG KÊ SẢN LƯỢNG COI THI');
		$sheet->mergeCells([1,$row, $COLS, $row]);
		$style = $sheet->getStyle([1,$row, $COLS, $row]);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$style->getFont()->setBold(true);

		$row += 2;
		$headingRow = $row;
		foreach ($headers as $index => $header)
			$sheet->setCellValue([$index+1, $row], $header);
		$style = $sheet->getStyle([1,$row,$COLS,$row]);
		$style->getFont()->setBold(true);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		$seq=0;
		foreach ($monitoringProductions as $employee)
		{
			$seq++;
			$row++;
			$data = [
				$seq,
				$employee['lastname'],
				$employee['firstname'],
				$employee['unit'],
				$employee['count'],
				(float)$employee['production']
			];
			foreach ($data as $index => $value)
				$sheet->setCellValue([$index+1, $row], $value);
		}

		//Kẻ bảng
		$style = $sheet->getStyle([1,$headingRow, $COLS, $row]);
		$style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
	}

	static public function writeExamseasonMarkingProductions(Worksheet $sheet, array $markingProductions)
	{
		/**
		 * Cấu trúc của $markingProduction
		 * [employee_id] => [exam_id][count1, count2, kassess, name]
		 */

		//Lấy bổ sung thông tin về giảng viên
		$employeeIds = array_keys($markingProductions);
		$employeeInfos = DatabaseHelper::getEmployeeInfos($employeeIds, 'assoc');

		//Ghi tiêu đề
		$headers = ['STT', 'Họ đệm', 'Tên', 'Đơn vị', 'Số bài chấm 1', 'Quy đổi chấm 1', 'Số bài chấm 2', 'Quy đổi chấm 2', 'Diễn giải'];
		$widths =  [6,      20,       8,          10,  20,              20,               20,              20,               70];
		$COLS = sizeof($headers);
		for($i=1; $i<=$COLS; $i++)
		{
			$columnLetter = Coordinate::stringFromColumnIndex($i);
			$sheet->getColumnDimension($columnLetter)->setWidth($widths[$i-1]);
		}

		$row=1;
		$sheet->setCellValue([1,$row], 'THỐNG KÊ SẢN LƯỢNG CHẤM THI');
		$sheet->mergeCells([1,$row, $COLS, $row]);
		$style = $sheet->getStyle([1,$row, $COLS, $row]);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$style->getFont()->setBold(true);

		$row += 2;
		$headingRow = $row;
		foreach ($headers as $index => $header)
			$sheet->setCellValue([$index+1, $row], $header);
		$style = $sheet->getStyle([1,$row,$COLS,$row]);
		$style->getFont()->setBold(true);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Ghi dữ liệu
		$seq=0;
		foreach ($markingProductions as $employeeId => $production)
		{
			//Tính toán
			$count1 = $count2 = $production1 = $production2 = 0;
			$description = '';

			foreach ($production as $examId => $examProduction)
			{
				$count1 += $examProduction['count1'];
				$production1 += $examProduction['count1'] * $examProduction['kassess'];
				$count2 += $examProduction['count2'];
				$production2 += $examProduction['count2'] * $examProduction['kassess'];

				if(!empty($description))
					$description .= "; \n";
				$description .= Text::sprintf('%s: %d Chấm 1, %d Chấm 2',
					$examProduction['name'],
					$examProduction['count1'],
					$examProduction['count2']
				);
			}

			//Ghi dữ liệu
			$seq++;
			$row++;
			$data = [
				$seq,
				$employeeInfos[$employeeId]['lastname'],
				$employeeInfos[$employeeId]['firstname'],
				$employeeInfos[$employeeId]['unit'],
				$count1,
				(float) $production1,
				$count2,
				(float) $production2,
				$description
			];
			foreach ($data as $index => $value)
				$sheet->setCellValue([$index+1, $row], $value);
		}

		// Enable text wrapping
		$sheet->getStyle([$COLS,$headingRow, $COLS, $row])->getAlignment()->setWrapText(true);

		//Kẻ bảng
		$style = $sheet->getStyle([1,$headingRow, $COLS, $row]);
		$style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

	}

	static public function writeExamseasonMarkStatistic(Worksheet $sheet,  array $markStatistic)
	{
		$headers = ['TT', 'Môn thi', 'Hình thức', 'Tổng', 'Đạt', '%', 'Trượt', '%', 'Khác', '%', 'Trung bình', 'TB Đạt'];
		$widths = [6,      40,        20,          10,     10,    6,  10,       6,  10,     6,   10,            10];
		$COLS = sizeof($headers);
		for($i=1; $i<=$COLS; $i++)
		{
			$columnLetter = Coordinate::stringFromColumnIndex($i);
			$sheet->getColumnDimension($columnLetter)->setWidth($widths[$i-1]);
		}
		$row=1;

		//Dòng tiêu đề
		$headingRow = $row;
		foreach ($headers as $index => $header)
			$sheet->setCellValue([$index+1, $row],$header);
		$style = $sheet->getStyle([1,$row,$COLS,$row]);
		$style->getFont()->setBold(true);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Các dòng giá trị
		$seq=0;
		foreach ($markStatistic as $item)
		{
			$row++;
			$seq++;

			$data = [
				$seq,
				$item['name'],
				ExamHelper::getTestType($item['testtype']),
				$item['total'],
				$item['passed'],
				$item['total']>0 ? round($item['passed']*100/$item['total']) : null,
				$item['failed'],
				$item['total']>0 ? round($item['failed']*100/$item['total']) : null,
				$item['other'],
				$item['total']>0 ? round($item['other']*100/$item['total']) : null,
				$item['avg'],
				$item['avg_passed']
			];

			foreach ($data as $index=>$value)
				$sheet->setCellValue([$index+1,$row], $value);
		}

		//Kẻ bảng
		$sheet->getStyle([1,$headingRow, $COLS, $row])->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

		//Căn lề
		$sheet->getStyle([1,$headingRow+1, 1, $row])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$sheet->getStyle([4,$headingRow+1, 10, $row])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
	}

	static public function writeExamseasonMarkDistribution(Worksheet $dataSheet, Worksheet $chartSheet, array $markStatistic)
	{
		$grades = ['other', 'F', 'D', 'D+', 'C', 'C+', 'B', 'B+', 'A', 'A+'];
		$headers = ['TT', 'Môn thi', 'Hình thức', 'Tổng', '-',  'F', 'D', 'D+', 'C', 'C+', 'B', 'B+', 'A', 'A+'];
		$widths = [6,      40,        20,          10,    6,    6,   6,    6,   6,    6,   6,   6,    6,     6];
		$COLS = sizeof($headers);
		for($i=1; $i<=$COLS; $i++)
		{
			$columnLetter = Coordinate::stringFromColumnIndex($i);
			$dataSheet->getColumnDimension($columnLetter)->setWidth($widths[$i-1]);
		}
		$row=1;

		//Dòng tiêu đề
		$headingRow = $row;
		$dataSheet->fromArray($headers, null, 'A'.$headingRow);
		$style = $dataSheet->getStyle([1,$row,$COLS,$row]);
		$style->getFont()->setBold(true);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Các dòng giá trị
		$seq=0;
		foreach ($markStatistic as $item)
		{
			$seq++;
			$examName = $item['name'];
			$examineeCount = $item['total'];


			$row++;
			$data = [
				$seq,
				$examName,
				ExamHelper::getTestType($item['testtype']),
				$examineeCount,
			];
			foreach ($grades as $grade)
				$data[] = $item[$grade];
			$dataSheet->fromArray($data, null, 'A'.$row, true);

			$row++;
			$data = [null, null, null, '%'];
			foreach ($grades as $grade)
				$data[] = round(100*$item[$grade]/$examineeCount,1);
			$dataSheet->fromArray($data, null, 'A'.$row, true);

			$dataSheet->mergeCells([1,$row-1,1,$row]);
			$dataSheet->mergeCells([2,$row-1,2,$row]);
			$dataSheet->mergeCells([3,$row-1,3,$row]);
		}

		//Kẻ bảng
		$dataSheet->getStyle([1,$headingRow, $COLS, $row])->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

		//Căn lề
		$dataSheet->getStyle([1,$headingRow+1, 1, $row])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$dataSheet->getStyle([4,$headingRow+1, $COLS, $row])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);


		//Vẽ biểu đồ
		$CHART_HEIGHT = 10;
		$CHART_WIDTH=8;
		$CHART_PAD = 2;
		$chartCategoriesRange = "'" . $dataSheet->getTitle()  . "'" . '!E' . $headingRow . ':N'.$headingRow;
		$chartCategories = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $chartCategoriesRange);
		$i=0;
		foreach ($markStatistic as $exam)
		{
			$examName = $exam['name'];
			$chartTopRow = ($i)*($CHART_HEIGHT+$CHART_PAD)+1;
			$chartTopLeft = 'A'.$chartTopRow;
			$chartBottomRight = Coordinate::stringFromColumnIndex($CHART_WIDTH+1).($chartTopRow+$CHART_HEIGHT-1);
			$valueRow = 3 + $i*2;
			$chartValuesRange = "'" . $dataSheet->getTitle()  . "'" . '!E' . $valueRow . ':N'.$valueRow;
			$chartValues = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $chartValuesRange);
			$chartDataSeries = new DataSeries(
				DataSeries::TYPE_BARCHART,    // Chart type
				DataSeries::GROUPING_STANDARD, // Grouping
				[0],                           // Plot order
				[],                            // Legend entries
				[$chartCategories],                 // X-axis labels
				[$chartValues]                      // Data values
			);
			$plotArea = new PlotArea(null, [$chartDataSeries]);
			$chartTitle = new Title($examName);
			$chart = new Chart(
				$examName,                 // Chart name
				$chartTitle,
				null,
				$plotArea                     // Plot area
			);
			$chart->setTopLeftPosition($chartTopLeft);
			$chart->setBottomRightPosition($chartBottomRight);
			$chartSheet->addChart($chart);
			$i++;
		}

	}

	static public function writeExamseasonStatistic(Worksheet $sheet, array $statistic)
	{
		$row=1;
		foreach ($statistic as $key => $value)
		{
			$sheet->setCellValue([1, $row], $key);
			$sheet->setCellValue([2, $row], $value);
			$row++;
		}
	}

	static public function writeRegradings(Spreadsheet $spreadsheet, array $items):void
	{
		$headers = ['TT', 'Khóa', 'Lớp', 'Mã HVSV', 'Họ đệm', 'Tên', 'Môn phúc khảo'];
		$widths = [6,      15,    15,    20,          20,    15, 40];
		$COLS = sizeof($headers);
		$sheet = $spreadsheet->createSheet();
		$sheet->setTitle('Phúc khảo');
		for($i=1; $i<=$COLS; $i++)
		{
			$columnLetter = Coordinate::stringFromColumnIndex($i);
			$sheet->getColumnDimension($columnLetter)->setWidth($widths[$i-1]);
		}
		$row=1;

		//Dòng tiêu đề
		$headingRow = $row;
		$sheet->fromArray($headers, null, 'A'.$headingRow);
		$style = $sheet->getStyle([1,$row,$COLS,$row]);
		$style->getFont()->setBold(true);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Dữ liệu
		$row++;
		$data = [];
		$seq=0;
		foreach ($items as $item)
		{
			$seq++;
			$data[] = [
				$seq,
				$item->course,
				$item->group,
				$item->code,
				$item->lastname,
				$item->firstname,
				$item->exam
			];
		}
		$sheet->fromArray($data, null, 'A'.$row);
	}
	static public function writeGradeCorrections(Spreadsheet $spreadsheet, array $items):void
	{
		$headers = ['TT', 'Khóa', 'Lớp', 'Mã HVSV', 'Họ đệm', 'Tên', 'Môn phúc khảo', 'Điểm', 'Lý do'];
		$widths = [6,      15,    15,    20,          20,       15,   40,             '20',   '40'];
		$COLS = sizeof($headers);
		$sheet = $spreadsheet->createSheet();
		$sheet->setTitle('Phúc khảo');
		for($i=1; $i<=$COLS; $i++)
		{
			$columnLetter = Coordinate::stringFromColumnIndex($i);
			$sheet->getColumnDimension($columnLetter)->setWidth($widths[$i-1]);
		}
		$row=1;

		//Dòng tiêu đề
		$headingRow = $row;
		$sheet->fromArray($headers, null, 'A'.$headingRow);
		$style = $sheet->getStyle([1,$row,$COLS,$row]);
		$style->getFont()->setBold(true);
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

		//Dữ liệu
		$row++;
		$data = [];
		$seq=0;
		foreach ($items as $item)
		{
			$seq++;
			$data[] = [
				$seq,
				$item->course,
				$item->group,
				$item->code,
				$item->lastname,
				$item->firstname,
				$item->exam,
				ExamHelper::decodeMarkConstituent($item->constituent),
				$item->reason
			];
		}
		$sheet->fromArray($data, null, 'A'.$row);
	}
}

