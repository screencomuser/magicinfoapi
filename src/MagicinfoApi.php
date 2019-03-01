<?php

namespace Screencom\MagicinfoApi;

use Exception;

class MagicinfoApi
{

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $token;

    /**
     * MagicinfoApi constructor.
     *
     * @param string $base_uri
     */
    public function __construct($base_uri = '')
    {
        if (! empty($base_uri)) {
            $this->setBaseUri($base_uri);
        }
    }

    /**
     * @param string $base_uri
     *
     * @return MagicinfoApi
     */
    public function setBaseUri($base_uri)
    {
        // make sure an / is at the end
        $base_uri = rtrim($base_uri, '/') . '/';

        $this->client = new \GuzzleHttp\Client([
            'base_uri' => $base_uri,
            'timeout'  => 10.0,
            'debug'    => false,
        ]);

        return $this;
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function fetchToken()
    {
        $xml = $this->execRequest(sprintf('auth?cmd=getAuthToken&id=%1$s&pw=%2$s', $this->username,
            $this->password), false);
        $this->setToken($xml->responseClass);

        return $this;
    }

    /**
     * @param      $request
     * @param bool $addToken
     *
     * @return \SimpleXMLElement
     * @throws Exception
     */
    protected function execRequest($request, $addToken = true)
    {
        if ($addToken) {
            $request = $this->addTokenToRequest($request);
        }

        $response = $this->client->get($request);

        if ($response->getStatusCode() != 200) {
            throw new Exception(__CLASS__ . ' : response error, code = ' . $response->getStatusCode(),
                $response->getStatusCode());
        }

        $xml = new \SimpleXMLElement($response->getBody()->getContents(), LIBXML_NOCDATA);

        /*
        try {
        } catch (\Exception $e) {
            file_put_contents(
                env('STORAGE_PATH') . '/logs/openapi-response.log',
                $response->getBody()->getContents() . PHP_EOL . str_repeat('=', 80) . PHP_EOL,
                FILE_APPEND
            );

            return false;
        }
        */

        if ((string)$xml['code']) {
            throw new Exception($xml->errorMessage);
        }

        return $xml;
    }

    /**
     * @param $request
     *
     * @return string
     */
    protected function addTokenToRequest($request)
    {
        $url            = parse_url($request);
        $query          = \GuzzleHttp\Psr7\parse_query($url['query']);
        $query['token'] = $this->getToken();
        $request        = $url['path'] . '?' . \GuzzleHttp\Psr7\build_query($query);

        return $request;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param string $token
     */
    public function setToken($token)
    {
        $this->token = (string)$token;
    }

    /**
     * @param $name
     * @param $user
     *
     * @return mixed
     * @throws Exception
     */
    public function addOrganization($name, $user)
    {
        $newAdmin = new \SimpleXMLElement('<User/>', LIBXML_NOENT);

        foreach ($user as $key => $value) {
            switch ($key) {
                case 'phone_num' :
                case 'mobile_num' :
                    $value = str_replace(['+', ' '], ['00', ''], $value);
                    break;
            }
            $newAdmin->$key = $value;
        }

        $userXML = str_replace(PHP_EOL, '', $newAdmin->asXML());
        $userXML = str_replace('<?xml version="1.0"?>', '', $userXML);
        $userXML = html_entity_decode($userXML, ENT_NOQUOTES, 'UTF-8');

        $xml = $this->execRequest('open?service=CommonUserService.addOrganization&organName=' . $name . '&newAdmin=' . $userXML);

        return $xml;
    }

    /**
     * @param $request
     *
     * @return \SimpleXMLElement
     * @throws Exception
     */
    protected function execPostRequest($request)
    {

        $post = [
            'token' => $this->getToken(),
            'xml'   => $request->asXML(),
        ];

        file_put_contents(env('STORAGE_PATH') . '/logs/openapi.log', var_export($post, true) . PHP_EOL);

        try {
            $response = $this->client->post('open', ['form_params' => $post]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {

            file_put_contents(env('STORAGE_PATH') . '/logs/openapi-response.log', $e->getResponse()->getBody()
                                                                                    ->getContents() . PHP_EOL . str_repeat('=', 80) . PHP_EOL);

        }

        if ($response->getStatusCode() != 200) {
            throw new Exception(__CLASS__ . ' : response error, code = ' . $response->getStatusCode(),
                $response->getStatusCode());
        }

        $xml = new \SimpleXMLElement($response->getBody()->getContents(), LIBXML_NOCDATA);

        if ((string)$xml['code']) {
            throw new Exception($xml->errorMessage);
        }

        return $xml;
    }

    /**
     * @param User $user
     *
     * @todo Afmaken!
     *
     * @return \SimpleXMLElement
     * @throws Exception
     */
    public function disableUser(User $user)
    {

        return false;

        $request                                            = new \SimpleXMLElement('<request/>');
        $request->service->id                               = 'CommonUserService.modifyUser';
        $request->service->parameters->user->User->user_id  = strtolower(str_replace(' ', '', $user->name));
        $request->service->parameters->user->User->password = sha1(microtime());

        $xml = $this->execPostRequest($request);

        $xml = $this->execRequest('open?service=CommonUserService.modifyUser&organName=' . $name . '&newAdmin=' . $userXML);

        return $xml;
    }

    /**
     * @param $userId
     * @param string $reason
     *
     * @return \SimpleXMLElement
     * @throws Exception
     */
    public function deleteUser($userId, $reason = 'delete')
    {
        $xml = $this->execRequest('open?service=CommonUserService.deleteUser&userId=' . $userId . '&delReason=' . $reason);

        return $xml;

    }

    /**
     * @param $userGroupId
     * @param string $reason
     *
     * @return mixed
     * @throws Exception
     */
    public function deleteOrganization($userGroupId, $reason = 'delete')
    {
        $xml = $this->execRequest('open?service=CommonUserService.deleteOrganization&userGroupId=' . $userGroupId . '&delReason=' . $reason);

        return $xml;

    }

    /**
     * @param int $userGroupId
     *
     * @return \SimpleXMLElement
     * @throws Exception
     */
    public function fetchUserList($userGroupId = 0)
    {
        $xml = $this->execRequest('open?service=CommonUserService.getUserList&groupId=' . $userGroupId . '&isAll=true');

        return $xml;

    }

    /**
     * @param     $user_id
     *
     * @return bool|string
     * @throws Exception
     */
    public function findUserId($user_id)
    {
        $response = $this->fetchUserList();

        foreach ($response->responseClass->resultList->User as $item) {
            if ($item->user_id == $user_id) {
                return (string)$item->user_id;
            }
        }

        return false;
    }

    /**
     * @return stdClass
     * @throws Exception
     */
    public function fetchOrganizationList()
    {
        $xml = $this->execRequest('open?service=CommonUserService.getOrganizationList');

        return $xml;

    }

    /**
     * @param string $groupName
     *
     * @return bool|int
     * @throws Exception
     */
    public function findOrganizationId($groupName)
    {
        $response = $this->fetchOrganizationList();

        foreach ($response->responseClass->resultList->UserGroup as $item) {
            if ($item->group_name == $groupName) {
                return (int)$item->group_id;
            }
        }

        return false;
    }

    /**
     * @return \GuzzleHttp\Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param $client
     *
     * @return $this
     */
    public function setClient($client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param $username
     *
     * @return $this
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param $password
     *
     * @return $this
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;

    }

    /**
     * @param $user
     *
     * @return mixed
     */
    protected function makeUserObject($user)
    {
        $xml = new \SimpleXMLElement('<User/>');
        foreach ($user as $key => $value) {
            $xml->$key = $value;
        }

        return $xml; // explode(PHP_EOL, $xml->asXML(), 2)[1];
    }

}
