<?php

namespace Messaging\Routes;

use Exception;
use SplitPHP\Exceptions\BadRequest;
use SplitPHP\Exceptions\FailedValidation;
use SplitPHP\Request;
use SplitPHP\Utils;
use SplitPHP\WebService;

class Messaging extends WebService
{
  /////////////////
  // NOTIFICATION ENDPOINTS:
  /////////////////
  public function init(): void
  {
    $this->setAntiXsrfValidation(false);

    if (!$this->getService('modcontrol/control')->moduleExists('iam'))
      throw new Exception('Module "iam" is required for messaging to work. Install it with "composer require lambdatt-php/iam"');

    $this->addEndpoint('GET', '/v1/notification/headlines', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $loggedUser = $this->getService('iam/session')->getLoggedUser();
      $params['id_iam_user_recipient'] = $loggedUser->id_iam_user;

      $result = $this->getService('messaging/notification')->listHeadlines($params);
      return $this->response->withStatus(200)->withData($result);
    });

    $this->addEndpoint('GET', '/v1/notification/count-unread', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $count = $this->getService('messaging/notification')->countUnread();

      return $this->response->withText($count);
    });

    // SSE stream — real-time notification signal for the logged-in user.
    // The response is streamed directly by the service (headers + event loop);
    // the framework response object is intentionally left empty/unused.
    $this->addEndpoint('GET', '/v1/notification/watch', function ($params) {
      // EventSource cannot set custom headers, so the session key is passed as
      // a query param (?iam_session_key=...) and injected into the unified
      // request context so that iam/session->authenticate() can find it.
      if (!empty($params['iam_session_key'])) {
        Request::setContext('iam_session_key', $params['iam_session_key']);
      }

      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $this->getService('messaging/notification')->watchCount();
      exit; // SSE loop ended gracefully — prevent framework from calling respond(null)
    });

    $this->addEndpoint('GET', '/v1/notification', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $usr = $this->getService('iam/session')->getLoggedUser();
      $params['id_iam_user_recipient'] = $usr->id_iam_user;

      return $this->response->withData($this->getService('messaging/notification')->list($params), false);
    });

    $this->addEndpoint('POST', '/v1/notification', function (Request $request) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $data = $request->getBody();
      $ntfObj = $this->getService('messaging/notification')->create($data);

      return $this->response
        ->withStatus(201)
        ->withData($ntfObj);
    });

    $this->addEndpoint('POST', '/v1/notification/team/?teamTag?', function (Request $request, $teamTag) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $data = $request->getBody();
      $this->getService('messaging/notification')->addToTeam($teamTag, $data);

      return $this->response
        ->withStatus(201);
    });

    $this->addEndpoint('PUT', '/v1/notification/mark-as-read/?key?', function ($key) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      $params = [
        'ds_key' => $key,
      ];

      $rows = $this->getService('messaging/notification')->markAsRead($params);
      if ($rows < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    });

    $this->addEndpoint('DELETE', '/v1/notification/?key?', function ($key) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      $params = [
        'ds_key' => $key,
      ];
      $deleted = $this->getService('messaging/notification')->remove($params);

      if (!$deleted) return $this->response->withStatus(404);

      return $this->response
        ->withStatus(204);
    });

    /////////////////
    // PUSH ENDPOINTS:
    /////////////////
    $this->addEndpoint('POST', "/v1/push/subscription", function ($data) {
      $deviceKey = $_SERVER['HTTP_IAM_DEVICE_KEY'];
      if (empty($deviceKey)) throw new BadRequest('No Device key could be found on the request.');

      $device = $this->getService('iam/device')->get([
        'ds_key' => Utils::dataDecrypt($deviceKey, APP_SECRET)
      ]);
      if (empty($device)) throw new FailedValidation('No Device found for the given key.');

      $data['id_iam_device'] = $device->id_iam_device;

      $record = $this->getService('messaging/push')->createSubcription($data);

      return $this->response
        ->withStatus(201)
        ->withData($record);
    }, false);

    // Atualiza a Inscrição
    $this->addEndpoint('PUT', "/v1/push/subscription/?oldToken?/?newToken?", function ($oldToken, $newToken) {
      $this->getService('messaging/push')->refreshToken($oldToken, $newToken);

      return $this->response->withStatus(204);
    }, false);

    /////////////////
    // CHAT ENDPOINTS:
    /////////////////
    $this->addEndpoint('GET', '/v1/chat-summary/?reader?', function ($reader, Request $request) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $records = $this->getService('messaging/chat')->list($reader, $request->getBody());
      return $this->response->withStatus(200)->withData($records);
    });

    $this->addEndpoint('PUT', '/v1/chat/?key?/close', function ($key) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $filters = ['ds_key' => $key];
      $rowsAffected = $this->getService('messaging/chat')->close($filters);

      if ($rowsAffected < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    });

    $this->addEndpoint('DELETE', '/v1/chat/?key?', function ($key) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $filters = ['ds_key' => $key];
      $deleted = $this->getService('messaging/chat')->remove($filters);

      if (!$deleted) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    });

    $this->addEndpoint('GET', '/v1/chat-message/count-unread/?reader?', function ($reader) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $count = $this->getService('messaging/chatmessage')->countUnread($reader);

      return $this->response
        ->withStatus(200)
        ->withText($count);
    });

    $this->addEndpoint('GET', '/v1/chat-message/?chatKey?/?reader?', function ($chatKey, $reader) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $chat = $this->getService('messaging/chat')->get($reader, [
        'ds_key' => $chatKey,
      ]);
      if (empty($chat)) return $this->response->withStatus(404);

      $messages = $this->getService('messaging/chatmessage')->list([
        'id_msg_chat' => $chat->id_msg_chat,
      ], $reader);
      return $this->response->withStatus(200)->withData($messages);
    });

    $this->addEndpoint('POST', '/v1/chat-message/?chatKey?/?participant?', function ($chatKey, $participant, Request $request) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $data = $request->getBody();
      $chat = $this->getService('messaging/chat')->get($participant, ['ds_key' => $chatKey]);
      if (empty($chat)) return $this->response->withStatus(404);

      $data['id_msg_chat'] = $chat->id_msg_chat;
      $record = $this->getService('messaging/chatmessage')->create($data);

      return $this->response
        ->withStatus(201)
        ->withData($record);
    });

    $this->addEndpoint('DELETE', '/v1/chat-message/?key?', function ($key) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $filters = ['ds_key' => $key];
      $deleted = $this->getService('messaging/chatmessage')->remove($filters);

      if (!$deleted) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    });
  }
}
