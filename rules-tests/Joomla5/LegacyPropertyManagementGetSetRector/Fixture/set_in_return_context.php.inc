<?php

use Joomla\CMS\Object\LegacyPropertyManagementTrait;

class ExampleView
{
    use LegacyPropertyManagementTrait;

    public function setAndGetTitle(string $value): string
    {
        $this->set('title', $value);

        return $this->get('title', '');
    }
}
-----
<?php

use Joomla\CMS\Object\LegacyPropertyManagementTrait;

class ExampleView
{
    use LegacyPropertyManagementTrait;

    public function setAndGetTitle(string $value): string
    {
        $this->title = $value;

        return $this->title ?? '';
    }
}
