<?php

// Does NOT extend CMSPlugin — $this->app and $this->db must not be changed.
class MyController extends \Joomla\CMS\MVC\Controller\BaseController
{
    public function execute(string $task): void
    {
        $app = $this->app;
        $db  = $this->db;
    }
}