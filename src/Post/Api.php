<?php

namespace Screencom\MagicinfoApi\Post;

class Api extends Magicinfo
{
    protected $loginendpoint;

    protected $csrfendpoint;

    /**
     * Api constructor.
     * @param $endpoint
     * @param $username
     * @param $password
     * @throws \Exception
     */
    public function __construct($endpoint, $username, $password)
    {
        parent::__construct($endpoint, $username, $password, false);

        $this->loginendpoint = join('/', [$this->endpoint, 'j_spring_security_check']);
        $this->csrfendpoint = join('/', [$this->endpoint, 'login.htm?cmd=INIT']);

        $this->login();
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function csrf()
    {
        try {
            $response = $this->client->get($this->csrfendpoint);

            $body = $response->getBody()->getContents();

            if (preg_match('/<input type="hidden" value="(.*?)" name="_csrf">/', $body, $match)) {
                return $match[1];
            }

            throw new \Exception('Unable to retrieve csrf token @ ' . $this->csrfendpoint);
        } catch (\Exception $e) {
            throw new \Exception('Unable to retrieve csrf token @ ' . $this->csrfendpoint);
        }

    }

    /**
     * @throws \Exception
     */
    public function login()
    {
        $response = $this->client->post($this->loginendpoint, [
            'form_params'    => [
                'j_username' => $this->username,
                'j_password' => $this->password,
                '_csrf'      => $this->csrf()
            ],
            'headers'        => [
                'Accept'  => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Referer' => $this->endpoint . '/login.htm?cmd=INIT'
            ],
            //'connect_timeout' => 5,
            //'debug' => true
        ]);

        // $body = $response->getBody()->getContents();
    }

    /**
     * @param $zipfile
     * @param $contentId
     *
     * @return stdClass
     */
    public function uploadWebPackage($zipfile, $contentId)
    {
        return $this->uploadWebContent($zipfile, $contentId);
    }

    /**
     * @param $filename
     * @param $contentId
     * @return mixed
     */
    public function uploadWebContent($filename, $contentId)
    {
        $response = $this->client->get($this->endpoint . '/content/getContentView.htm?cmd=VIEW&contentId=' . $contentId . '&_=' . (time() * 1000));

        $body = $response->getBody()->getContents($filename, $contentId);

        $menu = \GuzzleHttp\json_decode($body)->result->menu;

        $query = http_build_query([
            'groupId'         => '',
            'contentType'     => $menu->mediaType,
            'webContentName'  => $menu->contentName,
            'startPage'       => $menu->html_start_page,
            'mode'            => 'update',
            'contentId'       => $contentId,
            'refreshInterval' => $menu->refresh_interval,
        ]);

        $response = $this->client->post($this->endpoint . '/servlet/ContentFileUpload?' . $query, [
            'multipart' => [
                [
                    'name'     => basename($filename),
                    'contents' => fopen($filename, 'r')
                ]
            ],
            //'connect_timeout' => 5,
            //'debug' => true
        ]);

        return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }

    /**
     * @param $filename
     * @param $contentId
     * @return mixed
     *
     * curl 'http://192.168.0.69:7001/MagicInfo/servlet/ContentFileUpload?contentId=BA6425E4-FE1D-48DC-ADCA-92D5B0DF8957'
     * -H 'Cookie: JSESSIONID=34B0A599D869F01AE3285E670081AE3B; magicInfoUserId=; org.springframework.web.servlet.i18n.CookieLocaleResolver.LOCALE=en; MagicInfoPremiumLanguage=en; pageMode_contentTab_admin=image; contentTab_pageSizeadmin=25'
     * -H 'Origin: http://192.168.0.69:7001'
     * -H 'Accept-Encoding: gzip, deflate'
     * -H 'X-CSRF-Token: 1705d442-da9c-4a94-9519-eccec76aeb72'
     * -H 'Accept-Language: en-US,en;q=0.9,nl;q=0.8'
     * -H 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.120 Safari/537.36'
     * -H 'Content-Type: multipart/form-data; boundary=----WebKitFormBoundaryk6XnEgnWtqqa83P1'
     * -H 'Referer: http://192.168.0.69:7001/MagicInfo/login.htm?cmd=INIT'
     * -H 'X-Requested-With: XMLHttpRequest'
     * -H 'Connection: keep-alive'
     * -H 'AJAX: true'
     * --data-binary $'------WebKitFormBoundaryk6XnEgnWtqqa83P1\r\n
     * Content-Disposition: form-data; name="category"\r\n\r\n1\r\n
     * ------WebKitFormBoundaryk6XnEgnWtqqa83P1\r\n
     * Content-Disposition: form-data; name="file"; filename="reboot.png"\r\n
     * Content-Type: image/png\r\n\r\n\r\n------WebKitFormBoundaryk6XnEgnWtqqa83P1--\r\n'
     * --compressed --insecure
     */
    public function uploadImageContent($filename, $contentId)
    {
        $query = http_build_query([
            'contentId'         => $contentId,
        ]);

        $response = $this->client->post($this->endpoint . '/servlet/ContentFileUpload?' . $query, [
            'multipart' => [
                [
                    'name'     => 'category',
                    'contents' => 1,
                ],
                [
                    'name'     => 'file',
                    'contents' => fopen($filename, 'r')
                ]
            ],
        ]);

        return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }

    /**
     * @param $filename
     * @param $contentId
     *
     * @param string $type
     * @return mixed
     */
    public function uploadContent($filename, $contentId, $type = 'web')
    {
        // retrieve view first
        if ($type == 'web') {
            return $this->uploadWebContent($filename, $contentId);
        } else {
            return $this->uploadImageContent($filename, $contentId);
        }
    }

    /**
     * @param $data
     *
     * user_id: misadmin3
     * new_organization: MIS3
     * group_name: default
     * password: misadmin3misadmin3
     * user_name: misadmin3
     * email: misadmin3@screencom.eu
     * role_name: Administrator
     *
     * @return mixed
     */
    public function createUser($data)
    {
        /**
         * &user_id=misadmin3&new_organization=MIS3&group_name=default&password=misadmin3misadmin3
         * &user_name=misadmin3&email=misadmin3%40screencom.eu&mobile_num=&phone_num=&role_name=Administrator&team_name=&job_position=&_=1569588804903
         */

        $user_query = http_build_query($data);

        $url = $this->endpoint . '/user/getUser.htm?cmd=saveOrg&' . $user_query;

        $response = $this->client->get($url);

        return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }
}
