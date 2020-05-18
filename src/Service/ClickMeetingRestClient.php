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
     * Session interface to fetch user's data on join meeting action.
     */
    private ?PaymentInterface $sessionPayment;

    /**
     * API url
     */
    protected string $url;

    /**
     * API key
     */
    protected string $api_key;

    /**
     * Format
     */
    protected string $format;

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
    public function __construct(PayPalClient $payPalClient, string $baseUrl, string $apiKey, string $format)
    {
        if (false === extension_loaded('curl'))
        {
            throw new Exception('The curl extension must be loaded for using this class!');
        }

        $this->url = $baseUrl;
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
        $params = [
            'lobby_enabled' => true,
            'lobby_description' => 'Webinar Damian Wiśniewski',
            'name' => 'Pokój testowy',
            'room_type' => 'meeting',
            'permanent_room' => 0,
            'access_type' => 1,
        ];
        $room = $this->addConference($params);
        $room = json_decode($room)->room;

        $params = [
            'email' => $this->sessionPayment->getEmail(),
            'nickname' => $this->sessionPayment->getNickName(),
            'role' => 'listener',
        ];

        try {
            $hash = $this->conferenceAutologinHash($room->id, $params);
        } catch (\Exception $e) {
            return false;
        }

        return $room->room_url. '?l='.$room->autologin_hash;
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