<?php
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class plgSystemGantryadmin extends JPlugin
{
    protected $app;
    protected $styles;

    public function __construct(&$subject, $config = array())
    {
        $this->app = JFactory::getApplication();

        // Only initialize plugin in admin.
        if ($this->app->isAdmin())
        {
            parent::__construct($subject, $config);
        }
    }

    /**
     * Re-route Gantry templates to Gantry Administration component.
     */
    public function onAfterRoute()
    {
        $option = $this->app->input->getCmd('option');
        $task   = $this->app->input->getCmd('task');

        if ($option == 'com_templates' && $task)
        {
            $id = $this->app->input->getInt('id');
            if (!$id) {
                $pks = $this->app->input->post->get('cid', array(), 'array');
                $id = array_shift($pks);
            }

            if ($id) {
                $styles = $this->getStyles();

                if (isset($styles[$id])) {
                    $this->setRequestOption('option', 'com_gantryadmin');
                }
            }
        }
    }

    /**
     * Convert links in com_templates to point into Gantry Administrator component.
     */
    public function onAfterRender()
    {
        $document = JFactory::getDocument();
        $type   = $document->getType();

        $option = $this->app->input->getString('option');
        $view   = $this->app->input->getString('view', 'styles');
        $task   = $this->app->input->getString('task');

        if ($option == 'com_templates' && $view == 'styles' && !$task && $type == 'html')
        {
            $this->styles = $this->getStyles();

            $body = preg_replace_callback('/(<a\s[^>]*href=")([^"]*)("[^>]*>)(.*)(<\/a>)/siU', [$this, 'appendHtml'], $this->app->getBody());

            $this->app->setBody($body);
        }
    }

    /**
     * @param string $key
     * @param string $value
     */
    private function setRequestOption($key, $value)
    {
        $this->app->input->set($key, $value);
        $this->app->input->get->set($key, $value);

        if (class_exists('JRequest'))
        {
            JRequest::setVar($key, $value, 'GET');
        }
    }

    /**
     * @param array $matches
     * @return string
     */
    private function appendHtml(array $matches)
    {
        $html = $matches[0];

        if (strpos($matches[2], 'task=style.edit'))
        {
            $uri = new JUri($matches[2]);
            $id = $uri->getVar('id');

            if ($id && $uri->getVar('option') == 'com_templates' && isset($this->styles[$id]))
            {
                $uri->setVar('option', 'com_gantryadmin');
                $html = $matches[1] . $uri . $matches[3] . $matches[4] . $matches[5];

                if ($this->styles[$id])
                {
                    $html .= '<span style="white-space:nowrap;margin:0 10px;background:#d63c1f;color:#fff;padding:2px 4px;font-family:Helvetica,Arial,sans-serif;border-radius:3px;">&#10029; Master</span>';
                }
                else
                {
                    $html .= '<span style="white-space:nowrap;margin:0 10px;background:#999;color:#fff;padding:2px 4px;font-family:Helvetica,Arial,sans-serif;border-radius:3px;">Override</span>';
                }
            }
        }

        return $html;
    }

    /**
     * @return array
     */
    private function getStyles()
    {
        $tag   = JFactory::getLanguage()->getTag();
        $cache = JFactory::getCache('com_templates', '');
        $list = $cache->get('gantry-templates-' . $tag);

        if ($list === false)
        {
            // Load styles
            $db    = JFactory::getDbo();
            $query = $db
                ->getQuery(true)
                ->select('s.id, s.template, s.params')
                ->from('#__template_styles as s')
                ->where('s.client_id = 0')
                ->where('e.enabled = 1')
                ->leftJoin('#__extensions as e ON e.element=s.template AND e.type=' . $db->quote('template') . ' AND e.client_id=s.client_id');

            $db->setQuery($query);
            $templates = (array) $db->loadObjectList();

            $list = array();

            foreach ($templates as $template)
            {
                if (file_exists(JPATH_SITE . '/templates/' . $template->template . '/includes/gantry.php'))
                {
                    $params = new JRegistry;
                    $params->loadString($template->params);

                    $list[$template->id] = ($params->get('master') == 'true');
                }
            }

            $cache->store($list, 'gantry-templates-' . $tag);
        }

        return $list;
    }
}
