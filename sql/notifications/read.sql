SELECT
    ntf.ds_key,
    ntf.dt_created,
    DATE_FORMAT(ntf.dt_created, '%d/%m/%Y %H:%i:%s') AS dtCreated,
    ntf.id_msg_notification,
    (CASE 
      WHEN ntf.id_iam_user_created IS NULL THEN 'System' 
      ELSE CONCAT(cre.ds_first_name,' ', cre.ds_last_name) 
    END) as author_name, 
    (CASE 
      WHEN ntf.id_iam_user_created IS NULL THEN 'system' 
      ELSE fle.ds_url 
    END) as author_avatar, 
    ntf.ds_headline, 
    ntf.ds_brief, 
    ntf.tx_content, 
    ntf.do_important, 
    ntf.id_iam_user_recipient,
    ntf.do_read
  FROM MSG_NOTIFICATION ntf 
  LEFT JOIN IAM_USER cre ON (cre.id_iam_user = ntf.id_iam_user_created)
  LEFT JOIN FMN_FILE fle ON (fle.id_fmn_file = cre.id_fmn_file_avatar)