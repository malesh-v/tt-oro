<?php

declare(strict_types=1);

namespace App\ChainCommandBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to Symfony console events to manage command chain execution.
 */
class ChainCommandSubscriber implements EventSubscriberInterface
{
    /**
     * Returns the events this subscriber listens to.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // TODO add listen to console command events.
        ];
    }
}
