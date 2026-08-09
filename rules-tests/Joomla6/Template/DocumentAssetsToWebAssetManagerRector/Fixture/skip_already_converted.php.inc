<?php

namespace Joomla\Rector\Tests\Joomla6\Template\DocumentAssetsToWebAssetManagerRector\Fixture;

// Idempotence: nothing left to convert.
class AlreadyConverted
{
    public function render(): void
    {
        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('foo.template', 'media/templates/site/foo/css/template.css');
        $wa->addInlineScript('console.log("hi");');
    }

    public function getDocument()
    {
        return null;
    }
}
