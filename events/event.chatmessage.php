<?php

namespace Messaging\Events;

use SplitPHP\Event;

/**
 * Class ChatMessage
 *
 * This class represents an event that occurs when an action regarding chat messages occurs.
 * It extends the base Event class and provides additional functionality for handling chat message events.
 *
 * @package Messaging\Events
 */
class ChatMessage extends Event
{
  /**
   * The name of the event.
   * This constant is used to identify the event type.
   */
  public const EVENT_NAME = 'messaging.chat.message';

  /**
   * @var object $target
   * The target message record.
   */
  private object $target;

  /**
   * @var string $action
   * The action that was performed on the target.
   */
  private string $action;

  /**
   * ChatMessage constructor.
   *
   * Initializes the ChatMessage event with the provided affected message record and the action performed onto it.
   *
   * @param object $target The message object that was affected by the action.
   * @param string $action The action that was performed on the target.
   */
  public function __construct(object $target, string $action)
  {
    $this->target = $target;
    $this->action = $action;
  }

  /**
   * Returns a string representation of the ChatMessage event.
   * This method provides a human-readable description of the event, including the message record.
   *
   * @return string A string representation of the ChatMessage event.
   */
  public function __toString(): string
  {
    return 'Event: ' . self::EVENT_NAME . ' (Action: "' . $this->action . '", Message: ' . json_encode($this->target) . ')';
  }

  /**
   * Returns the message instance associated with the ChatMessage event.
   *
   * @return object The message object.
   */
  public function info(): object
  {
    return (object) [
      "target" => $this->target,
      "action" => $this->action
    ];
  }
}
