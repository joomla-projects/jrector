<?php

namespace Joomla\Rector\Tests\Joomla6\JpathPlatformToJexecRector\Fixture;

// Checking both constants at once is deliberate, so the rule only flags it.
defined('_JEXEC') or defined('JPATH_PLATFORM') or die;

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\JpathPlatformToJexecRector\Fixture;

// TODO jrector: JPATH_PLATFORM is no longer defined in Joomla 6
// Checking both constants at once is deliberate, so the rule only flags it.
defined('_JEXEC') or defined('JPATH_PLATFORM') or die;

?>
