<?php
namespace Kma\Component\Survey\Site\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Menu\AbstractMenu;

class Router extends RouterView
{
    /**
     * Constructor
     *
     * @param   CMSApplication  $app   The application object
     * @param   AbstractMenu    $menu  The menu object to work with
     * @since   1.0.0
     */
    public function __construct(CMSApplication $app, AbstractMenu $menu)
    {
        parent::__construct($app, $menu);

        // View: surveys (list of surveys)
        $surveys = new RouterViewConfiguration('surveys');
        $this->registerView($surveys);

        // View: survey (single survey form)
        $survey = new RouterViewConfiguration('survey');
        $survey->setKey('id'); // can be survey_id or token
        $this->registerView($survey);
    }

    /**
     * Build SEF route segments from query vars
     * @param   array  &$query  The query array
     * @return  array  The URL segments
     * @since   1.0.0
     */
    public function build(&$query)
    {
        $segments = [];

        if (isset($query['view']))
        {
            $segments[] = $query['view'];
            unset($query['view']);
        }

        if (isset($query['id']))
        {
            $segments[] = $query['id'];
            unset($query['id']);
        }
        return $segments;
    }

    /**
     * Parse SEF URL segments back into query vars
     * @param   array  &$segments  The URL segments
     * @return  array  The query array
     * @since   1.0.0
     */
    public function parse(&$segments)
    {
        $vars = [];

        if (!empty($segments[0]))
        {
            $vars['view'] = $segments[0];

            if ($segments[0] === 'survey' && !empty($segments[1]))
            {
                $vars['id'] = $segments[1];
            }
        }
        return $vars;
    }}