<?php

namespace Joomla\Rector\Tests\Joomla6\Template\DocumentAssetsToWebAssetManagerRector\Fixture;

class TwoMethods
{
    public function renderHead(): void
    {
        $doc = $this->getDocument();
        $doc->addStyleSheet('media/templates/site/foo/css/a.css');
    }

    public function renderFoot(): void
    {
        $doc = $this->getDocument();
        $doc->addScript('media/templates/site/foo/js/b.js');
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

class TwoMethods
{
    public function renderHead(): void
    {
        $doc = $this->getDocument();
        $wa = $doc->getWebAssetManager();
        $wa->registerAndUseStyle('foo.a', 'media/templates/site/foo/css/a.css');
    }

    public function renderFoot(): void
    {
        $doc = $this->getDocument();
        $wa = $doc->getWebAssetManager();
        $wa->registerAndUseScript('foo.b', 'media/templates/site/foo/js/b.js');
    }

    public function getDocument()
    {
        return null;
    }
}

?>
