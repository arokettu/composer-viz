<?php

// from symfony/polyfill-php80 backported to PHP 5.x

function str_contains($haystack, $needle)
{
    return '' === $needle || false !== strpos($haystack, $needle);
}

function str_starts_with($haystack, $needle)
{
    return 0 === strncmp($haystack, $needle, \strlen($needle));
}
