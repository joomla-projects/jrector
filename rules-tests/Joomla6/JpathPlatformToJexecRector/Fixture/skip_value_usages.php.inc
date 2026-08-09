<?php

namespace Joomla\Rector\Tests\Joomla6\JpathPlatformToJexecRector\Fixture;

\defined('_JEXEC') or die;

// A path expression has no generally correct replacement, and MARK_OTHER_USAGES is off.
require_once JPATH_PLATFORM . '/src/foo.php';

// Other JPATH_* constants are not affected.
$base = JPATH_BASE . '/index.php';
