<?php

namespace Joomla\Rector\Tests\Joomla6\Template\DocumentAssetsToWebAssetManagerRector\Fixture;

use Joomla\CMS\HTML\HTMLHelper;

class Skips
{
    public function render($path): void
    {
        $doc = $this->getDocument();

        // A generated name from a dynamic path is not reliable.
        $doc->addStyleSheet($path);
        $doc->addScript('media/' . $path . '/x.js');

        // addScriptOptions() is not deprecated in Joomla 6.
        $doc->addScriptOptions('com_example', ['a' => 1]);

        // behavior.* and bootstrap.* need the core asset names and are out of scope.
        HTMLHelper::_('behavior.core');
        HTMLHelper::_('bootstrap.tooltip');

        // The option array of HTMLHelper does not map onto the WebAssetManager options.
        HTMLHelper::_('stylesheet', 'media/com_example/css/a.css', ['version' => 'auto']);
    }

    public function getDocument()
    {
        return null;
    }
}
