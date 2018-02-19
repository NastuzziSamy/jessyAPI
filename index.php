<?php include('db.php');
  $db = new DB(DB_USER, DB_NAME, DB_PASS);

  header('Content-Type: application/json');

  if (!isset($_GET['app_key'])) {
    header("HTTP/1.0 403 Not Authorized");
    echo json_encode(array(
      "id" => "",
      "username" => "",
      "message" => "Pas de clé !"
    ));
    exit;
  }

  if ($_GET['app_key'] != APP_KEY) {
    header("HTTP/1.0 400 Bad Request");
    echo json_encode(array(
      "id" => "",
      "username" => "",
      "message" => "Clé incorrecte !"
    ));
    exit;
  }

  $split = explode("?", $_SERVER["REQUEST_URI"]);
  $split = explode("/", $split[0]);

  if (end($split) == "validate") {
    $db->request(
      "UPDATE reservations SET validated = ? WHERE id = ?",
      array((isset($_POST['validate']) && $_POST['validate'] == 0 ? 0 : 1), $split[count($split) - 2])
    );

    echo json_encode(array(
      "id" => $split[count($split) - 2],
      "username" => "",
      "message" => "Place ".((isset($_POST['validate']) && $_POST['validate'] == 0 ? 'dé' : ''))."validée"
    ));

    exit;
  }

  if ($split[count($split) - 2] == 'user') {
    $query = $db->request(
      "SELECT * FROM users WHERE login = ?",
      array(end($split))
    );
  }
  else {
    $query = $db->request(
      "SELECT * FROM tickets WHERE shortTag = ?",
      array(end($split))
    );
  }

  if ($query->rowCount() == 0) {
    if ($event['idShotgun'] == NULL) {
      header("HTTP/1.0 404 Not Found");
      echo json_encode(array(
        "id" => "",
        "username" => "",
        "message" => "Pas trouvé"
      ));

      exit;
    }
    else {
      $query = $db->request(
        "SELECT * FROM shotgun.prod2_choice, shotgun.prod2_option
        WHERE shotgun.prod2_choice.fk_desc_id = ? AND shotgun.prod2_choice.choice_id = shotgun.prod2_option.fk_choice_id AND shotgun.prod2_option.user_login = ? AND shotgun.prod2_option.option_status = ?",
        array($event['idShotgun'], end($split), 'V')
      );

      if ($query->rowCount() == 0) {
        header("HTTP/1.0 404 Not Found");
        echo json_encode(array(
          "id" => "",
          "username" => "",
          "message" => "Pas trouvé"
        ));

        exit;
      }

      $data = $query->fetch();

      $db->request(
        "INSERT INTO reservations VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, 0)",
        array($data['user_login'], $data['user_nom'], $data['user_prenom'], $data['user_mail'], $data['choice_name'], $data['option_id'], $event['id'])
      );

      $query = $db->request(
        "SELECT * FROM reservations WHERE username = ? AND event_id = ?",
        array(end($split), $event['id'])
      );
    }
  }

  $data = $query->fetch();

  if ($data["isValidated"])
    header("HTTP/1.0 410 Gone");

  echo json_encode(array(
    "id" => $data['id'],
    "username" => $data['username'],
    "creationDate" => $event["creation_date"] == NULL ? 1 : $event["creation_date"],
    "expirationDate" => $event["expires_at"] == NULL ? 99999999999 : $event["expires_at"],
    "data" => array(
      "Informations générales" => array(
        "Nom" => $data['lastname'],
        "Prénom" => $data['firstname'],
        "Email" => $data['email'],
      ),
      "Informations complémentaires" => array(
        "Evènement" => $event["seance"],
        "Type de place" => $data["type"],
        "Numéro de réserv." => $data["reservation_id"],
      ),
    ),
    "positiveCommand" => array(
      'name' => $data["validated"] ? 'Dévalider' : 'Valider',
      'command' => 'validate',
      'arguments' => array(
        'validate' => $data["validated"] ? '0' : '1'
      )
    )
  ));
