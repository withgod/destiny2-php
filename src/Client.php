<?php
/**
 * destiny2-php
 * @author Richard Lynskey <richard@mozor.net>
 * @copyright Copyright (c) 2017, Richard Lynskey
 * @license https://opensource.org/licenses/MIT MIT
 * @version 0.1
 *
 * Built 2017-09-23 09:51 CDT by richard
 *
 */

namespace Destiny;

use Destiny\Enums\BungieMembershipType;
use Destiny\Enums\DestinyComponentType;
use Destiny\Enums\GroupType;
use Destiny\Exceptions\ClientException;
use Destiny\Exceptions\ApiKeyException;
use Destiny\Exceptions\OAuthException;
use Destiny\Objects\DestinyCharacterComponent;
use Destiny\Objects\DestinyProfileComponent;
use Destiny\Objects\DestinyProfileResponse;
use Destiny\Objects\GeneralUser;
use Destiny\Objects\GroupMember;
use Destiny\Objects\GroupResponse;
use GuzzleHttp\Exception\ClientException as GuzzleClientException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

/**
 * Class Client
 * @package Destiny
 *
 * Some of get functions do require an OAuth token for access. If you do not pass an OAuth token for functions that do
 * not require it, that is fine. However, if you pass an invalid OAuth token to ANY function regardless of its
 * requirements you will get an OAuthException
 */
class Client
{
    /**
     *
     */
    const URI = "https://www.bungie.net/Platform";

    /**
     * @var string Destiny API Key
     */
    protected $_apiKey;

    /**
     * @var string Destiny OAuth Token
     */
    protected $_oauthToken;

    /**
     * @var GuzzleClient $_httpClient
     */
    protected $_httpClient;

    /**
     * Client constructor.
     * @param string $apiKey
     * @param null $token
     * @throws ApiKeyException
     */
    function __construct($apiKey = '', $token = null)
    {
        if (empty($apiKey)) {
            $apiKey = $_ENV["APIKEY"];
        }

        if (empty($apiKey)) {
            throw new ApiKeyException("API Key is not set");
        }

        $this->_apiKey = $apiKey;

        if (!empty($token)) {
            $this->_oauthToken = $token;
        }
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function __get($name)
    {

        switch (strtoupper($name)) {
            case 'CLIENTID':
            case '_CLIENTID':
            case 'APIKEY':
            case '_APIKEY':
                return $this->_apiKey;
                break;
            default:
                return $this->$name;
                break;
        }
    }

    /**
     * @param string $url
     * @return mixed
     * @throws ApiKeyException
     * @throws ClientException
     * @throws OAuthException
     */
    protected function request($url)
    {

        if (empty($this->_apiKey)) {
            throw new ApiKeyException("API Key is not set");
        }

        $headers = [
            'X-Api-Key' => $this->_apiKey
        ];

        if (!empty($this->_oauthToken)) {
            $headers['Authorization'] = sprintf('Bearer %s', $this->_oauthToken);
        }

        try {
            $response = $this->getHttpClient()
                ->request('GET', $url, [
                    'headers' => $headers
                ]);
        } catch (GuzzleClientException $x) {
            switch ($x->getCode()) {
                case 401:
                    throw new OAuthException('401 Unauthorized');
                    break;
            }
        }

        $body = ResponseMediator::convertResponseToArray($response);

        switch ($body['ErrorCode']) {
            case 1:
                return $body;
                break;
            case 2101:
                throw new ApiKeyException($body['Message'], $body['ErrorCode'], $body['ThrottleSeconds'],
                    $body['ErrorStatus']);
                break;
            default:
                throw new ClientException($body['Message'], $body['ErrorCode'], $body['ThrottleSeconds'],
                    $body['ErrorStatus']);
                break;
        }
    }

    /**
     * @return GuzzleClient
     */
    protected function getHttpClient()
    {
        if ($this->_httpClient === null) {
            $this->_httpClient = new GuzzleClient(['base_uri' => self::URI, 'verify' => false]);
        }

        return $this->_httpClient;
    }

    /**
     * @param string $endpoint
     * @param array|null $uriParams
     * @param array|null $queryParams
     * @return string
     */
    protected function _buildRequestString($endpoint, array $uriParams = null, array $queryParams = null)
    {
        $query = '';
        if (!empty($queryParams)) {
            $query = http_build_query($queryParams);
        }
        $url = sprintf("%s/%s/%s/?%s", self::URI, $endpoint, implode("/", $uriParams), $query);
        return $url;
    }

    /**
     * Get a Bungie group either by name with grouptype or by groupID (grouptype is ignored with a groupID).
     * Note that a group with a numeric only name will not work here as this function will see it as a groupID
     *
     * There is a bug currently in the Bungie API that is causing lookup by name to not always function when names have
     * a space in them. See https://github.com/Bungie-net/api/issues/162
     *
     * @param string|int $group
     * @param string|int $groupType
     * @return GroupResponse
     *
     * @throws ApiKeyException
     * @throws ClientException
     * @throws OAuthException
     *
     * OAuth token optional. Passing an OAuth token for a user in the requested group will cause it to return more info.
     *
     * @link https://bungie-net.github.io/multi/operation_get_GroupV2-GetGroupByName.html#operation_get_GroupV2-GetGroupByName
     * @link https://bungie-net.github.io/multi/operation_get_GroupV2-GetGroup.html#operation_get_GroupV2-GetGroup
     */
    public function getGroup($group, $groupType = GroupType::CLAN)
    {
        if (is_integer($group)) {
            $response = $this->request($this->_buildRequestString('GroupV2', [$group]));
        } else {
            $response = $this->request($this->_buildRequestString('GroupV2', ['Name', $group, $groupType]));
        }
        return GroupResponse::makeFromArray($response['Response']);
    }

    /**
     * @param $clanID
     * @param int $currentPage
     * @return GroupMember[]
     *
     * @throws ApiKeyException
     * @throws ClientException
     * @throws OAuthException
     *
     * @link https://bungie-net.github.io/multi/operation_get_GroupV2-GetMembersOfGroup.html#operation_get_GroupV2-GetMembersOfGroup
     */
    public function getClanMembers($clanID, $currentPage = 1)
    {
        $response = $this->request($this->_buildRequestString('GroupV2', [$clanID, 'Members'],
            ['currentPage' => $currentPage]));

        return array_map(function ($item) {
            return GroupMember::makeFromArray($item);
        }, $response['Response']['results']);
    }

    /**
     * @param $clanID
     * @param int $currentPage
     * @return GroupMember[]
     *
     * @throws ApiKeyException
     * @throws ClientException
     * @throws OAuthException
     *
     * @link https://bungie-net.github.io/multi/operation_get_GroupV2-GetAdminsAndFounderOfGroup.html#operation_get_GroupV2-GetAdminsAndFounderOfGroup
     */
    public function getClanAdminsAndFounder($clanID, $currentPage = 1)
    {
        $response = $this->request($this->_buildRequestString('GroupV2', [$clanID, 'AdminsAndFounder'],
            ['currentPage' => $currentPage]));

        return array_map(function ($item) {
            return GroupMember::makeFromArray($item);
        }, $response['Response']['results']);
    }

    /**
     * @return GeneralUser
     *
     * @throws ApiKeyException
     * @throws ClientException
     * @throws OAuthException
     *
     * Requires an OAuth token
     */
    public function getCurrentBungieUser()
    {
        if (empty($this->_oauthToken)) {
            throw new OAuthException('401 Unauthorized');
        }
        $response = $this->request($this->_buildRequestString('User', ['GetCurrentBungieNetUser']));

        return GeneralUser::makeFromArray($response['Response']);
    }

    /**
     * @param int|string $membershipType
     * @param int $membershipID
     * @param string[]|int[] $components
     *
     * @return mixed
     *
     * @throws ApiKeyException
     * @throws ClientException
     * @throws OAuthException
     */
    public function getProfile($membershipType, $membershipID, ...$components)
    {
        // Check to see if the supplied membershipType is a number. If not, convert it to the label
        if (is_int($membershipType)) {
            $membershipType = BungieMembershipType::getLabel($membershipType);
        }
        if($membershipType == "None" || $membershipType == "") {
            throw new ClientException('An invalid MembershipType was supplied.');
        }

        $params = [];
        foreach($components as $i) {
            if(is_int($i)) {
                $params[] = DestinyComponentType::getLabel($i);
            } else {
                $params[] = $i;
            }
        }

        $response = $this->request($this->_buildRequestString('Destiny2', [$membershipType, 'Profile', $membershipID], ['components' => implode(',', $params)]));

        $profileResponse = new DestinyProfileResponse();
        $profileResponse->profile = DestinyProfileComponent::makeFromArray($response['Response']['profile']);

        $profileResponse->characters = array_map(function ($item) {
            return DestinyCharacterComponent::makeFromArray($item);
        }, $response['Response']['characters']['data']);

        return $profileResponse;

    }

    /**
     * Gets the absolute path on https://www.bungie.net to the mobileWorldContentPath for the given locale (defaults to en)
     * @param string $locale Defaults to en.
     * @return string
     */
    public function getMobileWorldContentsPath($locale = "en")
    {
        $response = $this->request($this->_buildRequestString('Destiny2', ['Manifest']));

        return $response['Response']['mobileWorldContentPaths'][$locale];
    }

    /**
     * @param int $userID
     * @return GeneralUser
     *
     * @throws ApiKeyException
     * @throws ClientException
     * @throws OAuthException
     *
     * @link https://bungie-net.github.io/multi/operation_get_User-GetBungieNetUserById.html#operation_get_User-GetBungieNetUserById
     */
    public function getBungieUser($userID)
    {
        $response = $this->request($this->_buildRequestString('User', ['GetBungieNetUserById', $userID]));

        return GeneralUser::makeFromArray($response['Response']);
    }

    /**
     * Shim for testing the API
     *
     * @param string $responseFile
     * @param int $statusCode HTTP Response Code (Defaults to 200)
     */
    public function setMock($responseFile, $statusCode = 200)
    {


        $mock = new MockHandler([
            new Response($statusCode, ['Content-Type' => 'application/json'], file_get_contents($responseFile))
        ]);

        $handler = HandlerStack::create($mock);
        $this->_httpClient = new GuzzleClient(['handler' => $handler]);
    }

}