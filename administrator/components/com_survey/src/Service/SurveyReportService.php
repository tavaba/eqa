<?php
namespace Kma\Component\Survey\Administrator\Service;
defined('_JEXEC') or die('Restricted access');
require_once JPATH_ROOT.'/vendor/autoload.php';
use Exception;
use Kma\Component\Survey\Administrator\Enum\SurveyQuestionType;
use Kma\Library\Kma\Helper\IOHelper;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;

class SurveyReportService
{
    private string $modelJson;
    private array $responses;
    private string $title;

    /*
     * All element types supported by the survey component.
     * The same list is passed (as a part of builderOptions)
     * to the SurveyCreator constructor (file: tmpl/form/design.php)
     * This can be increased in future versions of this component.
     */
    public const SUPPORTED_QUESTION_TYPES = [
        SurveyQuestionType::TEXT,
        SurveyQuestionType::COMMENT,
        SurveyQuestionType::CHECKBOX,
        SurveyQuestionType::RADIO_GROUP,
        SurveyQuestionType::DROPDOWN,
        SurveyQuestionType::TAGBOX,
        SurveyQuestionType::IMAGE_PICKER,
        SurveyQuestionType::BOOLEAN,
        SurveyQuestionType::SLIDER,
        SurveyQuestionType::RATING,
        SurveyQuestionType::RANKING,
        SurveyQuestionType::MATRIX,
    ];

    public function __construct(string $modelJson, array $responses, string $title)
    {
        $this->modelJson = $modelJson;
        $this->responses = $responses;
        $this->title = $title;
    }

    /**
     * Check if a given question is supported by this report generator.
     *
     * @param array $question One question definition from the survey model
     * @param bool $throw If 'true', throws exception on unsupported question type
     * @return bool
     * @throws Exception
     * @since 1.0.0
     */
    public static function checkIfSupported(array $question, bool $throw=false): bool
    {
        $type = $question['type'] ?? null;

        //A. Check if it's one of our supported types
        if (!in_array($type, self::SUPPORTED_QUESTION_TYPES)) {
            if ($throw)
                throw new Exception('Unsupported question type: '. htmlspecialchars($type));
            else
                return false;
        }

        //B. Check for additional properties that are not supported yet

        //B-1. For the "Slider" type, the property "sliderType" is not supported yet
        if($type === SurveyQuestionType::SLIDER)
        {
            $sliderType = $question['sliderType'] ?? null;
            if($sliderType)
            {
                if($throw)
                    throw new Exception('Slider type "'.$sliderType.'" is not supported yet');
                else
                    return false;
            }
        }

        //If all the other checks pass, then we're good!
        return true;
    }

    /**
     * Helper: calculate median
     * @param array $values
     * @return float|null
     * @since 1.0.0
     */
    private function median(array $values): ?float
    {
        $count = count($values);
        if ($count === 0) return null;

        sort($values);
        $middle = (int) floor(($count - 1) / 2);

        if ($count % 2) {
            return (float) $values[$middle];
        }

        return ($values[$middle] + $values[$middle + 1]) / 2.0;
    }

    /**
     * Recursively collect questions (handles panels and nested elements).
     * This is used in the collectQuestions() method.
     *
     * @param array $element
     * @param array &$questions
     * @since  1.0.0
     */
    private function collectQuestions(array $element, array &$questions): void
    {
        $type = $element['type'] ?? null;
        $name = $element['name'] ?? null;

        // Skip containers like panel / paneldynamic
        $containerTypes = SurveyQuestionType::CONTAINER_TYPES;
        if ($name && $type && !in_array($type, $containerTypes, true)) {
            $questions[] = $element;
        }
        elseif (!empty($element['elements'])) {
            foreach ($element['elements'] as $child) {
                $this->collectQuestions($child, $questions);
            }
        }
    }

    /**
     * Extracts and flattens all questions from a SurveyJS form JSON
     *
     * @param string $modelJson JSON string from SurveyJS Creator
     * @return array List of questions with keys: name, type, title, choices (if any)
     * @since 1.0.0
     */
    private function getQuestions(string $modelJson): array
    {
        $form = json_decode($modelJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid survey model JSON: ' . json_last_error_msg());
        }

        $questions = [];

        // Case 1: Form has pages
        if (!empty($form['pages'])) {
            foreach ($form['pages'] as $page) {
                if (!empty($page['elements'])) {
                    foreach ($page['elements'] as $element) {
                        $this->collectQuestions($element, $questions);
                    }
                }
            }
        }

        // Case 2: Form has elements directly (no pages)
        if (!empty($form['elements'])) {
            foreach ($form['elements'] as $element) {
                $this->collectQuestions($element, $questions);
            }
        }

        return $questions;
    }

    /**
     * Calculate statistics for text/comment questions
     *
     * @param array $question  One question definition from the survey model
     * @param array $responses Array of all respondents' answers
     * @return array The statistics for this question which is an assoc array:
     *               - question: Question title
     *               - name: Question name
     *               - type: Question type
     *               - total: Number of respondents who answered this question
     *               - answers: All non-empty responses to this question
     * @since 1.0.0
     */
    protected function calculateTextStatistics(array $question, array $responses): array
    {
        $name = $question['name'];
        $allAnswers = [];
        $answeredCount = 0;

        foreach ($responses as $response) {
            // response = one respondent’s answers (assoc array: qName => value)
            $response = json_decode($response,true);
            if (!array_key_exists($name, $response))
                continue;

            $value = $response[$name];

            if (is_string($value))
                $value = trim($value);

            if (!empty($value))
            {
                $allAnswers[] = $value;
                $answeredCount++;
            }

        }

        return [
            'question'  => $question['title'] ?? $name,
            'name'      => $name,
            'total'     => $answeredCount,
            'answers'   => $allAnswers
        ];
    }

    /**
     * Calculate statistics for score-type questions (rating, nps).
     *
     * @param array $question
     * @param array $responses
     * @return array The statistics for this question which is an assoc array:
     *               - question: Question title
     *               - name: Question name
     *               - type: Question type
     *               - total: Total number of respondents who answered this question
     *               - distribution: Distribution of values (assoc array: value => count)
     *               - average: Average value across all respondents’ answers
     *               - min/max/median: Min/max/median value across all respondents’ answers
     *               - nps: Net promoter score (only for "nps" type)
     * @since 1.0.0
     */
    protected function calculateScoreStatistics(array $question, array $responses): array
    {
        $name = $question['name'];
        $type = $question['type'];

        $values = [];
        foreach ($responses as $response) {
            $response = json_decode($response,true);
            if (!array_key_exists($name, $response)) {
                continue; // skipped
            }

            $value = $response[$name];
            if ($value === null || $value === '') {
                continue; // empty
            }

            if ($type === 'nps') {
                $value = (int) $value;
            } else {
                // rating
                $value = is_numeric($value) ? (int) $value : null;
            }

            if ($value !== null) {
                $values[] = $value;
            }
        }

        $answeredCount = count($values);

        $stats = [
            'question'     => $question['title'] ?? $name,
            'name'         => $name,
            'total'        => $answeredCount,
            'distribution' => array_count_values($values),
            'average'      => $answeredCount ? array_sum($values) / $answeredCount : null,
            'min'          => $answeredCount ? min($values) : null,
            'max'          => $answeredCount ? max($values) : null,
            'median'       => $this->median($values),
        ];

        // Extra: NPS score
        if ($type === 'nps' && $answeredCount > 0) {
            $promoters = 0;
            $passives = 0;
            $detractors = 0;

            foreach ($values as $v) {
                if ($v >= 9) $promoters++;
                elseif ($v >= 7) $passives++;
                else $detractors++;
            }

            $stats['nps'] = round((($promoters - $detractors) / $answeredCount) * 100);
        }

        return $stats;
    }

    /**
     * Calculate statistics for choice-type questions.
     *
     * @param array $question
     * @param array $responses
     * @return array
     * @since 1.0.0
     */
    protected function calculateChoiceStatistics(array $question, array $responses): array
    {
        $name = $question['name'];

        $choices = $question['choices'] ?? [];

        // Normalize choices: ensure value/text
        $choiceMap = [];
        foreach ($choices as $choice) {
            if (is_array($choice)) {
                $value = $choice['value'] ?? $choice['text'];
                $text  = $choice['text'] ?? $value;
            } else {
                $value = (string) $choice;
                $text  = $value;
            }
            $choiceMap[$value] = $text;
        }

        $counts = array_fill_keys(array_keys($choiceMap), 0);
        $otherAnswers = [];
        $noneCount = 0;

        $total = 0;
        foreach ($responses as $response) {
            $response = json_decode($response,true);
            if (!array_key_exists($name, $response)) {
                continue; // skipped
            }

            $total++;
            $value = $response[$name];

            if ($value === null || $value === '') {
                continue;
            }

            // Multi-choice array
            if (is_array($value)) {
                foreach ($value as $v) {
                    if ($v === 'none') {
                        $noneCount++;
                    } elseif (array_key_exists($v, $counts)) {
                        $counts[$v]++;
                    } elseif ($v === 'other') {
                        // Look for comment in {name + "-Comment"}
                        $commentKey = $name . "-Comment";
                        if (!empty($response[$commentKey])) {
                            $otherAnswers[] = $response[$commentKey];
                        } else {
                            $otherAnswers[] = 'Other (no text)';
                        }
                    }
                }
            } else {
                // Single choice
                if ($value === 'none') {
                    $noneCount++;
                } elseif (array_key_exists($value, $counts)) {
                    $counts[$value]++;
                } elseif ($value === 'other') {
                    $commentKey = $name . "-Comment";
                    if (!empty($response[$commentKey])) {
                        $otherAnswers[] = $response[$commentKey];
                    } else {
                        $otherAnswers[] = 'Other (no text)';
                    }
                }
            }
        }

        return [
            'question'     => $question['title'] ?? $name,
            'name'         => $name,
            'total'        => $total,
            'distribution' => $counts,
            'none'         => $noneCount,
            'other'        => $otherAnswers
        ];
    }

    /**
     * Calculate statistics for a boolean question.
     *
     * @param array $question
     * @param array $responses
     * @return array
     * @since 1.0.0
     */
    protected function calculateBooleanStatistics(array $question, array $responses): array
    {
        $name = $question['name'];
        $valueTrue  = $question['valueTrue']  ?? 'true';
        $valueFalse = $question['valueFalse'] ?? 'false';

        $counts = [
            $valueTrue  => 0,
            $valueFalse => 0
        ];

        foreach ($responses as $response) {
            $response = json_decode($response,true);
            if (!array_key_exists($name, $response)) {
                continue;
            }

            $value = $response[$name];

            if ($value === $valueTrue) {
                $counts[$valueTrue]++;
            } elseif ($value === $valueFalse) {
                $counts[$valueFalse]++;
            }
            // if skipped → ignored
        }

        return [
            'question'     => $question['title'] ?? $name,
            'name'         => $name,
            'total'        => array_sum($counts),
            'distribution' => $counts
        ];
    }

    /**
     * Calculate statistics for ranking-type questions
     * @param array $question
     * @param array $responses
     * @return array
     * @since 1.0.0
     */
    protected function calculateRankingStatistics(array $question, array $responses): array
    {
        $name = $question['name'];

        $items = $question['choices'] ?? [];
        $selectToRankEnabled = $question['selectToRankEnabled'] ?? false;

        $rankCounts = []; // [item => [position => count]]
        $rankSums = [];   // for avg rank
        $unrankedCounts = []; // if selectToRankEnabled

        foreach ($items as $item) {
            $value = is_array($item) ? ($item['value'] ?? $item['text']) : (string) $item;
            $rankCounts[$value] = [];
            $rankSums[$value] = 0;
            $unrankedCounts[$value] = 0;
        }

        $totalResponses = 0;

        foreach ($responses as $response) {
            $response = json_decode($response,true);
            if (!array_key_exists($name, $response)) {
                continue;
            }

            $ranking = $response[$name];
            if (!is_array($ranking) || empty($ranking)) {
                continue;
            }

            $totalResponses++;

            // Track which items were ranked in this response
            $rankedSet = [];

            foreach ($ranking as $position => $itemValue) {
                if (!isset($rankCounts[$itemValue])) {
                    $rankCounts[$itemValue] = [];
                    $rankSums[$itemValue] = 0;
                    $unrankedCounts[$itemValue] = 0;
                }
                $rankCounts[$itemValue][$position + 1] = ($rankCounts[$itemValue][$position + 1] ?? 0) + 1;
                $rankSums[$itemValue] += ($position + 1);
                $rankedSet[$itemValue] = true;
            }

            if ($selectToRankEnabled) {
                // Items not ranked by this respondent
                foreach ($items as $item) {
                    $value = is_array($item) ? ($item['value'] ?? $item['text']) : (string) $item;
                    if (!isset($rankedSet[$value])) {
                        $unrankedCounts[$value]++;
                    }
                }
            }
        }

        // Calculate average ranks
        $avgRanks = [];
        foreach ($rankCounts as $item => $positions) {
            $count = array_sum($positions);
            $avgRanks[$item] = $count > 0 ? $rankSums[$item] / $count : null;
        }

        return [
            'question'       => $question['title'] ?? $name,
            'name'           => $name,
            'total'          => $totalResponses,
            'rankCounts'     => $rankCounts,
            'avgRanks'       => $avgRanks,
            'unrankedCounts' => $selectToRankEnabled ? $unrankedCounts : null
        ];
    }

    /**
     * Calculate statistics for a SurveyJS matrix question (with cellType radiogroup or checkbox).
     * Not that this support only the type 'matrix', not 'matrixdropdown', nor 'matrixdynamic'.
     *
     * @param array $question
     * @param array $responses
     * @return array
     * @since 1.0.0
     */
    protected function calculateMatrixStatistics(array $question, array $responses): array
    {
        $name = $question['name'];

        // Normalize row/col definitions into value => label
        $rows = $question['rows'] ?? [];
        $columns = $question['columns'] ?? [];
        $rowMap = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $value = $row['value'] ?? $row['text'];
                $text  = $row['text'] ?? $value;
            } else {
                $value = (string) $row;
                $text  = $value;
            }
            $rowMap[$value] = $text;
        }

        $colMap = [];
        foreach ($columns as $col) {
            if (is_array($col)) {
                $value = $col['value'] ?? $col['text'];
                $text  = $col['text'] ?? $value;
            } else {
                $value = (string) $col;
                $text  = $value;
            }
            $colMap[$value] = $text;
        }

        // Initialize counts[row][col] = 0
        $counts = [];
        foreach ($rowMap as $rVal => $_rText) {
            foreach ($colMap as $cVal => $_cText) {
                $counts[$rVal][$cVal] = 0;
            }
        }

        $totalResponses = 0;

        // Count responses
        foreach ($responses as $response) {
            $response = json_decode($response,true);
            if (!array_key_exists($name, $response)) {
                continue;
            }

            $matrixAns = $response[$name];
            if (!is_array($matrixAns)) {
                continue;
            }

            $totalResponses++;

            foreach ($matrixAns as $rowValue => $cellValue) {
                if ($cellValue === null || $cellValue === '') {
                    continue;
                }

                // Radiogroup style (single choice)
                if (!is_array($cellValue)) {
                    $counts[$rowValue][$cellValue]++;
                }
                else { // Checkbox style (multiple choices)
                    foreach ($cellValue as $colValue) {
                        $counts[$rowValue][$colValue]++;
                    }
                }
            }
        }

        return [
            'question' => $question['title'] ?? $name,
            'name'     => $name,
            'rows'     => $rowMap,
            'columns'  => $colMap,
            'counts'   => $counts,
            'total'    => $totalResponses
        ];
    }

    /**
     * Define styles for survey report statistics
     *
     * @param PhpWord $doc The PhpWord document object
     * @return void
     * @since 1.0.0
     */
    protected function defineReportStyles(PhpWord $doc): void
    {
        // 1. Question title style
        $doc->addParagraphStyle('QuestionTitle', [
            'alignment' => Jc::START,
            'spaceBefore' => Converter::pointToTwip(12),
            'spaceAfter' => Converter::pointToTwip(6),
            'keepNext' => true,
            'keepLines' => true
        ]);

        // 2. Question description style
        $doc->addParagraphStyle('QuestionDescription', [
            'alignment' => Jc::BOTH,
            'spaceAfter' => Converter::pointToTwip(6),
            'indentation' => ['left' => Converter::cmToTwip(0.5)]
        ]);

        // 3. Statistics summary (e.g., "Tổng số phản hồi: 50")
        $doc->addParagraphStyle('StatsSummary', [
            'alignment' => Jc::START,
            'spaceAfter' => Converter::pointToTwip(6)
        ]);

        // 4. Section label (e.g., "Danh sách câu trả lời:")
        $doc->addParagraphStyle('SectionLabel', [
            'alignment' => Jc::START,
            'spaceAfter' => Converter::pointToTwip(3)
        ]);

        // 5. Answer item (indented list item)
        $doc->addParagraphStyle('AnswerItem', [
            'alignment' => Jc::BOTH,
            'spaceAfter' => Converter::pointToTwip(3),
            'indentation' => ['left' => Converter::cmToTwip(1.0)]
        ]);

        // 6. Empty message (e.g., "Không có câu trả lời nào")
        $doc->addParagraphStyle('EmptyMessage', [
            'alignment' => Jc::START,
            'spaceAfter' => Converter::pointToTwip(6),
            'indentation' => ['left' => Converter::cmToTwip(0.5)]
        ]);

        // Font styles for statistics
        $doc->addFontStyle('QuestionTitleFont', [
            'bold' => true,
            'size' => 13
        ]);

        $doc->addFontStyle('QuestionDescFont', [
            'italic' => true,
            'size' => 12,
            'color' => '666666'
        ]);

        $doc->addFontStyle('StatsSummaryFont', [
            'size' => 12
        ]);

        $doc->addFontStyle('SectionLabelFont', [
            'bold' => true,
            'size' => 12
        ]);

        $doc->addFontStyle('AnswerItemFont', [
            'size' => 12
        ]);

        $doc->addFontStyle('EmptyMessageFont', [
            'italic' => true,
            'size' => 12,
            'color' => '999999'
        ]);

        // Table style for statistics tables
        $doc->addTableStyle('StatsTable', [
            'borderSize' => 6,
            'borderColor' => '999999',
            'cellMargin' => 80,
            'alignment' => JcTable::CENTER,
            'width' => 100 * 50 // 100% width in twips percentage
        ]);
    }

    /**
     * Write (append) statistics for a text/comment question to a Word document.
     *
     * @param Section $section The section to append to
     * @param array $question The question definition
     * @param array $data The calculated statistics for this question
     * @param array $option Options for writing the data to Word
     * *          'show_question_label' (bool): Whether to show the question label before each answer
     * *          'question_label' (string): Label for questions, default = 'Câu'
     * *          'question_id' (int|string): The sequence number or id of this question
     * @return void
     * @since 1.0.0
     */
    protected function addTextStatisticsToWord(Section $section, array $question, array $data, array $option = []): void
    {
        // Parse options with defaults
        $showQuestionLabel = $option['show_question_label'] ?? true;
        $questionLabel = $option['question_label'] ? IOHelper::sanitizeTextForWord($option['question_label']) : 'Câu';
        $questionId = $option['question_id'] ?? '';

        // Add question title
        $titleText = '';
        if ($showQuestionLabel && $questionId !== '') {
            $titleText = $questionLabel . ' ' . $questionId . ': ';
        }
        $titleText .= $question['title'] ?? $question['name'];

        $section->addText(IOHelper::sanitizeTextForWord($titleText), 'QuestionTitleFont', 'QuestionTitle');

        // Add question description if exists
        if (!empty($question['description'])) {
            $section->addText(
                IOHelper::sanitizeTextForWord($question['description']),
                'QuestionDescFont',
                'QuestionDescription'
            );
        }

        // Add total responses count
        $totalAnswers = count($data['answers']);
        $section->addText(
            "Tổng số phản hồi: {$totalAnswers}",
            'StatsSummaryFont',
            'StatsSummary'
        );

        // Add all answers
        if ($totalAnswers > 0) {
            $section->addText(
                'Danh sách câu trả lời:',
                'SectionLabelFont',
                'SectionLabel'
            );

            foreach ($data['answers'] as $index => $answer) {
                $section->addText(
                    ($index + 1) . '. ' . IOHelper::sanitizeTextForWord($answer),
                    'AnswerItemFont',
                    'AnswerItem'
                );
            }
        } else {
            $section->addText(
                'Không có câu trả lời nào.',
                'EmptyMessageFont',
                'EmptyMessage'
            );
        }

        // Add spacing after this question
        $section->addTextBreak(1);
    }

    /**
     * Write (append) statistics for a scoring question to a Word document.
     * @param Section $section The section to append to
     * @param array $question The question definition
     * @param array $data The calculated statistics for this question
     * @param array $option Options for writing the data to Word
     *          'show_question_label' (bool): Whether to show the question label before each answer
     *          'question_label' (string): Label for questions, default = 'Câu'
     *          'question_id' (int|string): The sequence number or id of this question
     * @since 1.0.0
     */
    protected function addScoreStatisticsToWord(Section $section, array $question, array $data, array $option=[]):void
    {
        // Parse options with defaults
        $showQuestionLabel = $option['show_question_label'] ?? true;
        $questionLabel = $option['question_label'] ? IOHelper::sanitizeTextForWord($option['question_label']) : 'Câu';
        $questionId = $option['question_id'] ?? '';

        // Get question type
        $type = $question['type'];

        // Add question title
        $titleText = '';
        if ($showQuestionLabel && $questionId !== '') {
            $titleText = $questionLabel . ' ' . $questionId . ': ';
        }
        $titleText .= $question['title'] ?? $question['name'];

        $section->addText(IOHelper::sanitizeTextForWord($titleText), 'QuestionTitleFont', 'QuestionTitle');

        // Add question description if exists
        if (!empty($question['description'])) {
            $section->addText(
                IOHelper::sanitizeTextForWord($question['description']),
                'QuestionDescFont',
                'QuestionDescription'
            );
        }

        // Add statistics summary
        $total = $data['total'];

        $section->addText(
            "Tổng số phản hồi: {$total}",
            'StatsSummaryFont',
            'StatsSummary'
        );

        if ($total > 0) {
            // Format values based on question type
            if ($type === 'nps') {
                $average = $data['average'] !== null ? number_format($data['average'], 1) : 'N/A';
                $min = $data['min'] ?? 'N/A';
                $max = $data['max'] ?? 'N/A';
                $median = $data['median'] !== null ? number_format($data['median'], 1) : 'N/A';
            } else {
                // rating, slider
                $average = $data['average'] !== null ? number_format($data['average'], 2) : 'N/A';
                $min = $data['min'] !== null ? number_format($data['min'], 2) : 'N/A';
                $max = $data['max'] !== null ? number_format($data['max'], 2) : 'N/A';
                $median = $data['median'] !== null ? number_format($data['median'], 2) : 'N/A';
            }

            $section->addText(
                "Giá trị trung bình: {$average}",
                'StatsSummaryFont',
                'StatsSummary'
            );
            $section->addText(
                "Giá trị nhỏ nhất: {$min} | Giá trị lớn nhất: {$max} | Trung vị: {$median}",
                'StatsSummaryFont',
                'StatsSummary'
            );

            // Add NPS-specific statistics
            if ($type === 'nps' && isset($data['nps'])) {
                $npsScore = $data['nps'];
                $section->addText(
                    "Điểm NPS (Net Promoter Score): {$npsScore}",
                    'StatsSummaryFont',
                    'StatsSummary'
                );

                // Calculate NPS breakdown for display
                $distribution = $data['distribution'];
                $promoters = 0;
                $passives = 0;
                $detractors = 0;

                foreach ($distribution as $score => $count) {
                    if ($score >= 9) $promoters += $count;
                    elseif ($score >= 7) $passives += $count;
                    else $detractors += $count;
                }

                $promotersPercent = number_format(($promoters / $total) * 100, 1);
                $passivesPercent = number_format(($passives / $total) * 100, 1);
                $detractorsPercent = number_format(($detractors / $total) * 100, 1);

                $section->addText(
                    "  - Promoters (9-10): {$promoters} ({$promotersPercent}%)",
                    'StatsSummaryFont',
                    'StatsSummary'
                );
                $section->addText(
                    "  - Passives (7-8): {$passives} ({$passivesPercent}%)",
                    'StatsSummaryFont',
                    'StatsSummary'
                );
                $section->addText(
                    "  - Detractors (0-6): {$detractors} ({$detractorsPercent}%)",
                    'StatsSummaryFont',
                    'StatsSummary'
                );
            }

            // Add distribution table
            $section->addText(
                'Phân bố giá trị:',
                'SectionLabelFont',
                'SectionLabel'
            );

            $table = $section->addTable('StatsTable');

            // Table header
            $table->addRow();
            $table->addCell(3000)->addText('Giá trị', 'SectionLabelFont', ['alignment' => Jc::CENTER]);
            $table->addCell(3000)->addText('Số lượng', 'SectionLabelFont', ['alignment' => Jc::CENTER]);
            $table->addCell(3000)->addText('Tỷ lệ (%)', 'SectionLabelFont', ['alignment' => Jc::CENTER]);

            // Table data
            $distribution = $data['distribution'];
            ksort($distribution); // Sort by value

            foreach ($distribution as $value => $count) {
                $percentage = number_format(($count / $total) * 100, 1);

                // Format value display
                $displayValue = ($type === 'nps') ? $value : number_format($value, 2);

                $table->addRow();
                $table->addCell(3000)->addText($displayValue, 'AnswerItemFont', ['alignment' => Jc::CENTER]);
                $table->addCell(3000)->addText($count, 'AnswerItemFont', ['alignment' => Jc::CENTER]);
                $table->addCell(3000)->addText($percentage . '%', 'AnswerItemFont', ['alignment' => Jc::CENTER]);
            }

        } else {
            $section->addText(
                'Không có câu trả lời nào.',
                'EmptyMessageFont',
                'EmptyMessage'
            );
        }

        // Add spacing after this question
        $section->addTextBreak(1);
    }

    /**
     * Write (append) statistics for a choice-based question to a Word document.
     * @param Section $section The section to append to
     * @param array $question The question definition
     * @param array $data The calculated statistics for this question
     * @param array $option Options for writing the data to Word
     *          'show_question_label' (bool): Whether to show the question label before each answer
     *          'question_label' (string): Label for questions, default = 'Câu'
     *          'question_id' (int|string): The sequence number or id of this question
     * @since 1.0.0
     */
    protected function addChoiceStatisticsToWord(Section $section, array $question, array $data, array $option=[]):void
    {
        // Parse options with defaults
        $showQuestionLabel = $option['show_question_label'] ?? true;
        $questionLabel = $option['question_label'] ? IOHelper::sanitizeTextForWord($option['question_label']) : 'Câu';
        $questionId = $option['question_id'] ?? '';

        // Add question title
        $titleText = '';
        if ($showQuestionLabel && $questionId !== '') {
            $titleText = $questionLabel . ' ' . $questionId . ': ';
        }
        $titleText .= $question['title'] ?? $question['name'];

        $section->addText(IOHelper::sanitizeTextForWord($titleText), 'QuestionTitleFont', 'QuestionTitle');

        // Add question description if exists
        if (!empty($question['description'])) {
            $section->addText(
                IOHelper::sanitizeTextForWord($question['description']),
                'QuestionDescFont',
                'QuestionDescription'
            );
        }

        // Calculate total responses
        $total = $data['total'];

        $section->addText(
            "Tổng số phản hồi: {$total}",
            'StatsSummaryFont',
            'StatsSummary'
        );

        if ($total > 0) {
            // Get choice labels from question definition
            $choices = $question['choices'] ?? [];
            $choiceMap = [];
            foreach ($choices as $choice) {
                if (is_array($choice)) {
                    $value = $choice['value'] ?? $choice['text'];
                    $text = $choice['text'] ?? $value;
                } else {
                    $value = (string) $choice;
                    $text = $value;
                }
                $choiceMap[$value] = $text;
            }

            // Add distribution table
            $section->addText(
                'Phân bố lựa chọn:',
                'SectionLabelFont',
                'SectionLabel'
            );


            $table = $section->addTable('StatsTable');

            // Table header
            $table->addRow();
            $table->addCell(5000)->addText('Lựa chọn', 'SectionLabelFont', ['alignment' => Jc::CENTER]);
            $table->addCell(2500)->addText('Số lượng', 'SectionLabelFont', ['alignment' => Jc::CENTER]);
            $table->addCell(2500)->addText('Tỷ lệ (%)', 'SectionLabelFont', ['alignment' => Jc::CENTER]);

            // Table data - regular choices (in original order)
            $distribution = $data['distribution'];
            foreach ($choiceMap as $value => $text) {
                $count = $distribution[$value] ?? 0;
                $percentage = number_format(($count / $total) * 100, 1);

                $table->addRow();
                $table->addCell(5000)->addText(IOHelper::sanitizeTextForWord($text));
                $table->addCell(2500)->addText($count, 'AnswerItemFont', ['alignment' => Jc::CENTER]);
                $table->addCell(2500)->addText($percentage . '%', 'AnswerItemFont', ['alignment' => Jc::CENTER]);
            }

            // Add "None" option if exists
            $noneCount = $data['none'] ?? 0;
            if ($noneCount > 0) {
                $percentage = number_format(($noneCount / $total) * 100, 1);

                $table->addRow();
                $table->addCell(5000)->addText('[Không chọn]', 'AnswerItemFont');
                $table->addCell(2500)->addText($noneCount, 'AnswerItemFont', ['alignment' => Jc::CENTER]);
                $table->addCell(2500)->addText($percentage . '%', 'AnswerItemFont', ['alignment' => Jc::CENTER]);
            }

            // Add "Other" responses if exists
            $otherAnswers = $data['other'] ?? [];
            if (!empty($otherAnswers)) {
                $otherCount = count($otherAnswers);
                $percentage = number_format(($otherCount / $total) * 100, 1);

                $table->addRow();
                $table->addCell(5000)->addText('[Khác]', 'AnswerItemFont');
                $table->addCell(2500)->addText($otherCount, 'AnswerItemFont', ['alignment' => Jc::CENTER]);
                $table->addCell(2500)->addText($percentage . '%', 'AnswerItemFont', ['alignment' => Jc::CENTER]);

                // List all "Other" text responses
                $section->addTextBreak(1);
                $section->addText(
                    'Chi tiết các câu trả lời "Khác":',
                    'SectionLabelFont',
                    'SectionLabel'
                );

                foreach ($otherAnswers as $index => $otherText) {
                    $section->addText(
                        ($index + 1) . '. ' . IOHelper::sanitizeTextForWord($otherText),
                        'AnswerItemFont',
                        'AnswerItem'
                    );
                }
            }

        } else {
            $section->addText(
                'Không có câu trả lời nào.',
                'EmptyMessageFont',
                'EmptyMessage'
            );
        }

        // Add spacing after this question
        $section->addTextBreak(1);
    }
    public function writeReportToWord(PhpWord $doc):void
    {
        $questions = $this->getQuestions($this->modelJson);
        $responses = $this->responses;
        IOHelper::phpWordDefineCommonStyles($doc);
        $this->defineReportStyles($doc);
        $section = IOHelper::phpWordAddCommonSection($doc);
        $section->addText("Báo cáo thống kê ý kiến phản hồi",'Bold', 'Title');
        $section->addText("({$this->title})",'Italic', 'Center');
        $seq=0;
        foreach ($questions as $question) {
            self::checkIfSupported($question, true);
            $seq++;
            $option=[
                'show_question_label'=>true,
                'question_label'=>'Câu số',
                'question_id'=>null
            ];
            switch ($question['type']) {
                case SurveyQuestionType::TEXT:
                case SurveyQuestionType::COMMENT:
                    $data = $this->calculateTextStatistics($question, $responses);
                    $this->addTextStatisticsToWord($section, $question, $data, $option);
                    break;
                case SurveyQuestionType::RATING:
                case SurveyQuestionType::NPS:
                    $data = $this->calculateScoreStatistics($question, $responses);
                    $this->addScoreStatisticsToWord($section, $question, $data, $option);
                    break;
                case SurveyQuestionType::CHECKBOX:
                case SurveyQuestionType::RADIO_GROUP:
                case SurveyQuestionType::DROPDOWN:
                case SurveyQuestionType::TAGBOX:
                case SurveyQuestionType::IMAGE_PICKER:
                    $data = $this->calculateChoiceStatistics($question, $responses);
                    $this->addChoiceStatisticsToWord($section, $question, $data, $option);
                    break;
                case SurveyQuestionType::BOOLEAN:
                    $data = $this->calculateBooleanStatistics($question, $responses);
                    break;
                case SurveyQuestionType::RANKING:
                    $data = $this->calculateRankingStatistics($question, $responses);
                    break;
                case SurveyQuestionType::MATRIX:
                    $data = $this->calculateMatrixStatistics($question, $responses);
            }
        }
    }
}