<?php

use Joomla\CMS\Table\Table;

// Non-standard prefix cannot be reliably resolved to a class FQN — leave unchanged.
$table = Table::getInstance('Custom', 'MyComponentTable');
