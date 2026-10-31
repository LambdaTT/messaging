<?php

namespace Messaging\Services;

use SplitPHP\EventDispatcher;
use SplitPHP\Service;
use SplitPHP\Utils;

class Chat extends Service
{
  private const TABLE = "MSG_CHAT";

  public function list(string $participant, array $params = [])
  {
    $participant = Utils::stringToSlug($participant);
    $list = [];

    $t = $this->getDao(self::TABLE)
      ->filter('reader')->equalsTo($participant)
      ->bindParams($params)
      ->fetch(
        function ($row) use (&$list) {
          if (!array_key_exists($row->ds_key, $list)) {
            $list[$row->ds_key] = (object) [
              'ds_key' => $row->ds_key,
              'id_msg_chat' => $row->id_msg_chat,
              'dt_created' => $row->dt_created,
              'dt_updated' => $row->dt_updated,
              'dt_initiated' => $row->dt_initiated,
              'dt_ended' => $row->dt_ended,
              'id_iam_user_created' => $row->id_iam_user_created,
              'id_iam_user_updated' => $row->id_iam_user_updated,
              'dtCreated' => $row->dtCreated,
              'dtInitiated' => $row->dtInitiated,
              'dtEnded' => $row->dtEnded,
              'ds_status' => $row->ds_status ?? null,
              'unreadMsgsCount' => $row->unreadMsgsCount,
              'lastMessageContent' => $row->lastMessageContent,
              'lastMessageTime' => $row->lastMessageTime,
              'participants' => []
            ];
          }

          if (!empty($row->participantName))
            $list[$row->ds_key]->participants[] = $row->participantName;
        },
        'messaging/chat/chat/read',
        false
      );

    // echo ($t->sqlstring);
    // exit;

    return array_values($list);
  }

  public function getRaw($filters = [])
  {
    return $this->getDao(self::TABLE)
      ->bindParams($filters)
      ->first();
  }

  public function get(string $participant, array $filters = [])
  {
    return $this->list($participant, $filters)[0] ?? null;
  }

  public function create(array $participants = [])
  {
    $record = $this->getDao(self::TABLE)
      ->insert([
        'ds_key' => 'cht-' . uniqid(),
      ]);
    $record->participants = $participants;

    if (!empty($participants)) {
      $participants = array_map(fn($p) => (object)[
        'ds_key' => Utils::stringToSlug($p),
        'id_msg_chat' => $record->id_msg_chat,
        'ds_name' => $p,
      ], $participants);

      $this->getDao("MSG_CHAT_PARTICIPANT")
        ->insert($participants);
    }

    return $record;
  }

  public function close($filters = [])
  {
    $record = $this->getDao(self::TABLE)
      ->bindParams($filters)
      ->first();

    if (empty($record)) return null;

    $this->getDao(self::TABLE)
      ->filter('id_msg_chat')->equalsTo($record->id_msg_chat)
      ->update([
        'dt_ended' => date('Y-m-d H:i:s'),
        'dt_updated' => date('Y-m-d H:i:s'),
        'id_iam_user_updated' => $this->getService('iam/session')->getLoggedUser()?->id_iam_user,
      ]);

    EventDispatcher::dispatch(
      fn() => null,
      'messaging.chat.message',
      [$record, 'close_chat']
    );

    return $record;
  }

  /**
   * Reopens a closed conversation by clearing its dt_ended timestamp.
   * Returns true if the conversation was actually reopened, false otherwise.
   */
  public function reopen(int $chatId): bool
  {
    $conversation = $this->getDao('MSG_CHAT')
      ->filter('id_msg_chat')->equalsTo($chatId)
      ->first();

    if (empty($conversation) || empty($conversation->dt_ended)) return false;

    $this->getDao('MSG_CHAT')
      ->filter('id_msg_chat')->equalsTo($chatId)
      ->update([
        'dt_ended'   => null,
        'dt_updated' => date('Y-m-d H:i:s'),
      ]);

    return true;
  }

  public function remove($params = [])
  {
    return $this->getDao(self::TABLE)
      ->bindParams($params)
      ->delete();
  }

  public function addParticipant(int $chatId, string $participantName)
  {
    $tbName = "MSG_CHAT_PARTICIPANT";

    // Retrieve Chat Record:
    $chat = $this->getDao(self::TABLE)->filter('id_msg_chat')->equalsTo($chatId)->first();
    if (empty($chat)) return false;

    // Slugify participants:
    $data = [
      'ds_key' => Utils::stringToSlug($participantName),
      'id_msg_chat' => $chatId,
      'ds_name' => $participantName,
    ];

    $existing = $this->getDao($tbName)
      ->filter('id_msg_chat')->equalsTo($chatId)
      ->and('ds_key')->equalsTo($data['ds_key'])
      ->first();

    return $existing ?? $this->getDao($tbName)->insert($data);
  }
}
