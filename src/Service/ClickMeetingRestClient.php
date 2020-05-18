<?php

namespace App\Service;

use App\Model\PaymentInterface;
use Exception;

/**
 * ClickMeeting REST client
 */
class ClickMeetingRestClient
{

    /**
     * @var PaymentInterface
     */
    private ?PaymentInterface $sessionPayment;

    /**
     * API url
     * @var string
     */
    protected $url = 'https://api.clickmeeting.com/v1/';

    /**
     * API key
     * @var string
     */
    protected $api_key = null;

    /**
     * Format
     * @var string
     */
    protected $format = null; // json, xml, printr, js

    /**
     * Curl options
     * @var options
     */
    protected $curl_options = array(
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 8
    );

    /**
     * Error codes
     * @var array
     */
    protected $http_errors = array
    (
        400 => '400 Bad Request',
        401 => '401 Unauthorized',
        403 => '403 Forbidden',
        404 => '404 Not Found',
        422 => '422 Unprocessable Entity',
        500 => '500 Internal Server Error',
        501 => '501 Not Implemented',
    );

    /**
     * Allowed formats
     * @var unknown
     */
    protected $formats = array('json', 'xml', 'js', 'printr');

    /**
     * Constructor
     * @param array $params
     * @throws Exception
     */
    public function __construct(PayPalClient $payPalClient, string $apiKey, string $format)
    {
        if (false === extension_loaded('curl'))
        {
            throw new Exception('The curl extension must be loaded for using this class!');
        }

        $this->sessionPayment = $payPalClient->getSessionPayment();
        $this->api_key = $apiKey ?? $this->api_key;
        $this->format = $format && in_array(strtolower($format), $this->formats, true) ? strtolower($format) : $this->format;
    }

    /**
     * @return bool|string
     * @throws Exception
     */
    public function getConferenceLink(){
        // here should be some real data
        $params = array(
            'lobby_enabled' => true,
            'lobby_description' => 'My meeting',
            'name' => 'test_room',
            'room_type' => 'meeting',
            'permanent_room' => 0,
            'access_type' => 3,
            'registration' => array(
                'template' => 1,
                'enabled' => false
            ),
            'settings' => array(
                'show_on_personal_page' => 1,
                'thank_you_emails_enabled' => 1,
                'connection_tester_enabled' => 1,
                'phonegateway_enabled' => 1,
                'recorder_autostart_enabled' => 1,
                'room_invite_button_enabled' => 1,
                'social_media_sharing_enabled' => 1,
                'connection_status_enabled' => 1,
                'thank_you_page_url' => 'http://example.com/thank_you.html',
            ),
        );
        $room = $this->addConference($params);
        $room = json_decode($room)->room;

        $params = array(
            'email' => $this->sessionPayment->getEmail(), // email address
            'nickname' => $this->sessionPayment->getEmail(), // user nickname
            'role' => 'listener', // user role, other: presenter, host
        );

        try {
            $hash = $this->conferenceAutologinHash($room->id, $params);
        } catch (\Exception $e) {
            return false;
        }

        return $room->room_url. '?I='.$room->autologin_hash;
    }

    /**
     * @param array $params
     * @return array|string
     * @throws Exception
     */
    public function addConference(array $params)
    {
        return $this->sendRequest('POST', 'conferences', $params);
    }

    /**
     * @param $room_id
     * @param array $params
     * @return array|string
     * @throws Exception
     */
    public function conferenceAutologinHash($room_id, array $params)
    {
        return $this->sendRequest('POST', 'conferences/'.$room_id.'/room/autologin_hash', $params);
    }


    /**
     * Get response
     * @param string $method
     * @param string $path
     * @param array $params
     * @param bool $format_response
     * @param bool $is_upload_file
     * @throws Exception
     * @return string|array
     */
    protected function sendRequest($method, $path, $params = null, $format_response = true, $is_upload_file = false)
    {
        // do the actual connection
        $curl = curl_init();

        // set URL
        curl_setopt($curl, CURLOPT_URL, $this->url.$path.'.'.(isset($this->format) ? $this->format : 'json'));
        // set api key
        $headers = array( 'X-Api-Key:' . $this->api_key);

        // is uplaoded file
        if (true == $is_upload_file)
        {
            $headers[] = 'Content-type: multipart/form-data';
        }

        switch ($method) {
            case 'GET':
                curl_setopt($curl, CURLOPT_HTTPGET, true);
                break;
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, true);
                $headers[] = 'Expect:';
                break;
            case 'PUT':
                if(empty($params))
                {
                    $headers[] = 'Content-Length: 0';
                }
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
                break;
            default:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        // add params
        if (!empty($params))
        {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $is_upload_file ? $params : http_build_query($params));
        }

        curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt_array($curl, $this->curl_options);
        // send the request
        $response = curl_exec($curl);

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (isset($this->http_errors[$http_code]))
        {
            throw new Exception($response, $http_code);
        }
        elseif (!in_array($http_code, array(200,201)))
        {
            throw new Exception('Response status code: ' . $http_code);
        }

        // check for curl error
        if (0 < curl_errno($curl))
        {
            throw new Exception('Unable to connect to '.$this->url . ' Error: ' . curl_error($curl));
        }

        // close the connection
        curl_close($curl);

        // check return format
        if (!isset($this->format) && true == $format_response)
        {
            $response = json_decode($response);
        }
        return $response;
    }
}