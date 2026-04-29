<?php

namespace Messaging\Services;

use Exception;

use SplitPHP\Helpers;
use SplitPHP\Service;

class Notification extends Service
{
  public function __construct()
  {
    if (!$this->getService('modcontrol/control')->moduleExists('iam'))
      throw new Exception('Module "iam" is required for notifications to work. Install it with "composer require lambdatt-php/iam"');

    if (!defined('MSG_PUSH_ENABLED'))
      define('MSG_PUSH_ENABLED', getenv('MSG_PUSH_ENABLED') === 'on');
  }

  public function list($params = [])
  {
    return $this->getDao('MSG_NOTIFICATION')
      ->bindParams($params)
      ->find('messaging/notifications/read');
  }

  /**
   * Opens an SSE stream and pushes a 'changed' event each time the logged
   * user receives a new notification (or reads / deletes one).
   *
   * When a Redis server and the infrastructure/redis service are available,
   * the stream uses a blocking SUBSCRIBE for instant event delivery.
   * Otherwise, it falls back to stash file polling.
   *
   * Called by GET /v1/notification/watch.
   */
  public function watchCount(): void
  {
    $loggedUsrId = $this->getService('iam/session')->getLoggedUser()?->id_iam_user;

    // Try Redis
    $useRedis = false;
    try {
      $redis = $this->getService('infrastructure/redis');
      $useRedis = $redis->isAvailable();
    } catch (\Throwable $e) {
      // Service does not exist or Redis unavailable — fall back to stash
    }

    if ($useRedis) {
      $this->watchCountViaRedis($redis, $loggedUsrId);
    } else {
      $this->watchCountViaStash($loggedUsrId);
    }
  }

  /**
   * watchCount implementation using Redis blocking subscribe.
   */
  private function watchCountViaRedis($redis, $userId): void
  {
    Helpers::SSE()
      ->open()
      ->tickInterval(50)
      ->tick(function ($sse) use ($redis, $userId): bool {
        $received = $redis->waitForSignal('notifBus_' . $userId, 5);
        if ($received) {
          $sse->emit('changed');
        }
        return true;
      });
  }

  /**
   * watchCount fallback using stash file polling.
   */
  private function watchCountViaStash($userId): void
  {
    $stash = $this->getService('multitenancy/stash');
    $key   = 'notif_version_' . $userId;
    $seen  = (int) $stash->get($key, 0);

    Helpers::SSE()
      ->open()
      ->tick(function ($sse) use ($stash, $key, &$seen): bool {
        $current = (int) $stash->get($key, 0);
        if ($current > $seen) {
          $seen = $current;
          $sse->emit('changed');
        }
        return true;
      });
  }

  /**
   * Signals the SSE watcher for a specific user that their notification
   * state has changed. Increments the Redis version key that
   * watchCountViaRedis() polls via waitForSignal(), or falls back to
   * stash increment when Redis is unavailable.
   */
  public function signalUser(int $userId): void
  {
    // Attempt Redis signal first (increments the version key polled by waitForSignal)
    try {
      $redis = $this->getService('infrastructure/redis');
      if ($redis->isAvailable()) {
        $redis->signal('notifBus_' . $userId);
        return;
      }
    } catch (\Throwable $e) {
      // Service not found or Redis unavailable — fall through to stash
    }

    // Fallback: stash file-based signalling
    $key = 'notif_version_' . $userId;
    $this->getService('multitenancy/stash')->increment($key);
  }

  public function countUnread()
  {
    $loggedUsrId = $this->getService('iam/session')->getLoggedUser()?->id_iam_user;

    return $this->getDao('MSG_NOTIFICATION')
      ->filter('id_iam_user_recipient')->equalsTo($loggedUsrId)
      ->first('messaging/notifications/count_unread')->numRecords;
  }

  public function listHeadlines($params = [])
  {
    return $this->getDao('MSG_NOTIFICATION')
      ->bindParams($params)
      ->fetch(
        function (&$row) {
          $row = (object) [
            'ds_headline' => $row->ds_headline,
          ];
        },
        "SELECT DISTINCT 
            id_iam_user_recipient,
            ds_headline 
          FROM `MSG_NOTIFICATION`"
      );
  }

  public function get($params = [])
  {
    return $this->getDao('MSG_NOTIFICATION')
      ->bindParams($params)
      ->first('messaging/notifications/read');
  }

  public function create($data, $sendPush = true)
  {
    // Removes forbidden fields from $data:
    $data = $this->getService('utils/misc')->dataBlacklist($data, [
      'id_msg_notification',
      'ds_key',
      'id_iam_user',
      'dt_created',
      'do_read'
    ]);

    // Set default values:
    $data['ds_key'] = 'unt-' . uniqid();
    $data['do_sendpush'] = $sendPush ? 'Y' : 'N';
    try {
      $data['id_iam_user_created'] = $this->getService('iam/session')->getLoggedUser()?->id_iam_user;
    } catch (\Throwable $e) {
      $data['id_iam_user_created'] = null; // contexto sem sessão (ex: SSE background tick)
    }

    $record = $this->getDao('MSG_NOTIFICATION')->insert($data);

    if (!empty($record->id_iam_user_recipient)) {
      // Add to Push Queue
      if (MSG_PUSH_ENABLED && $sendPush)
        $this->sendPushNotification($record);

      $this->signalUser($record->id_iam_user_recipient);
    }

    return $record;
  }

  public function remove($params)
  {
    $deleted = $this->getDao('MSG_NOTIFICATION')
      ->bindParams($params)
      ->delete();

    if ($deleted) {
      $loggedUsrId = $this->getService('iam/session')->getLoggedUser()?->id_iam_user;
      if ($loggedUsrId) $this->signalUser($loggedUsrId);
    }

    return $deleted;
  }

  public function markAsRead($params)
  {
    $updated = $this->getDao('MSG_NOTIFICATION')
      ->bindParams($params)
      ->update(['do_read' => 'Y']);

    if ($updated) {
      $loggedUsrId = $this->getService('iam/session')->getLoggedUser()?->id_iam_user;
      if ($loggedUsrId) $this->signalUser($loggedUsrId);
    }

    return $updated;
  }

  public function addToTeam($teamTag, $data)
  {
    return $this->getDao('IAM_USERTEAM')
      ->filter('teamTag')->equalsTo($teamTag)
      ->fetch(
        function ($row) use ($data) {
          $data['id_iam_user_recipient'] = $row->userId;
          $this->create($data);
        },
        "SELECT 
          utu.id_iam_user as userId
        FROM `IAM_USERTEAM` team 
        JOIN `IAM_USERTEAM_USER` utu ON (utu.id_iam_userteam = team.id_iam_userteam)
        WHERE team.ds_tag = ?teamTag?"
      );
  }

  private function sendPushNotification(object $notification)
  {
    try {
      $usrFilter = ['id_iam_user' => $notification->id_iam_user_recipient];
      $devices = $this->getService('iam/device')->getDevicesOfUser($usrFilter);

      $msg = [
        'title' => $notification->ds_headline,
        'body' => $notification->ds_brief,
        'link' => $notification->ds_link ?? null,
      ];

      foreach ($devices as $device) {
        try {
          $deviceFilter = ['id_iam_device' => $device->id_iam_device];
          $this->getService('messaging/push')->sendToDevice($deviceFilter, $msg);
        } catch (Exception $e) {
          // Ignora falhas individuais em dispositivos para garantir que a iteração continue
        }
      }
    } catch (Exception $e) {
      return;
    }
  }
}
