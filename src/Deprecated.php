<?php

declare(strict_types=1);

namespace Policyreporter\LazyBase;

trait Deprecated
{
    private static $silenceDeprecationWarnings = false;

    /**
     * Trigger a deprecation notice
     *
     * All internal functions will respect this being overriden in a subclass
     */
    protected static function deprecated()
    {
        if (self::$silenceDeprecationWarnings !== true) {
            trigger_error("Deprecated Function Call", E_USER_DEPRECATED);
        }
    }

    /**
     * Disable any further deprecation warnings about the usage of old-style function
     * calls, this will itself throw an E_USER_DEPRECATED to prevent notices from
     * silently being squelched by accident
     *
     * @return void
     */
    public static function testMode()
    {
        trigger_error("Disabling deprecation warnings", E_USER_DEPRECATED);
        self::$silenceDeprecationWarnings = true;
    }
}
