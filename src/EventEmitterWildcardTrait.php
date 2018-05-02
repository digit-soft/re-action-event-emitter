<?php

namespace Reaction\Events;

use React\Promise\PromiseInterface;

/**
 * Trait EventEmitterWildcardTrait.
 * Use it with your classes to implement EventEmitterWildcardInterface
 * @package Reaction\Events
 */
trait EventEmitterWildcardTrait
{
    protected $listeners = [];
    protected $onceListeners = [];
    protected $listenersWildcard = [];
    protected $onceListenersWildcard = [];

    /**
     * Register event listener
     * @param string $event
     * @param callable $listener
     * @return $this
     */
    public function on($event, callable $listener)
    {
        if ($event === null) {
            throw new \InvalidArgumentException('Event name must not be null');
        }

        list($event, $isWildcard) = $this->processEventName($event);

        if ($isWildcard) {
            return $this->onWildcard($event, $listener);
        }

        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = $listener;

        return $this;
    }

    /**
     * Register event listener (once)
     * @param string $event
     * @param callable $listener
     * @return $this
     */
    public function once($event, callable $listener)
    {
        if ($event === null) {
            throw new \InvalidArgumentException('Event name must not be null');
        }

        list($event, $isWildcard) = $this->processEventName($event);

        if ($isWildcard) {
            return $this->onceWildcard($event, $listener);
        }

        if (!isset($this->onceListeners[$event])) {
            $this->onceListeners[$event] = [];
        }

        $this->onceListeners[$event][] = $listener;

        return $this;
    }

    /**
     * Remove listener for event
     * @param string   $event
     * @param callable $listener
     */
    public function removeListener($event, callable $listener)
    {
        if ($event === null) {
            throw new \InvalidArgumentException('Event name must not be null');
        }

        $this->removeListenerInternal($event, $listener);
        $this->removeListenerInternal($event, $listener, self::LISTENERS_GROUP_ONCE);
        $this->removeListenerInternal($event, $listener, self::LISTENERS_GROUP_WLC);
        $this->removeListenerInternal($event, $listener, self::LISTENERS_GROUP_WLC_ONCE);
    }

    /**
     * Remove all listeners for event
     * @param string $event
     */
    public function removeAllListeners($event = null)
    {
        $groups = [
            static::LISTENERS_GROUP_DEFAULT, static::LISTENERS_GROUP_ONCE,
            static::LISTENERS_GROUP_WLC, static::LISTENERS_GROUP_WLC_ONCE,
        ];
        if ($event !== null) {
            foreach ($groups as $group) {
                if (!isset($this->{$group}[$event])) continue;
                unset($this->listeners[$event]);
            }
        } else {
            foreach ($groups as $group) {
                $this->{$group} = null;
            }
        }
    }

    /**
     * Get listeners for event ar all
     * @param string|null $event
     * @return array
     */
    public function listeners($event = null): array
    {
        if ($event === null) {
            $events = [];
            $eventNames = \array_unique(
                \array_merge(\array_keys($this->listeners), \array_keys($this->onceListeners))
            );
            foreach ($eventNames as $eventName) {
                $events[$eventName] = \array_merge(
                    isset($this->listeners[$eventName]) ? $this->listeners[$eventName] : [],
                    isset($this->onceListeners[$eventName]) ? $this->onceListeners[$eventName] : []
                );
            }
            return $events;
        }

        $wlc = $this->findWildcardListeners(null, self::LISTENERS_GROUP_WLC);
        $wlcOnce = $this->findWildcardListeners(null, self::LISTENERS_GROUP_WLC_ONCE);

        return \array_merge(
            isset($this->listeners[$event]) ? $this->listeners[$event] : [],
            isset($this->onceListeners[$event]) ? $this->onceListeners[$event] : [],
            $wlc,
            $wlcOnce
        );
    }

    /**
     * Emit event on object
     * @param string $event
     * @param array $arguments
     */
    public function emit($event, array $arguments = [])
    {
        if ($event === null) {
            throw new \InvalidArgumentException('Event name must not be null');
        }

        if (isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $listener) {
                $listener(...$arguments);
            }
        }

        if (isset($this->onceListeners[$event])) {
            $listeners = $this->onceListeners[$event];
            unset($this->onceListeners[$event]);
            foreach ($listeners as $listener) {
                $listener(...$arguments);
            }
        }

        //Wildcard listeners
        $listenersWc = $this->findWildcardListeners($event);
        if (!empty($listenersWc)) {
            foreach ($listenersWc as $eventName => $listeners) {
                foreach ($listeners as $listener) {
                    $listener(...$arguments);
                }
            }
        }
        //Wildcard listeners (once)
        $listenersOnceWc = $this->findWildcardListeners($event, self::LISTENERS_GROUP_WLC_ONCE);
        if (!empty($listenersOnceWc)) {
            foreach ($listenersOnceWc as $eventName => $listeners) {
                unset($this->onceListenersWildcard[$eventName]);
                foreach ($listeners as $listener) {
                    $listener(...$arguments);
                }
            }
        }
    }

    /**
     * Emits an event and returns Promise, which will be resolved
     * when all listeners complete their work
     *
     * @param string $event
     * @param array $arguments
     * @param int   $timeout
     * @return PromiseInterface
     */
    public function emitAndWait($event, array $arguments = [], $timeout = 10) {
        if ($event === null) {
            throw new \InvalidArgumentException('Event name must not be null');
        }
        $results = [];

        if (isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $listener) {
                $results[] = $listener(...$arguments);
            }
        }

        if (isset($this->onceListeners[$event])) {
            $listeners = $this->onceListeners[$event];
            unset($this->onceListeners[$event]);
            foreach ($listeners as $listener) {
                $results[] = $listener(...$arguments);
            }
        }

        //Wildcard listeners
        $listenersWc = $this->findWildcardListeners($event);
        if (!empty($listenersWc)) {
            foreach ($listenersWc as $eventName => $listeners) {
                foreach ($listeners as $listener) {
                    $results[] = $listener(...$arguments);
                }
            }
        }
        //Wildcard listeners (once)
        $listenersOnceWc = $this->findWildcardListeners($event, self::LISTENERS_GROUP_WLC_ONCE);
        if (!empty($listenersOnceWc)) {
            foreach ($listenersOnceWc as $eventName => $listeners) {
                unset($this->onceListenersWildcard[$eventName]);
                foreach ($listeners as $listener) {
                    $results[] = $listener(...$arguments);
                }
            }
        }

        $promises = [];
        $prCallback = function () { return true; };
        foreach ($results as $result) {
            if ($result !== null && $result instanceof PromiseInterface) {
                $promises[] = $result->then($prCallback, $prCallback);
            }
        }

        $reactionUsed = function_exists('Reaction\Promise\resolve');
        if (empty($promises)) {
            return $reactionUsed ? \Reaction\Promise\resolve(true) : \React\Promise\resolve(true);
        }

        $allPromise = $reactionUsed ? \Reaction\Promise\all($promises) : \React\Promise\all($promises);

        if ($reactionUsed) {
            \Reaction::$app->loop->addTimer($timeout, function () use ($allPromise) { $allPromise->cancel(); });
        }

        return $allPromise;
    }

    /**
     * Register wildcard event (once)
     * @param string   $event
     * @param callable $listener
     * @return $this
     */
    protected function onWildcard($event, callable $listener) {
        if (!isset($this->listenersWildcard[$event])) {
            $this->listenersWildcard[$event] = [];
        }

        $this->listenersWildcard[$event][] = $listener;

        return $this;
    }

    /**
     * Register wildcard event (once)
     * @param string   $event
     * @param callable $listener
     * @return $this
     */
    protected function onceWildcard($event, callable $listener) {
        if (!isset($this->onceListenersWildcard[$event])) {
            $this->onceListenersWildcard[$event] = [];
        }

        $this->onceListenersWildcard[$event][] = $listener;

        return $this;
    }

    /**
     * Remove listener
     * @internal
     * @param string   $event
     * @param callable $listener
     * @param string   $listenersKey
     */
    protected function removeListenerInternal($event, callable $listener, $listenersKey = self::LISTENERS_GROUP_DEFAULT) {
        $listeners = &$this->{$listenersKey};
        if (isset($listeners[$event])) {
            $index = \array_search($listener, $listeners[$event], true);
            if (false !== $index) {
                unset($listeners[$event][$index]);
                if (\count($listeners[$event]) === 0) {
                    unset($listeners[$event]);
                }
            }
        }
    }

    /**
     * Process event name and find wildcards and regex
     * @param string $event
     * @return array
     */
    protected function processEventName($event) {
        //Not a regular expression or wildcard
        if (!preg_match('/(\~|\*|\^|\$|\?|\(|\)|\[|\])/', $event)) return [$event, false];
        //Regular expression begins with ~ (tilda symbol)
        if (strpos($event, '~') !== 0) {
            //Wildcards with stars placeholder
            $starPl = '__STAR__';
            //Replace some regex symbols
            $event = str_replace('\\*', $starPl, $event);
            $event = preg_replace_callback('/(^|[^\\\])(\.|\$|\[|\]|\(|\)|\?|\+)/', function ($matches) {
                return $matches[1] . '\\' . $matches[2];
            }, $event);
            //Replace wildcard with regex
            $event = str_replace('*', '(.*)', $event);
            //Return back slash prefixed star
            $event = str_replace($starPl, '\\*', $event);
        } else {
            //Remove ~ symbol from regex
            $event = substr($event, 1);
        }
        //Add regex slashes
        if (strcmp(substr($event, 0, 1), '/') !== 0 || strcmp(substr($event, -1), '/') !== 0) {
            $event = '/' . $event . '/';
        }
        return [$event, true];
    }

    /**
     * Find wildcard event handlers
     * @param string|null $event
     * @param string      $listenersKey
     * @return array
     */
    protected function findWildcardListeners($event = null, $listenersKey = self::LISTENERS_GROUP_WLC) {
        $listeners = [];
        $keys = array_keys($this->{$listenersKey});
        if (!empty($keys)) {
            foreach ($keys as $key) {
                if ($event === null || preg_match($key, $event)) {
                    $listeners[$key] = $this->{$listenersKey}[$key];
                }
            }
        }
        return $listeners;
    }
}