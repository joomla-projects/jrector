<?php

class PlgSystemExample extends \Joomla\CMS\Plugin\CMSPlugin
{
    public function onAfterInitialise(): void
    {
        $lang = $this->app->getLanguage();
        $db   = $this->db;
    }
}
-----
<?php

class PlgSystemExample extends \Joomla\CMS\Plugin\CMSPlugin
{
    public function onAfterInitialise(): void
    {
        $lang = $this->getApplication()->getLanguage();
        $db   = $this->getDatabase();
    }
}