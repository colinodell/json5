<?php
// @codingStandardsIgnoreFile

if (!function_exists('json5_decode')) {
    /**
     * Takes a JSON encoded string and converts it into a PHP variable.
     *
     * The parameters exactly match PHP's json_decode() function - see
     * http://php.net/manual/en/function.json-decode.php for more information.
     *
     * @param string $json        The JSON string being decoded.
     * @param bool   $associative When TRUE, returned objects will be converted into associative arrays.
     * @param int    $depth       User specified recursion depth.
     * @param int    $flags       Bitmask of JSON decode options.
     *
     * @throws \ColinODell\Json5\SyntaxError if the JSON encoded string could not be parsed.
     */
    function json5_decode(string $json, ?bool $associative = false, int $depth = 512, int $flags = 0): mixed
    {
        return \ColinODell\Json5\Json5Decoder::decode($json, $associative, $depth, $flags);
    }
}
