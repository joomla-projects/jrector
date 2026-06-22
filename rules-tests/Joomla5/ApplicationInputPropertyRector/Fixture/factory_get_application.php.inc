<?php

use Joomla\CMS\Factory;

class MyController extends \Joomla\CMS\MVC\Controller\BaseController
{
    public function execute(string $task): void
    {
        $app   = Factory::getApplication();
        $name  = $app->input->get('name', '', 'string');
        $input = $app->input;
    }

    public function save(): void
    {
        $app  = JFactory::getApplication();
        $data = $app->input->getArray();
    }

    public function view(): void
    {
        $app = \Joomla\CMS\Factory::getApplication();
        $id  = $app->input->getInt('id');
    }
}
-----
<?php

use Joomla\CMS\Factory;

class MyController extends \Joomla\CMS\MVC\Controller\BaseController
{
    public function execute(string $task): void
    {
        $app   = Factory::getApplication();
        $name  = $app->getInput()->get('name', '', 'string');
        $input = $app->getInput();
    }

    public function save(): void
    {
        $app  = JFactory::getApplication();
        $data = $app->getInput()->getArray();
    }

    public function view(): void
    {
        $app = \Joomla\CMS\Factory::getApplication();
        $id  = $app->getInput()->getInt('id');
    }
}