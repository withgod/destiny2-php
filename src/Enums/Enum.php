<?php
/**
 * destiny2-php
 * @author Richard Lynskey <richard@mozor.net>
 * @copyright Copyright (c) 2017, Richard Lynskey
 * @license https://opensource.org/licenses/MIT MIT
 * @version 0.1
 *
 * Built 2017-10-01 12:32 CDT by richard
 *
 */

namespace Destiny\Enums;

/**
 * Class Enum
 * @package Destiny\Enums
 */
interface Enum
{

    /**
     * @param int $type
     * @return string
     */
    public static function getLabel($type);
}