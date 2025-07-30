<?php

namespace Messaging\Services;

use SplitPHP\Service;
use SplitPHP\Helpers;
use SplitPHP\Utils;
use SplitPHP\Exceptions\FailedValidation;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;

class Push extends Service
{
  const TABLE = "MSG_PUSH_SUBSCRIPTION";

  public function __construct()
  {
    define('FIREBASE_PUSH_URL', getenv('FIREBASE_PUSH_URL'));
  }

  public function createSubcription($data)
  {
    // Removes forbidden fields from $data
    $data = $this->getService('utils/misc')->dataBlackList($data, [
      'id_msg_push',
      'ds_key',
      'dt_created',
      'dt_updated',
      'id_iam_user_created',
      'id_iam_user_updated'
    ]);

    // Formatting Subscription Data to insert in DB
    $localData['vl_expiration_time'] = $data['expirationTime'] ?? null;
    $localData['ds_token'] = Utils::dataEncrypt($data['token'], PUBLIC_KEY);

    // Set refs
    $user = $this->getService('iam/session')->getLoggedUser();

    if (empty($user)) {
      throw new FailedValidation('Usuário precisa estar logado para receber notificações');
    }

    // Set default values
    $localData['ds_key'] = 'sub-' . uniqid();
    $localData['id_iam_user_created'] = $user->id_iam_user;
    $localData['id_iam_user'] = $user->id_iam_user;

    return $this->getDao(self::TABLE)->insert($localData);
  }

  public function refreshToken($oldToken, $newToken)
  {
    return $this->getDao(self::TABLE)
      ->filter('ds_token')->equalsTo(Utils::dataEncrypt($oldToken, PUBLIC_KEY))
      ->update([
        'dt_updated' => date('Y-m-d H:i:s'),
        'ds_token' => Utils::dataEncrypt($newToken, PUBLIC_KEY)
      ]);
  }

  /**
   * Envia uma notifiação push para o FCM
   * @param $params Array associativo contendo os campos:\n
   * - ds_headline: título
   * - ds_brief: resumo
   * - ds_content: texto completo
   * - id_iam_user_recipient: usuário(s) alvo
   * - do_important: flag de importante
   */
  public function sendPushNotification($notification)
  {
    // -- OAuth 2.0 Token
    require ROOT_PATH . '/vendors/autoload.php';

    $credential = new ServiceAccountCredentials(
      "https://www.googleapis.com/auth/firebase.messaging",
      json_decode(file_get_contents(ROOT_PATH . "/pvKey.json"), true)
    );
    $pwaSettings = $this->getService('settings/settings')->contextObject('pwa');

    $authToken = $credential->fetchAuthToken(HttpHandlerFactory::build());

    // -- FCM Message Info
    $message = [
      'message' => [
        'token'        => $notification->ds_token,
        'notification' => [
          'title' => $notification->ds_title,
          'body'  => $notification->ds_body,
        ],
        'android' => [
          'notification' => [
            'icon'  => $pwaSettings->icon_192,
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
            'icon'    => $pwaSettings->icon_192,
            'badge'  => $pwaSettings->icon_128,
            'vibrate' => [200, 100, 200]
          ],
          'fcm_options' => [
            'link' => $notification->ds_link,
          ]
        ],
        'data' => [
          'url' => $notification->ds_link
        ],
      ]
    ];

    $response = Helpers::cURL()
      ->setHeader('Content-Type: application/json')
      ->setHeader('Authorization: Bearer ' . $authToken['access_token'])
      ->setDataAsJson($message)
      ->post(FIREBASE_PUSH_URL);

    if ($response->status != 200) {
      Helpers::Log()->common('push_notification_error', [
        'date' => date('Y-m-d H:i:s'),
        ...(array)$response
      ]);
    }

    return $response;
  }

  public function addToQueue($notification)
  {
    $queue = [];
    $this->getDao('MSG_PUSH')
      ->filter('id_iam_user')->equalsTo($notification->id_iam_user_recipient)
      ->fetch(function ($subscription) use (&$queue, $notification) {
        $queue[] = $this->getDao('MSG_PUSH_QUEUE')
          ->insert([
            'ds_token' => $subscription->ds_token,
            'ds_title' => $notification->ds_headline,
            'ds_body' => $notification->ds_brief,
            'tx_image' => null,
            'ds_link' => "/notifications"
          ]);
      }, "SELECT `ds_token` FROM `MSG_PUSH` WHERE id_iam_user = ?id_iam_user?");

    return $queue;
  }
}
