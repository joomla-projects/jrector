<?php

use Joomla\CMS\Session\Session;

$session = Session::getInstance();
$pathway = JPathway::getInstance('site');

?>
-----
<?php

use Joomla\CMS\Session\Session;

$session = \Joomla\CMS\Factory::getApplication()->getSession();
$pathway = \Joomla\CMS\Factory::getApplication()->getPathway();

?>
