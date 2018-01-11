<?php include('db.php');
  $db = new DB(DB_USER, DB_NAME, DB_PASS);

  header('Content-Type: application/json');

  if (!isset($_GET['app_key'])) {
    header("HTTP/1.0 403 Not Authorized");
    echo json_encode(array(
      "error" => "403",
      "message" => "Pas de clé !"
    ));

    exit;
  }

  $query = $db->request(
    "SELECT * FROM evenements WHERE id = ?",
    array($_GET['app_key'])
  );

  if ($query->rowCount() == 0) {
    header("HTTP/1.0 400 Bad Request");
    echo json_encode(array(
      "error" => "400",
      "message" => "Clé incorrecte !"
    ));

    exit;
  }

  $event = $query->fetch();

  $split = explode("?", $_SERVER["REQUEST_URI"]);
  $split = explode("/", $split[0]);

  if (end($split) == "validate") {
    $db->request(
      "UPDATE reservations SET validated = ? WHERE id = ?",
      array((isset($_GET['validate']) && $_GET['validate'] == 0 ? 0 : 1), $split[count($split) - 2])
    );

    echo json_encode(array(
      "status" => "200",
      "message" => "Place ".((isset($_GET['validate']) && $_GET['validate'] == 0 ? 'dé' : ''))."validée"
    ));

    exit;
  }

  $query = $db->request(
    "SELECT * FROM reservations WHERE username = ? AND event_id = ?",
    array(end($split), $event['id'])
  );

  if ($query->rowCount() == 0) {
    if ($event['idShotgun'] == NULL) {
      header("HTTP/1.0 404 Not Found");
      echo json_encode(array(
        "error" => "404",
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
          "error" => "404",
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

  if ($data["validated"]) {
    header("HTTP/1.0 410 Gone");
    echo json_encode(array(
      "error" => "410",
      "message" => "Place plus valide"
    ));

    exit;
  }

  echo json_encode(array(
    "id" => $data["id"],
    "username" => $data["firstname"].' '.$data['lastname'],
    "type" => $data["type"],
    "creation_date" => $event["creation_date"] == NULL ? 1 : $event["creation_date"],
    "expires_at" => $event["expires_at"] == NULL ? 99999999999 : $event["expires_at"],
    "reservation_id" => $data["reservation_id"],
    "seance" => $event["seance"]
  ));

/*
  $file = file_get_contents("data.csv");
  $reservations = explode(PHP_EOL, $file);

  foreach ($reservations as $reservation) {
    $data = explode(',', $reservation);

    $db->request(
      "INSERT INTO reservations(reservation_id, username, firstname, lastname, email, type) VALUES(?, ?, ?, ?, ?, ?)",
      array($data[0], $data[1], $data[2], $data[3], $data[4], $data[5])
    );
  }
*/
