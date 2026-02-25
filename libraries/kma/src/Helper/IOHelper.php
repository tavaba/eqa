<?php
namespace Kma\Library\Kma\Helper;
defined('_JEXEC') or die();
require_once JPATH_ROOT.'/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory as ExcelIOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Tab;

abstract class IOHelper
{
    protected const PAGE_WIDTH_A4_IN_INCH = 8.267;  //inch
    public static function sanitizeSheetTitle(string $title, int $maxLenth=31): string
    {
        //The title max length is 31 characters according to Excel specification.
        if($maxLenth>31)
            $maxLenth = 31;

        // Remove invalid characters
        $title = preg_replace('/[:\\\\\/?*\[\]]/', '_', $title);

        // Trim leading/trailing whitespace or quotes
        $title = trim($title, " \t\n\r\0\x0B'");

        // Limit length to 31 characters (Excel max)
        $title = mb_substr($title, 0, $maxLenth);

        //Trim trailing whitespace again
        return rtrim($title);
    }
    public static function loadSpreadsheet(string $fileName): Spreadsheet
    {
        try
        {
            $reader = new Xls();                                        //Try to open as .XLS first
            return $reader->load($fileName);
        }
        catch (\PhpOffice\PhpSpreadsheet\Exception $e){
            unset($e);
            $reader = ExcelIOFactory::createReader('Xlsx');  //Then try to open as .XLSX if failed
            return $reader->load($fileName);
        }
    }
    public static function phpWordDefineCommonStyles(PhpWord $phpWord): void
    {

        //1. Font styles
        $phpWord->setDefaultFontSize(14);
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->addFontStyle('Bold', ['bold' => true]);
        $phpWord->addFontStyle('Italic', ['italic' => true]);
        $phpWord->addFontStyle('ItalicBold', ['italic' => true, 'bold' => true]);
        $phpWord->addFontStyle('BoldUnderlined', ['bold' => true, 'underline'=>'single']);
        $phpWord->addFontStyle('ItalicUnderlined', ['italic' => true, 'underline'=>'single']);
        $phpWord->addFontStyle('ItalicBoldUnderlined', ['italic' => true, 'bold' => true, 'underline'=>'single']);

        //Title styles
        $phpWord->addTitleStyle(1,
            [
                'bold' => true,
                'size'=>14
            ],
            [
                'alignment'=> Jc::START,
                'pageBreakBefore' => true,
                'keepNext'=>true,
                'keepLines'=>true
            ]
        );


        //2. Paragraph styles
        $phpWord->addParagraphStyle('Normal', [
            'alignment'=> Jc::BOTH,
            'firstLine'=>709,  //1.25 cm
            'lineHeight' => 1.15,
            'spaceBefore' => 0,
            'spaceAfter'  => 0,
        ]);
        $phpWord->addParagraphStyle('Center', [
            'alignment'=> Jc::CENTER,
            'firstLine' => 0,
            'baseOn'=>'Normal'
        ]);
        $phpWord->addParagraphStyle('Left', [
            'alignment'=> Jc::START,
            'baseOn'=>'Normal'
        ]);
        $phpWord->addParagraphStyle('Right', [
            'alignment'=> Jc::END,
            'baseOn'=>'Normal'
        ]);
        $phpWord->addParagraphStyle('Title', [
            'alignment'=> Jc::CENTER,
            'firstLine' => 0,
            'spaceBefore' => 240,
            'spaceAfter'  => 120,
        ]);
        $phpWord->addParagraphStyle('TitleWithoutSpaceAfter', [
            'alignment'=> Jc::CENTER,
            'firstLine' => 0,
            'spaceBefore' => 240,
            'spaceAfter'  => 0
        ]);
        $phpWord->addParagraphStyle('Subtitle', [
            'alignment'=> Jc::CENTER,
            'firstLine' => 0,
            'spaceAfter' => Converter::pointToTwip(6),
            'baseOn'=>'Normal'
        ]);

        //3. Headings
        $phpWord->addParagraphStyle('HeadingLevel1', [
            'outlineLevel'     => 1,
            'alignment'=> Jc::START,
            'pageBreakBefore' => true,
            'baseOn' => 'Heading1'
        ]);
        $phpWord->addParagraphStyle('HeadingLevel1WithoutPageBreak', [
            'baseOn' => 'HeadingLevel1',
            'pageBreakBefore' => false
        ]);
        $phpWord->addParagraphStyle('HeadingLevel2', [
            'outlineLevel'     => 2,
            'alignment'=> Jc::BOTH,
            'keepNext'         => true,
            'keepLines'        => true,
            'spaceBefore' => Converter::pointToTwip(6),
            'spaceAfter'  => Converter::pointToTwip(3),
        ]);


        //4. Other paragraph styles
        $phpWord->addParagraphStyle('Blockquote', [
            'alignment'   => Jc::BOTH,
            'indentation' => array(
                'left'  => Converter::cmToTwip(1.0), // 1.0 cm left indent
                'right' => Converter::cmToTwip(1.0)  // 1.0 cm right indent
            ),
            'spaceBefore' => Converter::pointToTwip(12),
            'spaceAfter'  => Converter::pointToTwip(12)
        ]);
        $phpWord->addParagraphStyle('DotLine', [
            'lineHeight' => 2.0,
            'tabs' => [
                new Tab(
                    Tab::TAB_STOP_RIGHT,
                    9072,               //16 cm
                    Tab::TAB_LEADER_DOT
                )
            ],
            'baseOn'=>'Normal'
        ]);
    }
	public static function phpWordAddCommonSection(PhpWord $phpWord): Section
    {
        $sectionStyle = [
            'marginTop'    => 1134,  // 2 cm
            'marginRight'  => 1134,  // 2 cm
            'marginBottom' => 1134,  // 2 cm
            'marginLeft'   => 1701,  // 3 cm
        ];
        return $phpWord->addSection($sectionStyle);
    }
    public static function sendHttpXlsx(Spreadsheet $spreadsheet, string $fileName, bool $includeCharts=false): void
    {
        // Sanitize the file name
        $fileName = preg_replace('/[\\\\\/:*?"<>|]/u', '_', $fileName);
        $fileName = mb_substr(trim($fileName), 0, 255);

        // Create a temporary file
        $tmpDir = JPATH_SITE . '/tmp';  // or use \Joomla\CMS\Factory::getApplication()->get('tmp_path')
        $tmpFile = tempnam($tmpDir, 'xlsx_');

        // Save the spreadsheet to temp file
        $writer = new Xlsx($spreadsheet);
        if ($includeCharts) {
            $writer->setIncludeCharts(true);
        }
        $writer->save($tmpFile);

        // Clear any previous output
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Send headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
        header('Cache-Control: max-age=0');
        header('Expires: 0');
        header('Content-Length: ' . filesize($tmpFile));

        // Output the file content
        readfile($tmpFile);

        // Delete the temp file
        unlink($tmpFile);
    }
    public static function sendHttpDocx(PhpWord $phpWord, string $fileName): void
    {
        // Sanitize the file name
        $fileName = preg_replace('/[\\\\\/:*?"<>|]/u', '_', $fileName);
        $fileName = mb_substr(trim($fileName), 0, 255);

        // Create a temporary file
        $tmpDir = JPATH_SITE . '/tmp';  // or use \Joomla\CMS\Factory::getApplication()->get('tmp_path')
        $tmpFile = tempnam($tmpDir, 'docx_');

        // Save the spreadsheet to temp file
        $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tmpFile);

        // Clear any previous output
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Send headers
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
        header('Cache-Control: max-age=0');
        header('Expires: 0');
        header('Content-Length: ' . filesize($tmpFile));

        // Output the file content
        readfile($tmpFile);

        // Delete the temp file
        unlink($tmpFile);
    }
}

