<?php

namespace Joomla\Rector\Tests\Joomla6\Template\DocumentAssetsToWebAssetManagerRector\Fixture;

class AlreadyHasManager
{
    public function render(): void
    {
        $doc = $this->getDocument();
        $wa  = $doc->getWebAssetManager();

        $doc->addStyleSheet('media/templates/site/foo/css/extra.css');
    }

    public function getDocument()
    {
        return null;
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Template\DocumentAssetsToWebAssetManagerRector\Fixture;

class AlreadyHasManager
{
    public function render(): void
    {
        $doc = $this->getDocument();
        $wa  = $doc->getWebAssetManager();

        $wa->registerAndUseStyle('foo.extra', 'media/templates/site/foo/css/extra.css');
    }

    public function getDocument()
    {
        return null;
    }
}

?>
