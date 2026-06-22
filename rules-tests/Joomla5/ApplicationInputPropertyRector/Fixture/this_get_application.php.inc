<?php

class MyPlugin extends \Joomla\CMS\Plugin\CMSPlugin
{
    public function onContentPrepare(): void
    {
        $app   = $this->getApplication();
        $name  = $app->input->get('name', '', 'string');
        $input = $app->input;
    }
}
-----
<?php

class MyPlugin extends \Joomla\CMS\Plugin\CMSPlugin
{
    public function onContentPrepare(): void
    {
        $app   = $this->getApplication();
        $name  = $app->getInput()->get('name', '', 'string');
        $input = $app->getInput();
    }
}