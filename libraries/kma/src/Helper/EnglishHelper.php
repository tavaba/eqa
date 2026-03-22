<?php
namespace Kma\Library\Kma\Helper;

/**
 * Class EnglishHelper.
 * This class has been created with the help of Claude AI
 * @since 1.0.0
 */
abstract class EnglishHelper
{
    /**
     * Irregular plurals mapping (singular => plural)
     * @since 1.0.0
     */
    private static $irregularPlurals = [
        // Common irregular plurals
        'man' => 'men',
        'woman' => 'women',
        'child' => 'children',
        'tooth' => 'teeth',
        'foot' => 'feet',
        'mouse' => 'mice',
        'goose' => 'geese',
        
        // Animals
        'ox' => 'oxen',
        'sheep' => 'sheep',
        'deer' => 'deer',
        'fish' => 'fish',
        'species' => 'species',
        'series' => 'series',
        
        // Latin/Greek origins
        'datum' => 'data',
        'medium' => 'media',
        'bacterium' => 'bacteria',
        'curriculum' => 'curricula',
        'memorandum' => 'memoranda',
        'addendum' => 'addenda',
        'referendum' => 'referenda',
        'stratum' => 'strata',
        'erratum' => 'errata',
        
        'alumnus' => 'alumni',
        'stimulus' => 'stimuli',
        'focus' => 'foci',
        'radius' => 'radii',
        'cactus' => 'cacti',
        'fungus' => 'fungi',
        'nucleus' => 'nuclei',
        'syllabus' => 'syllabi',
        
        'analysis' => 'analyses',
        'basis' => 'bases',
        'crisis' => 'crises',
        'diagnosis' => 'diagnoses',
        'ellipsis' => 'ellipses',
        'hypothesis' => 'hypotheses',
        'oasis' => 'oases',
        'paralysis' => 'paralyses',
        'parenthesis' => 'parentheses',
        'synopsis' => 'synopses',
        'thesis' => 'theses',
        
        'appendix' => 'appendices',
        'index' => 'indices',
        'matrix' => 'matrices',
        'vertex' => 'vertices',
        'vortex' => 'vortices',
        
        'phenomenon' => 'phenomena',
        'criterion' => 'criteria',
        'automaton' => 'automata',
        
        // Other irregulars
        'person' => 'people',
        'die' => 'dice',
        'penny' => 'pence',
        'quiz' => 'quizzes',
        
        // Compound words
        'brother-in-law' => 'brothers-in-law',
        'father-in-law' => 'fathers-in-law',
        'mother-in-law' => 'mothers-in-law',
        'son-in-law' => 'sons-in-law',
        'daughter-in-law' => 'daughters-in-law',
        'sister-in-law' => 'sisters-in-law',
        'passer-by' => 'passers-by',
        'editor-in-chief' => 'editors-in-chief',
        
        // Unchanged plurals
        'aircraft' => 'aircraft',
        'spacecraft' => 'spacecraft',
        'buffalo' => 'buffalo',
        'bison' => 'bison',
        'moose' => 'moose',
        'salmon' => 'salmon',
        'trout' => 'trout',
        'swine' => 'swine',
        'offspring' => 'offspring',
        'headquarters' => 'headquarters',
        'means' => 'means',
        'news' => 'news',
        'economics' => 'economics',
        'politics' => 'politics',
        'mathematics' => 'mathematics',
        'physics' => 'physics',
        'gymnastics' => 'gymnastics',
        'athletics' => 'athletics',
    ];

    /**
     * Pluralization rules (pattern => replacement)
     * @since 1.0.0
     */
    private static $pluralRules = [
        // Words ending in s, x, z, ch, sh
        '/([sxz]|[cs]h)$/i' => '$1es',
        
        // Words ending in consonant + y
        '/([bcdfghjklmnpqrstvwxyz])y$/i' => '$1ies',

        // Words ending in f or fe
        '/f$/i' => 'ves',
        '/fe$/i' => 'ves',
        
        // Words ending in consonant + o
        '/([bcdfghjklmnpqrstvwxyz])o$/i' => '$1oes',
        
        // Words ending in us (Latin)
        '/us$/i' => 'i',
        
        // Words ending in is (Greek)
        '/is$/i' => 'es',

	    // Words ending in 'ion' or in some consonants + 'on'
        '/([itns])on$/i' => '$1ons',

	    // Words ending in on (Greek)
        '/on$/i' => 'a',
        
        // Words ending in um (Latin)
        '/um$/i' => 'a',
        
        // Default rule - just add s
        '/$/' => 's',
    ];

    /**
     * Exceptions to the f/fe -> ves rule
     * @since 1.0.0
     */
    private static $fExceptions = [
        'belief' => 'beliefs',
        'roof' => 'roofs',
        'chef' => 'chefs',
        'chief' => 'chiefs',
        'cliff' => 'cliffs',
        'proof' => 'proofs',
        'safe' => 'safes',
        'cafe' => 'cafes',
        'giraffe' => 'giraffes',
    ];

    /**
     * Exceptions to the consonant + o -> oes rule
     * @since 1.0.0
     */
    private static $oExceptions = [
        'photo' => 'photos',
        'piano' => 'pianos',
        'halo' => 'halos',
        'soprano' => 'sopranos',
        'pro' => 'pros',
        'solo' => 'solos',
        'auto' => 'autos',
        'memo' => 'memos',
        'casino' => 'casinos',
        'studio' => 'studios',
        'radio' => 'radios',
        'stereo' => 'stereos',
        'video' => 'videos',
        'zoo' => 'zoos',
        'tattoo' => 'tattoos',
        'bamboo' => 'bamboos',
        'taboo' => 'taboos',
    ];

    /**
     * Preserve the original case pattern
     *
     * @param string $original The original word
     * @param string $converted The converted word
     * @return string The converted word with preserved case
     * @since 1.0.0
     */
    private static function preserveCase(string $original, string $converted): string
    {
        if (ctype_upper($original)) {
            return strtoupper($converted);
        }

        if (ctype_upper($original[0])) {
            return ucfirst($converted);
        }

        return $converted;
    }

    /**
     * Convert singular noun to plural form
     *
     * @param string  $singularNoun  The singular noun
     *
     * @return string The plural form
     * @since 1.0.0
     */
    public static function singularToPlural(string $singularNoun): string
    {
        $word = strtolower(trim($singularNoun));
        
        if (empty($word)) {
            return $singularNoun;
        }

        // Check irregular plurals first
        if (isset(self::$irregularPlurals[$word])) {
            return self::preserveCase($singularNoun, self::$irregularPlurals[$word]);
        }

        // Check f/fe exceptions
        if (isset(self::$fExceptions[$word])) {
            return self::preserveCase($singularNoun, self::$fExceptions[$word]);
        }

        // Check consonant + o exceptions
        if (isset(self::$oExceptions[$word])) {
            return self::preserveCase($singularNoun, self::$oExceptions[$word]);
        }

        // Apply pluralization rules
        foreach (self::$pluralRules as $pattern => $replacement) {
            if (preg_match($pattern, $word)) {
                $plural = preg_replace($pattern, $replacement, $word);
                return self::preserveCase($singularNoun, $plural);
            }
        }

        return $singularNoun;
    }

    /**
     * Convert plural noun to singular form
     *
     * @param string $pluralNoun The plural noun
     * @return string The singular form
     * @since 1.0.0
     */
    public static function pluralToSingular(string $pluralNoun): string
    {
        $word = strtolower(trim($pluralNoun));
        
        if (empty($word)) {
            return $pluralNoun;
        }

        // Check reverse irregular plurals
        $reversedIrregulars = array_flip(self::$irregularPlurals);
        if (isset($reversedIrregulars[$word])) {
            return self::preserveCase($pluralNoun, $reversedIrregulars[$word]);
        }

        // Check reverse f/fe exceptions
        $reversedFExceptions = array_flip(self::$fExceptions);
        if (isset($reversedFExceptions[$word])) {
            return self::preserveCase($pluralNoun, $reversedFExceptions[$word]);
        }

        // Check reverse consonant + o exceptions
        $reversedOExceptions = array_flip(self::$oExceptions);
        if (isset($reversedOExceptions[$word])) {
            return self::preserveCase($pluralNoun, $reversedOExceptions[$word]);
        }

        // Apply singularization rules
        $singularRules = [
            // Words ending in ies -> y
            '/ies$/i' => 'y',
            
            // Words ending in ves -> f or fe
            '/ves$/i' => 'f',
            
            // Words ending in ses, xes, zes, ches, shes -> remove es
            '/([^o][sxz]|[cs]h)es$/i' => '$1',
            
            // Words ending in oes -> o (for consonant + o words)
            '/([bcdfghjklmnpqrstvwxyz])oes$/i' => '$1o',
            
            // Latin/Greek endings
            '/i$/i' => 'us',
            '/([^aeious])es$/i' => '$1is',
            '/a$/i' => 'um',
            
            // General case - remove s
            '/s$/i' => '',
        ];

        foreach ($singularRules as $pattern => $replacement) {
            if (preg_match($pattern, $word)) {
                // Special handling for ves -> fe/f
                if ($pattern === '/ves$/i') {
                    $base = preg_replace($pattern, '', $word);
                    // Check if it should be 'fe' ending
                    $feWords = ['wife', 'knife', 'life', 'strife'];
                    if (in_array($base . 'fe', $feWords)) {
                        $singular = $base . 'fe';
                    } else {
                        $singular = $base . 'f';
                    }
                } else {
                    $singular = preg_replace($pattern, $replacement, $word);
                }
                return self::preserveCase($pluralNoun, $singular);
            }
        }

        return $pluralNoun;
    }
}
