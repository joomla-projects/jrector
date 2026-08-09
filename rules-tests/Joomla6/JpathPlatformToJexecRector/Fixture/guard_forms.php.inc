<?php

namespace Joomla\Rector\Tests\Joomla6\JpathPlatformToJexecRector\Fixture;

defined('JPATH_PLATFORM') or die;
\defined('JPATH_PLATFORM') or die;
defined('JPATH_PLATFORM') || die();

if (!defined('JPATH_PLATFORM')) {
    die();
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\JpathPlatformToJexecRector\Fixture;

defined('_JEXEC') or die;
\defined('_JEXEC') or die;
defined('_JEXEC') || die();

if (!defined('_JEXEC')) {
    die();
}

?>
