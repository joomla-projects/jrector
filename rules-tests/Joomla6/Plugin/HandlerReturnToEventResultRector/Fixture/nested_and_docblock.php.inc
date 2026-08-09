<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\HandlerReturnToEventResultRector\Fixture;

use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentNested extends CMSPlugin
{
    /**
     * Validates the item.
     *
     * @param   BeforeSaveEvent  $event  The event.
     *
     * @return  boolean
     */
    public function onContentBeforeSave(BeforeSaveEvent $event): bool
    {
        foreach ($event->getItem()->tags as $tag) {
            if ($tag === 'blocked') {
                return false;
            }
        }

        return $this->check($event->getContext());
    }

    private function check(string $context)
    {
        return $context !== '';
    }
}

?>
-----
<?php

namespace Joomla\Rector\Tests\Joomla6\Plugin\HandlerReturnToEventResultRector\Fixture;

use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\Plugin\CMSPlugin;

class PlgContentNested extends CMSPlugin
{
    /**
     * Validates the item.
     *
     * @param   BeforeSaveEvent  $event  The event.
     *
     * @return void
     */
    public function onContentBeforeSave(BeforeSaveEvent $event): void
    {
        foreach ($event->getItem()->tags as $tag) {
            if ($tag === 'blocked') {
                $event->addResult(false);
                return;
            }
        }

        $event->addResult($this->check($event->getContext()));
    }

    private function check(string $context)
    {
        return $context !== '';
    }
}

?>
