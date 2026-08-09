<?php

namespace Joomla\Rector\Tests\Joomla6\Template\DocumentAssetsToWebAssetManagerRector\Fixture;

class TemplateRenderer
{
    public function render(): void
    {
        $doc = $this->getDocument();
        $doc->addStyleSheet('media/templates/site/foo/css/template.css');
        $doc->addScript('media/templates/site/foo/js/template.js');
        $doc->addScriptDeclaration('console.log("hi");');
        $doc->addStyleDeclaration('body { color: red; }');
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

class TemplateRenderer
{
    public function render(): void
    {
        $doc = $this->getDocument();
        $wa = $doc->getWebAssetManager();
        $wa->registerAndUseStyle('foo.template', 'media/templates/site/foo/css/template.css');
        $wa->registerAndUseScript('foo.template', 'media/templates/site/foo/js/template.js');
        $wa->addInlineScript('console.log("hi");');
        $wa->addInlineStyle('body { color: red; }');
    }

    public function getDocument()
    {
        return null;
    }
}

?>
