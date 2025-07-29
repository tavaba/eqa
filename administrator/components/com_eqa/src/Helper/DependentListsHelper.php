<?php
namespace Kma\Component\Eqa\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/**
 * Helper class for dependent lists functionality
 */
abstract class DependentListsHelper
{
	/**
	 * Generate the JavaScript initialization script
	 *
	 * @param   array  $config  JavaScript configuration
	 *
	 * @return  string  JavaScript code
	 */
	protected static function generateInitScript(array $config)
	{
		$jsonConfig = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		return "
        jQuery(document).ready(function($) {
            DependentLists.init({$jsonConfig});
        });
        ";
	}

    /**
     * Load dependent lists assets and initialize
     *
     * @param   WebAssetManager  $wa       Web Asset Manager instance
     * @param   array           $config   Configuration array
     * 
     * Configuration array structure:
     * [
     *     'prefix' => 'jform',
     *     'list1' => 'class_id',
     *     'list2' => 'learner_id',
     *     'prompt2' => 'COM_EQA_SELECT_LEARNER',
     *     'url2' => 'index.php?option=com_eqa&task=fixer.getClassLearners',
     *     'list3' => 'subject_id' (optional),
     *     'prompt3' => 'COM_EQA_SELECT_SUBJECT' (optional),
     *     'url3' => 'index.php?option=com_eqa&task=getSubjects' (optional),
     *     'loadingText' => 'COM_EQA_LOADING' (optional),
     *     'emptyText' => 'COM_EQA_NO_DATA_FOUND' (optional)
     * ]
     *
     * @return  void
     */
    public static function setup(WebAssetManager $wa, array $config)
    {
        // Validate required configuration
        $required = ['prefix', 'list1', 'list2', 'prompt2', 'url2'];
        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new \InvalidArgumentException("Missing required configuration: {$key}");
            }
        }

        // Set default values
        $config = array_merge([
            'loadingText' => 'Đang tải dữ liệu...',
            'emptyText' => 'Không có dữ liệu'
        ], $config);

        // Use the dependent lists script
        $wa->useScript('com_eqa.dependent_lists');

        // Build the JavaScript configuration
        $jsConfig = [
            'prefix' => $config['prefix'],
            'list1' => $config['list1'],
            'list2' => $config['list2'],
            'prompt2' => $config['prompt2'],
            'url2' => $config['url2'],
            'loadingText' => $config['loadingText'],
            'emptyText' => $config['emptyText']
        ];

        // Add list3 configuration if provided
        if (!empty($config['list3'])) {
            if (empty($config['prompt3']) || empty($config['url3'])) {
                throw new \InvalidArgumentException("When list3 is provided, prompt3 and url3 are required");
            }
            
            $jsConfig['list3'] = $config['list3'];
            $jsConfig['prompt3'] = $config['prompt3'];
            $jsConfig['url3'] = $config['url3'];
        }

        // Generate the initialization script
        $initScript = self::generateInitScript($jsConfig);

        // Add inline script
        $wa->addInlineScript(
            $initScript,
            [],
            ['type' => 'application/javascript'],
            ['com_eqa.dependent_lists']
        );
    }

    /**
     * Quick setup for 2-level dependent lists
     *
     * @param   WebAssetManager  $wa        Web Asset Manager instance
     * @param   string          $prefix    Form prefix (e.g., 'jform')
     * @param   string          $list1     First list field name
     * @param   string          $list2     Second list field name
     * @param   string          $prompt2   Prompt for list2 (language constant)
     * @param   string          $url2      AJAX URL for list2
     * 
     * @return  void
     */
    public static function setup2Level(WebAssetManager $wa, $prefix, $list1, $list2, $prompt2, $url2)
    {
        self::setup($wa, [
            'prefix' => $prefix,
            'list1' => $list1,
            'list2' => $list2,
            'prompt2' => $prompt2,
            'url2' => $url2
        ]);
    }

    /**
     * Quick setup for 3-level dependent lists
     *
     * @param   WebAssetManager  $wa        Web Asset Manager instance
     * @param   string          $prefix    Form prefix (e.g., 'jform')
     * @param   string          $list1     First list field name
     * @param   string          $list2     Second list field name
     * @param   string          $list3     Third list field name
     * @param   string          $prompt2   Prompt for list2 (language constant)
     * @param   string          $prompt3   Prompt for list3 (language constant)
     * @param   string          $url2      AJAX URL for list2
     * @param   string          $url3      AJAX URL for list3
     * 
     * @return  void
     */
    public static function setup3Level(WebAssetManager $wa, $prefix, $list1, $list2, $list3, $prompt2, $prompt3, $url2, $url3)
    {
        self::setup($wa, [
            'prefix' => $prefix,
            'list1' => $list1,
            'list2' => $list2,
            'list3' => $list3,
            'prompt2' => $prompt2,
            'prompt3' => $prompt3,
            'url2' => $url2,
            'url3' => $url3
        ]);
    }
}