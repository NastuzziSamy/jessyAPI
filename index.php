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

  if ($_GET['app_key'] != APP_KEY) {
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
      "UPDATE participantspayants SET is_validated = ? WHERE shortag = ?",
      array((isset($_GET['validate']) && $_GET['validate'] == 0 ? 0 : 1), $split[count($split) - 2])
    );

    echo json_encode(array(
      "status" => "200",
      "message" => "Place ".((isset($_GET['validate']) && $_GET['validate'] == 0 ? 'dé' : ''))."validée"
    ));

    exit;
  }

  if ($split[count($split) - 2] == 'user') {
    $query = $db->request(
      "SELECT * FROM participantspayants WHERE login = ?",
      array(end($split))
    );
  }
  else {
    $query = $db->request(
      "SELECT * FROM participantspayants WHERE shortag = ?",
      array(end($split))
    );
  }

  if ($query->rowCount() == 0) {
    header("HTTP/1.0 404 Not Found");
    echo json_encode(array(
      "error" => "404",
      "message" => "Pas trouvé"
    ));
    exit;
  }

  $data = $query->fetch();

  if ($data["is_validated"])
    header("HTTP/1.0 410 Gone");

  $types = array("I'M FASTER", "I'M COTISANT", "I'M AN EXTERIOR", "FUCK LES FINAUX");

  echo json_encode(array(
    "id" => $data["shortag"],
    "username" => $data["login"],
    "type" => $types[$data["type"] - 1],
    "fun_id" => FUN_ID,
    "creation_date" => 1 ,
    "expires_at" => 99999999999
  ));
