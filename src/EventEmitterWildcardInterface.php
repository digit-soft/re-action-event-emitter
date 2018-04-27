<?php

namespace Reaction\Events;

use Evenement\EventEmitterInterface;

interface EventEmitterWildcardInterface extends EventEmitterInterface
{
    const LISTENERS_GROUP_DEFAULT   = 'listeners';
    const LISTENERS_GROUP_ONCE      = 'onceListeners';
    const LISTENERS_GROUP_WLC       = 'listenersWildcard';
    const LISTENERS_GROUP_WLC_ONCE  = 'onceListenersWildcard';
}