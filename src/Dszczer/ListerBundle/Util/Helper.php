<?php
/**
 * Various helper functions.
 * @category Utils
 * @author   Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\Util;

/**
 * Class Helper
 * @package Dszczer\ListerBundle
 * @since 0.9
 */
abstract class Helper
{
    /**
     * Generates UUIDv4 unique identifier as string.
     * @return string
     */
    public static function uuidv4(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

            // 32 bits for "time_low"
            random_int(0, 0xffff),
            random_int(0, 0xffff),

            // 16 bits for "time_mid"
            random_int(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            random_int(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            random_int(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }

    /**
     * Camelize words.
     * @param string $input String to camelize
     * @param string $separator Separator of words
     * @return string
     */
    public static function camelize(string $input, string $separator = '_'): string
    {
        return lcfirst(str_replace($separator, '', ucwords(str_replace(' ', $separator, $input), $separator)));
    }

    /**
     * Fix Twig path: convert from Symfony-like to Twig-like.
     * @param string $path Symfony-like twig path
     * @return string
     */
    public static function fixTwigTemplatePath(string $path): string
    {
        return str_replace([':', 'Bundle'], ['/', ''], $path);
    }

    /**
     * Encodes anything into string, except resource type. Binary safe.
     * @param mixed $source Data to encode
     * @return string Encoded data
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public static function encodeAnything($source): string
    {
        if (is_resource($source)) {
            throw new \InvalidArgumentException('Object is not valid to encode');
        }
        try {
            if ($source instanceof \JsonSerializable || is_array($source)) {
                $encoded = json_encode($source);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException(json_last_error_msg(), json_last_error());
                }

                return $encoded;
            } else {
                return serialize($source);
            }
        } catch (\Throwable $exception) {
        }

        throw new \RuntimeException('Error occured while encoding', E_USER_ERROR, $exception);
    }

    /**
     * Decodes encoded string with static method. Binary safe.
     * @param string $source Source of encoded data by Helper::encodeAnything
     * @return mixed Decoded data
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public static function decodeAnything(string $source)
    {
        try {
            $decoded = json_decode($source, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception(json_last_error_msg(), json_last_error());
            }

            return $decoded;
        } catch (\Throwable $exception) {
        }

        try {
            return unserialize($source);
        } catch (\Throwable $exception) {
        }

        throw new \InvalidArgumentException('Source is not valid encoded string', E_USER_ERROR, $exception);
    }
}