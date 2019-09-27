<?php

namespace Screencom\MagicinfoApi\Post;

use GuzzleHttp\Client as Guzzle;
use Illuminate\Support\Facades\Cache;

/**
 * http://192.168.0.6:7001/MagicInfo/mobile/
 * http://192.168.0.6:7001/MagicInfo/swagger-ui.html
 */

/**
 * Class Magicinfo
 * @package App\Classes\API
 */
class Magicinfo
{

    protected $endpoint;

    protected $authendpoint;

    protected $apiendpoint;

    protected $apikey;

    protected $username;

    protected $password;

    protected $cacheMinutes = 60;

    protected $client;

    /**
     * Magicinfo constructor.
     *
     * @param      $endpoint
     * @param      $username
     * @param      $password
     * @param bool $authenticate
     *
     * @throws \Exception
     */
    public function __construct($endpoint, $username, $password, $authenticate = true)
    {
        $this->endpoint     = $endpoint; // env('MAGICINFO_ENDPOINT', 'http://192.168.0.6:7001/MagicInfo');
        $this->authendpoint = join('/', [$this->endpoint, 'auth']);
        $this->apiendpoint  = join('/', [$this->endpoint, 'restapi/v1.0']);

        $this->username = $username; // env('MAGICINFO_USERNAME', 'admin'); // admin
        $this->password = $password; // env('MAGICINFO_PASSWORD', 'Admin@2018!'); // Admin@2018!

        $this->apikey = null;
        $this->client = new Guzzle(['cookies' => true]);

        if ($authenticate) {
            $this->authenticate();
        }

    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function authenticate()
    {
        try {
            $this->apikey = $this->token();

            return $this;
        } catch (\Exception $e) {
            throw new \Exception('Unable to retrieve token @ ' . $this->authendpoint);
        }
    }

    /**
     * Fetch a new token from the server
     *
     * @return mixed
     */
    public function token()
    {
        return Cache::remember(
            $this->tokenCacheKey(),
            $this->cacheMinutes,
            function () {
                $response = $this->client->post($this->authendpoint, [
                    'body'    => json_encode([
                        'username' => $this->username,
                        'password' => $this->password,
                    ]),
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept'       => 'application/json, text/plain, */*',
                    ],
                    //'connect_timeout' => 5,
                    //'debug' => true
                ]);
                $result   = \GuzzleHttp\json_decode($response->getBody()->getContents());

                return $result->token;
            }
        );
    }

    /**
     * @return string
     */
    public function tokenCacheKey()
    {
        return sha1(join('§', [$this->authendpoint, $this->username, $this->password]));
    }

    /**
     * @param $endpoint
     * @param $username
     * @param $password
     *
     * @return Magicinfo
     * @throws \Exception
     */
    public static function make($endpoint, $username, $password)
    {
        return new static($endpoint, $username, $password);
    }

    /**
     * Refresh the token and save the new token in the cache
     *
     * @return mixed|object
     */
    public function refresh()
    {
        try {
            $response = $this->client->get($this->authendpoint . '/refresh', $this->headers());
            $result   = \GuzzleHttp\json_decode($response->getBody()->getContents());
            Cache::put(
                $this->tokenCacheKey(),
                $result->token,
                $this->cacheMinutes
            );

            return $result->token;
        } catch (\Exception $e) {
            $result = (object)['token' => null];
        }

        return $result;
    }

    /**
     * @return array
     */
    protected function headers()
    {
        return ['headers' => ['Accept' => 'application/json', 'api_key' => $this->apikey]];
    }

    /**
     * @param $base
     *
     * @return string
     */
    protected function url($base)
    {
        return join('/', [$this->apiendpoint, $base]);
    }

    /**
     * Remove the token from the cache and the object
     *
     * @return bool
     */
    public function clear()
    {
        $this->apikey = null;

        return Cache::forget($this->tokenCacheKey());
    }

    /**
     * Return a list of all devices
     *
     * @param int $max
     *
     * @return mixed|object
     */
    public function devices($max = 500)
    {
        $startIndex = 0;
        $pageSize = 100;

        $result = $this->getItems(sprintf('rms/devices?startIndex=%d&pageSize=%d', $startIndex, $pageSize));

        //echo "first hit : " . count($result->items) . " of {$result->totalCount}\n";

        if ($result->totalCount > $pageSize) {
            $left = $result->totalCount - count($result->items);
            $startIndex += count($result->items);
            //echo "update \$startIndex to $startIndex\n";
            while ($left > 0 && count($result->items) < $max) {
                //echo "items left to retrieve: $left, total retrieved : ".count($result->items)."\n";
                $tmp = $this->getItems(sprintf('rms/devices?startIndex=%d&pageSize=%d', $startIndex-1, ($pageSize < $left) ? $pageSize : $left));
                foreach ($tmp->items as $item) {
                    $result->items[] = $item;
                }
                $left = $left - count($tmp->items);
                //echo "update \$startIndex to $startIndex (left = $left)\n";
                $startIndex += count($tmp->items);
            }
        }

        //echo "total found : " . count($result->items) . "\n";

        return $result;
    }

    /**
     * @param null $filter
     *
     * @return mixed|object
     */
    public function content($filter = null)
    {
        if (is_null($filter)) {
            $startIndex = 1;
            $pageSize   = 100;

            return $this->getItems(sprintf('cms/contents?startIndex=%d&pageSize=%d', $startIndex, $pageSize));
        } else {
            return $this->postItems('cms/contents/filter', $filter);
        }
    }

    /**
     * @return mixed|object
     */
    public function HTMLElements()
    {
        return $this->content(['mediaType' => 'HTML']);
    }

    /**
     * Return the values needed to show the dashboard
     *
     * @return mixed|object
     */
    public function dashboard()
    {
        return $this->getItems('rms/devices/dashboard');
    }

    /**
     * Return the values needed to show the storage used
     *
     * @return mixed|object
     */
    public function storage()
    {
        return $this->getItems('ems/dashboard/storage');
    }

    /**
     * @return mixed|object
     */
    public function contents()
    {
        return $this->getItems('cms/contents/dashboard');
    }

    /**
     * @return mixed|object
     */
    public function playlists()
    {
        return $this->getItems('cms/playlists/dashboard');
    }

    /**
     * @return mixed|object
     */
    public function schedules()
    {
        return $this->getItems('dms/schedule/contents/dashboard');
    }

    /**
     * @return mixed|object
     */
    public function users()
    {
        return $this->getItems('ums/users/dashboard');
    }

    /**
     * @return object
     */
    public function notices()
    {
        $result = (object)['items' => []];

        $notices = $this->getItems('ems/dashboard/notice');

        if (count(optional($notices->items)->noticeList)) {
            foreach ($notices->items->noticeList as $item) {
                if ($item->noticeUserId == 'admin') {
                    $notice = $this->getItems(sprintf('ems/dashboard/notice/edit?noticeId=%d', $item->noticeId));
                    $notice->items->noticeUserId = $item->noticeUserId;
                    $result->items[] = $notice->items;
                }
            }
        }

        return $result;
    }

    /**
     * @param $url
     *
     * @return mixed|object
     */
    public function getItems($url)
    {
        try {
            $response = $this->client->get($this->url($url), $this->headers());
            $result   = \GuzzleHttp\json_decode($response->getBody()->getContents());
        } catch (\Exception $e) {
            $result = (object)['status' => $e->getMessage(), 'items' => []];
        }

        return $result;
    }

    /**
     * @param       $url
     * @param array $body
     *
     * @return mixed|object
     */
    public function postItems($url, $body = [])
    {
        try {
            $headers  = $this->headers();
            if ($body && count($body)) {
                $headers['json'] = $body;
            }
            $response = $this->client->post($this->url($url), $headers);
            $result   = \GuzzleHttp\json_decode($response->getBody()->getContents());
        } catch (\Exception $e) {
            $result = (object)['status' => $e->getMessage(), 'items' => []];
        }

        return $result;
    }

}
