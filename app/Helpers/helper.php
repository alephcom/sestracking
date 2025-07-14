<?php

    function generateToken()
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    function timezoneOffsetFormatter($minutesOffset): string
    {
        return ($minutesOffset < 0 ? '-' : '+') . date('H:i', mktime(0, abs($minutesOffset)));
    }