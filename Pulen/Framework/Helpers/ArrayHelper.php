<?php

namespace Kodhe\Pulen\Framework\Helpers;

class ArrayHelper
{
    /**
     * Element
     *
     * Lets you determine whether an array index is set and whether it has a value.
     * If the element is empty it returns NULL (or whatever you specify as the default value.)
     *
     * @param string $item
     * @param array $array
     * @param mixed $default
     * @return mixed Depends on what the array contains
     */
    public static function element(string $item, array $array, $default = null)
    {
        return array_key_exists($item, $array) ? $array[$item] : $default;
    }

    /**
     * Random Element
     *
     * Takes an array as input and returns a random element.
     *
     * @param array $array
     * @return mixed Depends on what the array contains
     */
    public static function randomElement(array $array)
    {
        return is_array($array) ? $array[array_rand($array)] : $array;
    }

    /**
     * Elements
     *
     * Returns only the array items specified. Will return a default value if
     * it is not set.
     *
     * @param array|string $items
     * @param array $array
     * @param mixed $default
     * @return array Depends on what the array contains
     */
    public static function elements($items, array $array, $default = null): array
    {
        $return = [];

        if (!is_array($items)) {
            $items = [$items];
        }

        foreach ($items as $item) {
            $return[$item] = array_key_exists($item, $array) ? $array[$item] : $default;
        }

        return $return;
    }
}
