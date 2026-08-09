<?php

/** @var \stdClass $module */
/** @var \Joomla\CMS\Application\CMSApplicationInterface $app */
/** @var \Joomla\Input\Input $input */
/** @var \Joomla\Registry\Registry $params */
/** @var string $template */
/** @var \stdClass[] $items */
\defined('_JEXEC') or die;

echo $params->get('title');
