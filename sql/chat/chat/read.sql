WITH Chats AS (
  SELECT `MSG_CHAT`.* FROM `MSG_CHAT`
  JOIN `MSG_CHAT_PARTICIPANT`
  ON `MSG_CHAT_PARTICIPANT`.`id_msg_chat` = `MSG_CHAT`.`id_msg_chat`
  AND `MSG_CHAT_PARTICIPANT`.`ds_key` = ?reader?
)

<MainQuery>
SELECT
    conv.id_msg_chat,
    conv.ds_key,
    conv.dt_created,
    conv.dt_updated,
    conv.dt_initiated,
    conv.dt_ended,
    conv.id_iam_user_created,
    conv.id_iam_user_updated,
    DATE_FORMAT(conv.dt_created, '%d/%m/%Y %H:%i:%s') AS dtCreated,
    DATE_FORMAT(conv.dt_initiated, '%d/%m/%Y %H:%i:%s') AS dtInitiated,
    DATE_FORMAT(conv.dt_ended, '%d/%m/%Y %H:%i:%s') AS dtEnded,
    (
      SELECT COUNT(msg.id_msg_chat_message)
      FROM `MSG_CHAT_MESSAGE` msg
      WHERE msg.id_msg_chat = conv.id_msg_chat
      AND NOT JSON_OVERLAPS(msg.tx_readers, JSON_ARRAY(?reader?))
    ) AS unreadMsgsCount,
    (
      SELECT msg.tx_content
      FROM `MSG_CHAT_MESSAGE` msg
      WHERE msg.id_msg_chat = conv.id_msg_chat
      ORDER BY msg.id_msg_chat_message DESC
      LIMIT 1
    ) as lastMessageContent,
    (
      SELECT DATE_FORMAT(msg.dt_created, '%H:%i')
      FROM `MSG_CHAT_MESSAGE` msg
      WHERE msg.id_msg_chat = conv.id_msg_chat
      ORDER BY msg.id_msg_chat_message DESC
      LIMIT 1
    ) as lastMessageTime,
    prt.ds_name AS participantName
  FROM Chats conv
  LEFT JOIN MSG_CHAT_PARTICIPANT prt
  ON prt.id_msg_chat = conv.id_msg_chat
  </MainQuery>