<?php

namespace Messaging\Routes;

use SplitPHP\WebService;

class Messaging extends WebService
{
  /////////////////
  // NOTIFICATION ENDPOINTS:
  /////////////////
  public function init(): void
  {
    $this->addEndpoint('GET', '/v1/notification/headlines', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $result = $this->getService('messaging/notification')->listHeadlines($params);
      return $this->response->withStatus(200)->withData($result);
    });

    // Count
    $this->addEndpoint('GET', '/v1/notification/count', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $usr = $this->getService('iam/session')->getLoggedUser();
      $params['id_iam_user_recipient'] = $usr->id_iam_user;
      $params['do_read'] = 'N';

      $count = count($this->getService('messaging/notification')->list($params, false));

      return $this->response->withText($count);
    });

    $this->addEndpoint('GET', '/v1/notification', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $usr = $this->getService('iam/session')->getLoggedUser();
      $params['id_iam_user_recipient'] = $usr->id_iam_user;

      return $this->response->withData($this->getService('messaging/notification')->list($params), false);
    });

    $this->addEndpoint('POST', '/v1/notification', function ($data) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate(false)) return $this->response->withStatus(401);

      $ntfObj = $this->getService('messaging/notification')->create($data);

      return $this->response
        ->withStatus(201)
        ->withData($ntfObj);
    });

    $this->addEndpoint('PUT', '/v1/notification/mark-as-read/?notificationKey?', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      $rows = $this->getService('messaging/notification')->markAsRead(['ds_key' => $params['notificationKey']]);
      if ($rows < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    });

    $this->addEndpoint('DELETE', '/v1/notification/?notificationKey?', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      $deleted = $this->getService('messaging/notification')->remove(['ds_key' => $params['notificationKey']]);

      if (!$deleted) return $this->response->withStatus(404);

      return $this->response
        ->withStatus(204);
    });

    /////////////////
    // PUSH ENDPOINTS:
    /////////////////

    $endpoint = '/v1/push';

    // Cria a Inscrição
    $this->addEndpoint('POST', "{$endpoint}/subscription", function ($data) {
      return $this->response
        ->withStatus(200)
        ->withData($this->getService('messaging/push')->createSubcription($data));
    }, false);

    // Atualiza a Inscrição
    $this->addEndpoint('PUT', "{$endpoint}/subscription/?oldToken?", function ($data) {
      $oldToken = $data['oldToken'];
      $newToken = $data['newToken'];

      $this->getService('messaging/push')->refreshToken($oldToken, $newToken);

      return $this->response->withStatus(204);
    }, false);
  }
}
