<?php

namespace Arokettu\Composer\Viz\Helpers;

/**
 * from symfony/polyfill-php80 backported to PHP 5.x
 * @internal
 */
final class StringHelper
{
    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function strContains($haystack, $needle)
    {
        return '' === $needle || false !== strpos($haystack, $needle);
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function strStartsWith($haystack, $needle)
    {
        return 0 === strncmp($haystack, $needle, \strlen($needle));
    }
}
