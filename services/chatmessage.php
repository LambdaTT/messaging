<?php

namespace Messaging\Services;

use Exception;
use SplitPHP\Database\Dao;
use SplitPHP\EventDispatcher;
use SplitPHP\Service;
use SplitPHP\Utils;

class ChatMessage extends Service
{
  private const TABLE = "MSG_CHAT_MESSAGE";

  /**
   * Creates a new chat message.
   *
   * @param array $data     Message fields (id_msg_chat, tx_content, ds_sender)
   * @return object The inserted message record
   */
  public function create($data)
  {
    $data = $this->getService('utils/misc')->dataWhiteList(
      $data,
      ['id_msg_chat', 'tx_content', 'ds_sender', 'ds_channel']
    );

    // If no conversation was provided, auto-create one:
    if (empty($data['id_msg_chat'])) {
      $conversation = $this->getService('messaging/chat')->create();
      $data['id_msg_chat'] = $conversation->id_msg_chat;
    }

    // Add sender as reader:
    $data['ds_key'] = 'msg-' . uniqid();

    $record = $this->getDao(self::TABLE)->insert($data);

    Dao::flush();

    $record->tx_readers = $this->read($record->id_msg_chat_message, $data['ds_sender'], false);

    EventDispatcher::dispatch(
      fn() => null,
      'messaging.chat.message',
      [$record, 'new']
    );

    return $record;
  }

  public function list(array $params = [], ?string $reader = null)
  {
    $list = $this->getDao(self::TABLE)
      ->bindParams($params)
      ->fetch(
        fn(&$record) => $record->tx_readers = json_decode($record->tx_readers) ?? [],
        'messaging/chat/message/read'
      );

    if (!empty($reader))
      foreach ($list as $record)
        $this->read($record->id_msg_chat_message, $reader);

    return $list;
  }

  public function get(array $filters = [], ?string $reader = null)
  {
    return $this->list($filters, $reader)[0] ?? null;
  }

  public function remove($params = [])
  {
    return $this->getDao(self::TABLE)
      ->bindParams($params)
      ->delete();
  }

  private function read(int $msgId, string $reader, bool $signal = true): array
  {
    $record = $this->getDao(self::TABLE)->filter('id_msg_chat_message')->equalsTo($msgId)->first();
    if (empty($record)) throw new Exception("[ChatMessage] No message was found to be read.");

    $curReaders = json_decode($record->tx_readers) ?? [];
    $updReaders = array_unique(array_merge($curReaders, [Utils::stringToSlug($reader)]));

    if (count($curReaders) == count($updReaders)) return $updReaders;

    $this->getDao(self::TABLE)
      ->filter('id_msg_chat_message')->equalsTo($record->id_msg_chat_message)
      ->update([
        'tx_readers' => json_encode($updReaders)
      ]);

    $this->getService('messaging/chat')->addParticipant($record->id_msg_chat, $reader);

    if ($signal) {
      EventDispatcher::dispatch(
        fn() => null,
        'messaging.chat.message',
        [$record, 'read']
      );
    }

    return $updReaders;
  }

  /**
   * Signals the SSE Server with the full message data.
   * Publishes a 'chatMessages' event via Redis PUBLISH so that
   * connected clients can render the message in real time.
   */
  public function signalChat(object $record, string $action): void
  {
    try {
      $redis = $this->getService('infrastructure/redis');
      if ($redis->isAvailable()) {
        $redis->publish('chatMessages', [...(array)$record, 'action' => $action]);
      }
    } catch (\Throwable $e) {
      throw new ('[signalChat] ' . $e->getMessage());
    }
  }

  public function countUnread(string $participant)
  {
    return $this->getDao(self::TABLE)
      ->filter('reader')->equalsTo($participant)
      ->first(
        "SELECT COUNT(msg.id_msg_chat_message) AS unreadMsgs
          FROM `MSG_CHAT_MESSAGE` msg
          JOIN `MSG_CHAT_PARTICIPANT` prt 
            ON prt.id_msg_chat = msg.id_msg_chat
            AND prt.ds_key = ?reader? 
          WHERE NOT JSON_OVERLAPS(msg.tx_readers, JSON_ARRAY(?reader?))"
      )?->unreadMsgs ?? 0;
  }
}
