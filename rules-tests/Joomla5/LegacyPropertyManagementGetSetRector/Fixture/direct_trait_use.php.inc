<?php

use Joomla\CMS\Object\LegacyPropertyManagementTrait;

class ExampleView
{
    use LegacyPropertyManagementTrait;

    public function display(): void
    {
        $title  = $this->get('title', '');
        $id     = $this->get('id', 0);
        $active = $this->get('active');
        $this->set('active', true);
        $this->set('title', 'New Title');
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
        $title  = $this->title ?? '';
        $id     = $this->id ?? 0;
        $active = $this->active ?? null;
        $this->active = true;
        $this->title = 'New Title';
    }
}
