<?php

namespace Joomla\Rector\Tests\Joomla7\GetInstanceToContainerRector\Fixture;

use Joomla\CMS\Pathway\Pathway;
use Joomla\CMS\Session\Session;

class PlainService
{
    public function run()
    {
        $pathway = Pathway::getInstance('site');
        $session = Session::getInstance();
        $token   = \Joomla\CMS\Session\Session::getInstance()->getFormToken();

        return JSession::getInstance();
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla7\GetInstanceToContainerRector\Fixture;

use Joomla\CMS\Pathway\Pathway;
use Joomla\CMS\Session\Session;

class PlainService
{
    public function run()
    {
        $pathway = \Joomla\CMS\Factory::getApplication()->getPathway();
        $session = \Joomla\CMS\Factory::getApplication()->getSession();
        $token   = \Joomla\CMS\Factory::getApplication()->getSession()->getFormToken();

        return \Joomla\CMS\Factory::getApplication()->getSession();
    }
}

?>
