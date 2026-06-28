<?php

use Joomla\CMS\Object\LegacyPropertyManagementTrait;

class ExampleView
{
    use LegacyPropertyManagementTrait;

    public function display(): void
    {
        $limit  = $this->get('limit', $this->getDefaultLimit());
        $params = $this->get('params', []);
    }
}
-----
<?php

use Joomla\CMS\Object\LegacyPropertyManagementTrait;

class ExampleView
{
    use LegacyPropertyManagementTrait;

    public function display(): void
    {
        $limit  = $this->limit ?? $this->getDefaultLimit();
        $params = $this->params ?? [];
    }
}
