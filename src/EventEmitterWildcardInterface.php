<?php

namespace Reaction\Events;

use Evenement\EventEmitterInterface;
use React\Promise\PromiseInterface;

/**
 * Interface EventEmitterWildcardInterface
 * @package Reaction\Events
 */
interface EventEmitterWildcardInterface extends EventEmitterInterface
{
    const LISTENERS_GROUP_DEFAULT   = 'listeners';
    const LISTENERS_GROUP_ONCE      = 'onceListeners';
    const LISTENERS_GROUP_WLC       = 'listenersWildcard';
    const LISTENERS_GROUP_WLC_ONCE  = 'onceListenersWildcard';

    /**
     * Emits an event and returns Promise, which will be resolved
     * when all listeners complete their work
     *
     * @param string $event
     * @param array $arguments
     * @param int   $timeout
     * @return PromiseInterface
     */
    public function emitAndWait($event, array $arguments = [], $timeout = 10);
}