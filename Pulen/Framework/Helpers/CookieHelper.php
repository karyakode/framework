<?php

namespace Kodhe\Pulen\Framework\Helpers;

class CookieHelper
{
    /**
     * Set cookie
     *
     * Accepts parameters or an associative array in the first parameter containing all the values.
     *
     * @param mixed  $name    Name of the cookie or array with cookie parameters
     * @param string $value   Value of the cookie
     * @param string $expire  Number of seconds until expiration
     * @param string $domain  Cookie domain (e.g., .yourdomain.com)
     * @param string $path    Cookie path
     * @param string $prefix  Cookie prefix
     * @param bool   $secure  True makes the cookie secure
     * @param bool   $httponly True makes the cookie accessible via HTTP(S) only
     * @return void
     */
    public static function setCookie($name, $value = '', $expire = '', $domain = '', $path = '/', $prefix = '', $secure = null, $httponly = null)
    {
        kodhe()->input->set_cookie($name, $value, $expire, $domain, $path, $prefix, $secure, $httponly);
    }

    /**
     * Fetch an item from the COOKIE array
     *
     * @param string $index    Cookie name
     * @param bool   $xssClean Whether to apply XSS filtering
     * @return mixed
     */
    public static function getCookie($index, $xssClean = null)
    {
        is_bool($xssClean) || $xssClean = (config_item('global_xss_filtering') === true);
        $prefix = isset($_COOKIE[$index]) ? '' : config_item('cookie_prefix');
        return kodhe()->input->cookie($prefix . $index, $xssClean);
    }

    /**
     * Delete a COOKIE
     *
     * @param string $name   Name of the cookie
     * @param string $domain Cookie domain (e.g., .yourdomain.com)
     * @param string $path   Cookie path
     * @param string $prefix Cookie prefix
     * @return void
     */
    public static function deleteCookie($name, $domain = '', $path = '/', $prefix = '')
    {
        self::setCookie($name, '', '', $domain, $path, $prefix);
    }
}
