SELECT
    msg.id_msg_chat,
    msg.id_msg_chat_message,
    msg.ds_key,
    msg.tx_content,
    msg.ds_sender,
    msg.dt_created,
    msg.tx_readers,
    msg.ds_channel,
    DATE_FORMAT(msg.dt_created, '%d/%m/%Y %H:%i') AS dtCreated,
    DATE_FORMAT(msg.dt_created, '%H:%i') AS msgTime
  FROM MSG_CHAT_MESSAGE msg