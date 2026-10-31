<?php

namespace Messaging\Services;

use Exception;
use SplitPHP\Service;
use SplitPHP\Helpers;
use SplitPHP\Utils;
use SplitPHP\Exceptions\FailedValidation;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use Throwable;

class Push extends Service
{
  private const TABLE_SUBSCRIPTION = "MSG_PUSH_DEVICE_SUBSCRIPTION";
  private const TABLE_QUEUE = "MSG_PUSH_QUEUE";

  private static $KEY_FILEPATH;

  public function __construct()
  {
    if (!defined('PUSH_SERVER_URL')) {
      define('PUSH_SERVER_URL', getenv('PUSH_SERVER_URL'));
    }
    self::$KEY_FILEPATH = ROOT_PATH . getenv('FCM_KEY_FILEPATH');

    if (!defined('MSG_PUSH_ENABLED'))
      define('MSG_PUSH_ENABLED', getenv('MSG_PUSH_ENABLED') === 'on');
  }

  public function createSubcription($data)
  {
    // Removes forbidden fields from $data
    $data = $this->getService('utils/misc')->dataWhiteList($data, [
      'id_iam_device',
      'nr_expiration_time',
      'tx_token'
    ]);

    if (empty($data['id_iam_device']))
      throw new FailedValidation('Device ID is required');

    if (empty($data['tx_token']))
      throw new FailedValidation('Token is required');

    // Formatting Subscription Data to insert in DB
    $data['tx_token'] = Utils::dataEncrypt($data['tx_token'], APP_SECRET);

    // Set default values
    $data['ds_key'] = 'sub-' . uniqid();

    return $this->getDao(self::TABLE_SUBSCRIPTION)->insert($data);
  }

  public function refreshToken($oldToken, $newToken)
  {
    return $this->getDao(self::TABLE_SUBSCRIPTION)
      ->filter('tx_token')->equalsTo(Utils::dataEncrypt($oldToken, APP_SECRET))
      ->update([
        'dt_updated' => date('Y-m-d H:i:s'),
        'tx_token' => Utils::dataEncrypt($newToken, APP_SECRET)
      ]);
  }

  public function send($token, $data)
  {
    $token = Utils::dataDecrypt($token, APP_SECRET);

    // Handles data
    $data = (object) $data;
    if (empty($data->title) || empty($data->body))
      throw new Exception('You cannot send a push notification without a title or body.');

    // Get icons if appdata module exists
    $hasAppDataMod = $this->getService('modcontrol/control')->moduleExists('appdata');
    $icons = $hasAppDataMod ? $this->getService('appdata/icon')->getIcons() : [];

    // OAuth 2.0 Token
    require ROOT_PATH . '/vendor/autoload.php';

    $credential = new ServiceAccountCredentials(
      "https://www.googleapis.com/auth/firebase.messaging",
      json_decode(file_get_contents(self::$KEY_FILEPATH), true)
    );

    $authToken = $credential->fetchAuthToken(HttpHandlerFactory::build());

    // -- FCM Message Info
    $message = [
      'message' => [
        'token'        => $token,
        'notification' => [
          'title' => $data->title,
          'body'  => $data->body,
        ],
        'android' => [
          'notification' => [
            'icon'  => $icons['192'] ?? '',
            'sound' => 'default',
          ]
        ],
        'apns' => [
          'payload' => [
            'aps' => [
              'sound' => 'default',
            ]
          ]
        ],
        'webpush' => [
          'notification' => [
            'icon'    => $icons['192'] ?? '',
            'badge'  => $icons['128'] ?? '',
            'vibrate' => [200, 100, 200]
          ],
          'fcm_options' => [
            'link' => $data->link ?? '',
          ]
        ],
        'data' => [
          'url' => $data->link ?? ''
        ],
      ]
    ];

    $response = Helpers::cURL()
      ->setHeader('Content-Type: application/json')
      ->setHeader('Authorization: Bearer ' . $authToken['access_token'])
      ->setDataAsJson($message)
      ->post(PUSH_SERVER_URL);

    if ($response->status != 200)
      Helpers::Log()->error('push_notification_error', $response);

    return $response;
  }

  /**
   * Envia uma notifiação push para o FCM
   * 
   * @param $deviceFilters Array associativo contendo os filtros para encontrar o dispositivo destinatário
   * @param $data Array associativo contendo os campos:
   * - title: título
   * - body: corpo da mensagem
   * - link: link
   */
  public function sendToDevice(array|object $deviceFilters, array|object $data)
  {
    if (!MSG_PUSH_ENABLED)
      return;

    if (!$this->getService('modcontrol/control')->moduleExists('iam'))
      throw new Exception('Module "iam" is required for identified-device push notifications to work. Install it with "composer require lambdatt-php/iam"');

    // Check for valid device
    $deviceId = $this->getService('iam/device')->get($deviceFilters)?->id_iam_device;
    if (empty($deviceId))
      throw new Exception('No valid device was found with the provided parameters: ' . json_encode($deviceFilters));

    // Retrieve Push Subscription Token
    $subscription = $this->getDao(self::TABLE_SUBSCRIPTION)
      ->filter('id_iam_device')->equalsTo($deviceId)
      ->first();
    if (empty($subscription) || empty($subscription->tx_token))
      throw new Exception('Device has not a valid push subscription.');

    $token = $subscription->tx_token;

    $this->send($token, $data);
  }

  public function addToQueue(string $token, array|object $data)
  {
    if (!MSG_PUSH_ENABLED)
      return;

    if (empty($token))
      throw new Exception('You cannot send a push notification without a token.');

    $data = (object) $data;
    $data->icon = !empty($data->icon) ? (array) $data->icon : [];

    if (empty($data->title) || empty($data->body))
      throw new Exception('You cannot send a push notification without a title or body.');

    $pushData = [
      'tx_token' => $token,
      'ds_title' => $data->title,
      'ds_body' => $data->body,
      'ds_icon_192' => $data->icon['192'] ?? '',
      'ds_icon_128' => $data->icon['128'] ?? '',
      'ds_image' => $data->image ?? '',
      'ds_link' => $data->link ?? '',
    ];

    return $this->getDao(self::TABLE_QUEUE)->insert($pushData);
  }

  /**
   * Sends a push notification to a FCM topic.
   * All devices subscribed to the topic will receive the notification.
   *
   * @param string $topic The topic name (e.g. "cartappio_localhost_dev")
   * @param array|object $data Must contain: title, body. Optional: link, image
   */
  public function sendToTopic(string $topic, array|object $data)
  {
    if (!MSG_PUSH_ENABLED)
      return;

    $data = (object) $data;
    if (empty($data->title) || empty($data->body))
      throw new Exception('You cannot send a push notification without a title or body.');

    // Get icons if appdata module exists
    $hasAppDataMod = $this->getService('modcontrol/control')->moduleExists('appdata');
    $icons = $hasAppDataMod ? $this->getService('appdata/icon')->getIcons() : [];

    // OAuth 2.0 Token
    require ROOT_PATH . '/vendor/autoload.php';

    $credential = new ServiceAccountCredentials(
      "https://www.googleapis.com/auth/firebase.messaging",
      json_decode(file_get_contents(self::$KEY_FILEPATH), true)
    );

    $authToken = $credential->fetchAuthToken(HttpHandlerFactory::build());

    // -- FCM Message Info (topic-based)
    $message = [
      'message' => [
        'topic'        => $topic,
        'notification' => [
          'title' => $data->title,
          'body'  => $data->body,
        ],
        'android' => [
          'notification' => [
            'icon'  => $icons['192'] ?? '',
            'sound' => 'default',
          ]
        ],
        'apns' => [
          'payload' => [
            'aps' => [
              'sound' => 'default',
            ]
          ]
        ],
        'webpush' => [
          'notification' => [
            'icon'    => $icons['192'] ?? '',
            'badge'   => $icons['128'] ?? '',
            'vibrate' => [200, 100, 200],
            'image'   => $data->image ?? '',
          ],
          'fcm_options' => [
            'link' => $data->link ?? '',
          ]
        ],
        'data' => [
          'url' => $data->link ?? ''
        ],
      ]
    ];

    $response = Helpers::cURL()
      ->setHeader('Content-Type: application/json')
      ->setHeader('Authorization: Bearer ' . $authToken['access_token'])
      ->setDataAsJson($message)
      ->post(PUSH_SERVER_URL);

    if ($response->status != 200)
      Helpers::Log()->error('push_topic_notification_error', $response);

    return $response;
  }

  /**
   * Subscribes a device token to a FCM topic.
   *
   * @param string $token The raw (decrypted) FCM token
   * @param string $topic The topic name to subscribe to
   */
  public function subscribeToTopic(string $token, string $topic)
  {
    if (!MSG_PUSH_ENABLED)
      return;

    // OAuth 2.0 Token
    require ROOT_PATH . '/vendor/autoload.php';

    $credential = new ServiceAccountCredentials(
      "https://www.googleapis.com/auth/firebase.messaging",
      json_decode(file_get_contents(self::$KEY_FILEPATH), true)
    );

    $authToken = $credential->fetchAuthToken(HttpHandlerFactory::build());

    $url = "https://iid.googleapis.com/iid/v1/{$token}/rel/topics/{$topic}";

    $response = Helpers::cURL()
      ->setHeader('Content-Type: application/json')
      ->setHeader('Authorization: Bearer ' . $authToken['access_token'])
      ->setDataAsJson([])
      ->post($url);

    if ($response->status != 200)
      Helpers::Log()->error('push_topic_subscribe_error', $response);

    return $response;
  }
}
