<?php

namespace Joomla\Rector\Tests\Joomla6\Template\DocumentAssetsToWebAssetManagerRector\Fixture;

use Joomla\CMS\HTML\HTMLHelper;

class HtmlHelperRenderer
{
    public function render(): void
    {
        HTMLHelper::_('stylesheet', 'media/com_example/css/admin.css');
        HTMLHelper::_('script', 'media/com_example/js/admin.js');
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Template\DocumentAssetsToWebAssetManagerRector\Fixture;

use Joomla\CMS\HTML\HTMLHelper;

class HtmlHelperRenderer
{
    public function render(): void
    {
        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('com_example.admin', 'media/com_example/css/admin.css');
        $wa->registerAndUseScript('com_example.admin', 'media/com_example/js/admin.js');
    }
}

?>
