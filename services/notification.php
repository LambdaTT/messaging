<?php

namespace Messaging\Services;

use SplitPHP\Service;

class Notification extends Service
{
  private $data;

  public function list($params = [])
  {
    return $this->getDao('MSG_NOTIFICATION')
      ->bindParams($params)
      ->find(
        "SELECT
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
          LEFT JOIN FMN_FILE fle ON (fle.id_fmn_file = cre.id_fmn_file_avatar)"
      );
  }

  public function listHeadlines($params)
  {

    $query = "SELECT DISTINCT ds_headline FROM MSG_NOTIFICATION";

    return $this->getDao('MSG_NOTIFICATION')
      ->bindParams($params)
      ->find($query);
  }

  public function get($params = [])
  {
    return $this->getDao('MSG_NOTIFICATION')
      ->bindParams($params)
      ->first(
        "SELECT
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
          LEFT JOIN FMN_FILE fle ON (fle.id_fmn_file = cre.id_fmn_file_avatar)"
      );
  }

  public function create($data)
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
    $loggedUser = $this->getService('iam/session')->getLoggedUser();
    if (!empty($loggedUser)) $data['id_iam_user_created'] = $loggedUser->id_iam_user;

    $returnObj = $this->getDao('MSG_NOTIFICATION')->insert($data);

    // Add to Push Queue
    $this->getService('messaging/push')->addToQueue($returnObj);

    return $returnObj;
  }

  public function remove($params)
  {
    return $this->getDao('MSG_NOTIFICATION')
      ->bindParams($params)
      ->delete();
  }

  public function markAsRead($params)
  {
    return $this->getDao('MSG_NOTIFICATION')
      ->bindParams($params)
      ->update(['do_read' => 'Y']);
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
}
