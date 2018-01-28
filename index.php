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

  // Recherche par utilisateur non permise
  $query = $db->request(
    "SELECT * FROM tags WHERE shortTag = ?",
    array(end($split))
  );

  if ($query->rowCount() == 0) {
    header("HTTP/1.0 404 Not Found");
    echo json_encode(array(
      "error" => "404",
      "message" => "Non trouvé"
    ));

    exit;
  }

  $tag = $query->fetch();

  $query = $db->request(
    "SELECT * FROM tickets WHERE id = ?",
    array($tag['idTicket'])
  );

  if ($query->rowCount() == 0) {
    header("HTTP/1.0 503 Internal Server Error");
    echo json_encode(array(
      "error" => "503",
      "message" => "Ticket non trouvable !"
    ));

    exit;
  }

  $ticket = $query->fetch();

  $query = $db->request(
    "SELECT * FROM types WHERE id = ?",
    array($ticket['idType'])
  );

  if ($query->rowCount() == 0) {
    header("HTTP/1.0 503 Internal Server Error");
    echo json_encode(array(
      "error" => "503",
      "message" => "Type de ticket non trouvable !"
    ));

    exit;
  }

  $type = $query->fetch();

  $query = $db->request(
    "SELECT * FROM users WHERE id = ?",
    array($ticket['idUser'])
  );

  if ($query->rowCount() == 0) {
    header("HTTP/1.0 503 Internal Server Error");
    echo json_encode(array(
      "error" => "503",
      "message" => "Utilisateur non trouvable !"
    ));

    exit;
  }

  $user = $query->fetch();

  if ($data["isValidated"])
    header("HTTP/1.0 410 Gone");

  $data = array(
    'Informations générales' => array(
      "Nom" => $ticket['lastname'],
      "Prénom" => $ticket['firstname'],
    ),
    'Billet' => array(
      "Type" => $type["name"],
      "Prix" => $type["price"] == 0 ? 'Gratuit' : $type["price"].' €',
    )
  );

  if ($ticket['lastname'] != $user['lastname'] || $ticket['firstname'] != $user['firstname'])
    $data['Informations générales']['Payé par'] = $user['lastname'].' '.$user['firstname'];

  if ($type['nbrInPack'] != 1) {
    $data['Billet']['Groupe'] = 'Billet vendu par '.$type['nbrInPack'];
  }

  if ($type['maxAge'] < 100)
    $data['Billet']['Limite d\'âge'] = $type['maxAge'].' an'.($type['maxAge'] > 1 ? 's' : '');

  if (!$type['sellToStudentsOnly'] && !$type['sellToContributers']) {
    $age = floor((time() - strtotime($ticket['birthdate'])) / (60 * 60 * 24 * 365.25));

    $data['Informations complémentaires'] = array(
      'Date de naissance' => date('d/m/Y', strtotime($ticket['birthdate'])),
      'Age' => $age.' an'.($age > 1 ? 's' : '')
    );
  }

  echo json_encode(array(
    "id" => $tag["shortTag"], // Id permettant de retrouver le ticket (doit être identique que celui sur le QR Code)
    "username" => $user["login"] == NULL ? '' : $user["login"], // Login de la personne si elle en possède un: recoupement avec Ginger (doit être identique que celui sur le QR Code)
    "creationDate" => $tag['creationDate'],
    "expirationDate" => $tag['modificationDate'] == NULL ? -1 : $tag['modificationDate'],
    "data" => $data // Oblige l'appli à afficher ces informations dans l'ordre avec la même écriture
  ));
