<?php
/**
 * destiny2-php
 * @author Richard Lynskey <richard@mozor.net>
 * @copyright Copyright (c) 2017, Richard Lynskey
 * @license https://opensource.org/licenses/MIT MIT
 * @version 0.1
 *
 * Built 2017-10-15 14:32 CDT by richard
 *
 */

namespace Destiny\Objects;

use JsonSerializable;

/**
 * Class DestinyProfileResponse
 * @package Destiny\Objects
 *
 * @link https://bungie-net.github.io/multi/schema_Destiny-Responses-DestinyProfileResponse.html#schema_Destiny-Responses-DestinyProfileResponse
 *
 * @method DestinyProfileComponent profile()
 * @method DestinyCharacterComponent[] characters()
 *
 * @property DestinyProfileComponent $profile
 * @property DestinyCharacterComponent[] $characters
 */
class DestinyProfileResponse
{

    public function __call($name, $arguments)
    {
        return $this->$name;
    }

}