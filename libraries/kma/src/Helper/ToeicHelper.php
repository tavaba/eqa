<?php
namespace Kma\Library\Kma\Helper;
defined('_JEXEC') or die();

/**
 * Helper class for TOEIC score calculation.
 *
 * Converts raw section scores (number of correct answers, 0–100) into
 * scaled scores and total TOEIC score according to the official
 * score conversion table used by KMA.
 *
 * Usage example:
 * <code>
 *   $total     = ToeicHelper::getTotalScore(85, 72);
 *   $breakdown = ToeicHelper::getScoreBreakdown(85, 72);
 *   // $breakdown['listening_scaled'] => 440
 *   // $breakdown['reading_scaled']   => 355
 *   // $breakdown['total']            => 795
 * </code>
 *
 * @since 1.0.0
 */
abstract class ToeicHelper
{
    // =========================================================================
    // Score conversion tables (index = raw score, value = scaled score)
    // Source: study4_com_scoring.xlsx, sheet ScoreCalculator
    // =========================================================================

    /**
     * Listening scaled score table.
     * Index 0–100 maps raw score → scaled score (0–495).
     *
     * @var int[]
     * @since 1.0.0
     */
    private const array STUDY4_LISTENING_TABLE = [
          0,  15,  20,  25,  30,  35,  40,  45,  50,  55,  // 0–9
         60,  65,  70,  75,  80,  85,  90,  95, 100, 105,  // 10–19
        110, 115, 120, 125, 130, 135, 140, 145, 150, 155,  // 20–29
        160, 165, 170, 175, 180, 185, 190, 195, 200, 205,  // 30–39
        210, 215, 220, 225, 230, 235, 240, 245, 250, 255,  // 40–49
        260, 265, 270, 275, 280, 285, 290, 295, 300, 305,  // 50–59
        310, 315, 320, 325, 330, 335, 340, 345, 350, 355,  // 60–69
        360, 365, 370, 375, 380, 385, 395, 400, 405, 410,  // 70–79
        415, 420, 425, 430, 435, 440, 445, 450, 455, 460,  // 80–89
        465, 470, 475, 480, 485, 490, 495, 495, 495, 495,  // 90–99
        495,                                                 // 100
    ];

    /**
     * Reading scaled score table.
     * Index 0–100 maps raw score → scaled score (0–495).
     *
     * @var int[]
     * @since 1.0.0
     */
    private const array STUDY4_READING_TABLE = [
          0,   5,   5,  10,  15,  20,  25,  30,  35,  40,  // 0–9
         45,  50,  55,  60,  65,  70,  75,  80,  85,  90,  // 10–19
         95, 100, 105, 110, 115, 120, 125, 130, 135, 140,  // 20–29
        145, 150, 155, 160, 165, 170, 175, 180, 185, 190,  // 30–39
        195, 200, 205, 210, 215, 220, 225, 230, 235, 240,  // 40–49
        245, 250, 255, 260, 265, 270, 275, 280, 285, 290,  // 50–59
        295, 300, 305, 310, 315, 320, 325, 330, 335, 340,  // 60–69
        345, 350, 355, 360, 365, 370, 375, 380, 385, 390,  // 70–79
        395, 400, 405, 410, 415, 420, 425, 430, 435, 440,  // 80–89
        445, 450, 455, 460, 465, 470, 475, 480, 485, 490,  // 90–99
        495,                                                 // 100
    ];

	// =========================================================================
	// Score conversion tables (index = raw score, value = scaled score)
	// Source: Longman TOEIC 4-th edition
	// =========================================================================

	/**
	 * Listening scaled score table.
	 * Index 0–100 maps raw score → scaled score (0–495).
	 *
	 * @var int[]
	 * @since 1.0.0
	 */
	private const array LONGMAN4_LISTENING_TABLE = [
		5,   5,   5,   5,   5,   5,   5,  10,  15,  20,  // 0–9
		25,  30,  35,  40,  45,  50,  55,  60,  65,  70,  // 10–19
		75,  80,  85,  90,  95, 100, 110, 115, 120, 125,  // 20–29
		130, 135, 140, 145, 150, 160, 165, 170, 175, 180,  // 30–39
		185, 190, 195, 200, 210, 215, 220, 230, 240, 245,  // 40–49
		250, 255, 260, 270, 275, 280, 290, 295, 300, 310,  // 50–59
		315, 320, 325, 330, 340, 345, 350, 360, 365, 370,  // 60–69
		380, 385, 390, 395, 400, 405, 410, 420, 425, 430,  // 70–79
		440, 445, 450, 460, 465, 470, 475, 480, 485, 490,  // 80–89
		495, 495, 495, 495, 495, 495, 495, 495, 495, 495,  // 90–99
		495,                                                 // 100
	];

	/**
	 * Reading scaled score table.
	 * Index 0–100 maps raw score → scaled score (0–495).
	 *
	 * @var int[]
	 * @since 1.0.0
	 */
	private const array LONGMAN4_READING_TABLE = [
		5,   5,   5,   5,   5,   5,   5,   5,   5,   5,  // 0–9
		5,   5,   5,   5,   5,   5,  10,  15,  20,  25,  // 10–19
		30,  35,  40,  45,  50,  60,  65,  70,  80,  85,  // 20–29
		90,  95, 100, 110, 115, 120, 125, 130, 140, 145,  // 30–39
		150, 160, 165, 170, 175, 180, 190, 195, 200, 210,  // 40–49
		215, 220, 225, 230, 235, 240, 250, 255, 260, 265,  // 50–59
		270, 280, 285, 290, 300, 305, 310, 320, 325, 330,  // 60–69
		335, 340, 350, 355, 360, 365, 370, 380, 385, 390,  // 70–79
		395, 400, 405, 410, 415, 420, 425, 430, 435, 445,  // 80–89
		450, 455, 465, 470, 480, 485, 490, 495, 495, 495,  // 90–99
		495,                                                 // 100
	];

	// ========================================================================
	// Lựa chọn bảng quy đổi điểm
	// ========================================================================
	private const array LISTENING_TABLE = self::LONGMAN4_LISTENING_TABLE;
	private const array READING_TABLE = self::LONGMAN4_READING_TABLE;

	// =========================================================================
    // Public API
    // =========================================================================

    /**
     * Get the scaled Listening score from a raw score.
     *
     * @param  int  $rawScore  Number of correct answers in the Listening section (0–100).
     *
     * @return int  Scaled Listening score (0–495).
     *
     * @throws \InvalidArgumentException  If $rawScore is outside the range [0, 100].
     * @since  1.0.0
     */
    public static function getListeningScaledScore(int $rawScore): int
    {
        self::assertValidRawScore($rawScore);

        return self::LISTENING_TABLE[$rawScore];
    }

    /**
     * Get the scaled Reading score from a raw score.
     *
     * @param  int  $rawScore  Number of correct answers in the Reading section (0–100).
     *
     * @return int  Scaled Reading score (0–495).
     *
     * @throws \InvalidArgumentException  If $rawScore is outside the range [0, 100].
     * @since  1.0.0
     */
    public static function getReadingScaledScore(int $rawScore): int
    {
        self::assertValidRawScore($rawScore);

        return self::READING_TABLE[$rawScore];
    }

    /**
     * Get the total TOEIC score from raw Listening and Reading scores.
     *
     * Total score = Scaled Listening + Scaled Reading (range: 0–990).
     *
     * @param  int  $listeningRaw  Number of correct answers in Listening (0–100).
     * @param  int  $readingRaw    Number of correct answers in Reading (0–100).
     *
     * @return int  Total TOEIC score (0–990).
     *
     * @throws \InvalidArgumentException  If either raw score is outside [0, 100].
     * @since  1.0.0
     */
    public static function getTotalScore(int $listeningRaw, int $readingRaw): int
    {
        return self::getListeningScaledScore($listeningRaw)
             + self::getReadingScaledScore($readingRaw);
    }

    /**
     * Get a full score breakdown for a TOEIC result.
     *
     * Returns an associative array with the following keys:
     *   - listening_raw     (int)  : raw Listening score (0–100)
     *   - reading_raw       (int)  : raw Reading score (0–100)
     *   - listening_scaled  (int)  : scaled Listening score (0–495)
     *   - reading_scaled    (int)  : scaled Reading score (0–495)
     *   - total             (int)  : total TOEIC score (0–990)
     *
     * @param  int  $listeningRaw  Number of correct answers in Listening (0–100).
     * @param  int  $readingRaw    Number of correct answers in Reading (0–100).
     *
     * @return array{
     *     listening_raw: int,
     *     reading_raw: int,
     *     listening_scaled: int,
     *     reading_scaled: int,
     *     total: int
     * }
     *
     * @throws \InvalidArgumentException  If either raw score is outside [0, 100].
     * @since  1.0.0
     */
    public static function getScoreBreakdown(int $listeningRaw, int $readingRaw): array
    {
        $listeningScaled = self::getListeningScaledScore($listeningRaw);
        $readingScaled   = self::getReadingScaledScore($readingRaw);

        return [
            'listening_raw'    => $listeningRaw,
            'reading_raw'      => $readingRaw,
            'listening_scaled' => $listeningScaled,
            'reading_scaled'   => $readingScaled,
            'total'            => $listeningScaled + $readingScaled,
        ];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Validate that a raw score is within the allowed range [0, 100].
     *
     * @param  int  $rawScore  The raw score to validate.
     *
     * @return void
     *
     * @throws \InvalidArgumentException  If $rawScore is outside [0, 100].
     * @since  1.0.0
     */
    private static function assertValidRawScore(int $rawScore): void
    {
        if ($rawScore < 0 || $rawScore > 100) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Raw score must be between 0 and 100, %d given.',
                    $rawScore
                )
            );
        }
    }
}
