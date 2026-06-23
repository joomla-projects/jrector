<?php

use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentExample extends CMSPlugin
{
    public function onContentPrepare(): void
    {
        $app   = $this->app;
        $this->app->enqueueMessage('test');
        $query = $this->db->getQuery(true);
        $this->db->setQuery($query);
    }
}
-----
<?php

use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentExample extends CMSPlugin
{
    public function onContentPrepare(): void
    {
        $app   = $this->getApplication();
        $this->getApplication()->enqueueMessage('test');
        $query = $this->getDatabase()->getQuery(true);
        $this->getDatabase()->setQuery($query);
    }
}