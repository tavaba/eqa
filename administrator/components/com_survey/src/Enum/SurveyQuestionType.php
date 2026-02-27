<?php

namespace Kma\Component\Survey\Administrator\Enum;

final class SurveyQuestionType
{
    // Textual inputs
    public const TEXT          = 'text';
    public const COMMENT       = 'comment';
    public const MULTIPLE_TEXT = 'multipletext';

    // Choice questions
    public const CHECKBOX      = 'checkbox';
    public const RADIO_GROUP   = 'radiogroup';
    public const DROPDOWN      = 'dropdown';
    public const BOOLEAN       = 'boolean';
    public const TAGBOX        = 'tagbox';
    public const IMAGE_PICKER  = 'imagepicker';

    // Rating / scoring
    public const RATING        = 'rating';
    public const SLIDER        = 'slider';
    public const NPS           = 'nps';

    // Matrix types
    public const MATRIX          = 'matrix';
    public const MATRIX_DROPDOWN = 'matrixdropdown';
    public const MATRIX_DYNAMIC  = 'matrixdynamic';

    // Ordering
    public const RANKING       = 'ranking';
    public const FILE          = 'file';
    public const SIGNATURE_PAD = 'signaturepad';

    // Content / calculated
    public const HTML        = 'html';
    public const EXPRESSION  = 'expression';

    // Containers
    public const PANEL         = 'panel';
    public const PANEL_DYNAMIC = 'paneldynamic';

    // Grouped arrays
    public const TEXT_TYPES = [
        self::TEXT,
        self::COMMENT,
    ];

    public const CHOICE_TYPES = [
        self::CHECKBOX,
        self::RADIO_GROUP,
        self::DROPDOWN,
        self::TAGBOX,
        self::IMAGE_PICKER,
    ];

    public const BOOLEAN_TYPES = [
        self::BOOLEAN,
    ];

    public const SCORE_TYPES = [
        self::RATING,
        self::SLIDER,
        self::NPS,
    ];

    public const MATRIX_TYPES = [
        self::MATRIX,
        self::MATRIX_DROPDOWN,
        self::MATRIX_DYNAMIC,
    ];

    public const ORDERING_TYPES = [
        self::RANKING,
    ];

    public const CONTAINER_TYPES = [
        self::PANEL,
        self::PANEL_DYNAMIC,
    ];

     // Human-friendly labels
    public const LABELS = [
        self::TEXT          => 'Single-line text',
        self::COMMENT       => 'Long text / Comment',
        self::MULTIPLE_TEXT => 'Multiple text inputs',

        self::CHECKBOX      => 'Multiple choice (checkbox)',
        self::RADIO_GROUP   => 'Single choice (radio)',
        self::DROPDOWN      => 'Dropdown',
        self::TAGBOX        => 'Multi-select dropdown (tagbox)',
        self::IMAGE_PICKER  => 'Image picker',

        self::BOOLEAN       => 'Yes / No (boolean)',

        self::RATING        => 'Rating',
        self::NPS           => 'Net Promoter Score',

        self::MATRIX          => 'Matrix (single choice per row)',
        self::MATRIX_DROPDOWN => 'Matrix dropdown',
        self::MATRIX_DYNAMIC  => 'Dynamic matrix',

        self::RANKING       => 'Ranking',

        self::FILE          => 'File upload',
        self::SIGNATURE_PAD => 'Signature pad',

        self::HTML        => 'Static HTML',
        self::EXPRESSION  => 'Calculated expression',

        self::PANEL         => 'Panel (container)',
        self::PANEL_DYNAMIC => 'Dynamic panel',
    ];

    /**
     * Get human-readable label for a question type
     * @since 1.0.0
     */
    public static function getLabel(string $type): string
    {
        return self::LABELS[$type] ?? $type;
    }
}
