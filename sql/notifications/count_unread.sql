SELECT COUNT(id_msg_notification) as numRecords
  FROM `MSG_NOTIFICATION`
  WHERE id_iam_user_recipient = ?id_iam_user_recipient?
  AND do_read = 'N'
