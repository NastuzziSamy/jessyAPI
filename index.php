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

  $split = explode("?", $_SERVER["REQUEST_URI"]);
  $split = explode("/", $split[0]);

  if (end($split) == "validate") {
    $db->request(
      "UPDATE tickets SET isValidated = ? WHERE shortTag = ?",
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
    header("HTTP/1.0 404 Not Found");
    echo json_encode(array(
      "error" => "404",
      "message" => "Non trouvé"
    ));
    exit;
  }

  $data = $query->fetch();

  if ($data["isValidated"])
    header("HTTP/1.0 410 Gone");

  echo json_encode(array(
    "id" => $data["shortag"], // Id permettant de retrouver le ticket (doit être identique que celui sur le QR Code)
    "username" => $data["login"], // Login de la personne si elle en possède un: recoupement avec Ginger (doit être identique que celui sur le QR Code)
    "data" => array( // Oblige l'appli à afficher ces informations dans l'ordre avec la même écriture
      "Nom" => $data['lastname'],
      "Prénom" => $data['firstname'],
      "Email" => $data['email'],
      "Type" => $data["type"],
      "Prix" => $data["price"] + '€',
    ),
    "creationDate" => $data['creationDate'],
    "expirationDate" => -1 // expire jamais
  ));
