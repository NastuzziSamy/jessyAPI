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

  if (end($split) == 'pay' || end($split) == 'ignore') {
    $query = $db->request(
      "SELECT * FROM ecocups WHERE id = ? AND status = 'lent' ORDER BY date DESC",
      array($split[count($split) - 2])
    );

    if ($query->rowCount() == 0) {
      $db->request(
        "INSERT INTO ecocups VALUES(NULL, ?, 'lent', NOW())",
        array($split[count($split) - 2])
      );

      $query = $db->request(
        "SELECT * FROM ecocups WHERE login = ? AND status = 'lent' ORDER BY date DESC",
        array($split[count($split) - 2])
      );

      $data = $query->fetch();

      echo json_encode(array(
        "id" => $data['id'],
        "username" => $data['login'],
        "message" => "Ecocup prêtée et payée"
      ));
    }
    else {
      $data = $query->fetch();

      $query = $db->request(
        "UPDATE ecocups SET status = ? WHERE id = ?",
        array(end($split) == 'pay' ? 'refund' : 'sold', $data['id'])
      );

      echo json_encode(array(
        "id" => $data['id'],
        "username" => $data['username'],
        "message" => end($split) == 'pay' ? "Ecocup rendue et remboursée" : "Ecocup non rendue et non remboursée mais ignorée"
      ));
    }

    exit;
  }

  if ($split[count($split) - 2] == 'user') {
    $query = $db->request(
      "SELECT * FROM ecocups WHERE login = ? AND status = 'lent' ORDER BY date DESC",
      array(end($split))
    );
  }
  else if (end($split) == 'refund') {
    $query = $db->request(
      "SELECT * FROM ecocups WHERE id = ? AND status = 'lent' ORDER BY date DESC",
      array($split[count($split) - 2])
    );
  }
  else {
    $query = $db->request(
      "SELECT * FROM ecocups WHERE id = ? AND status = 'lent' ORDER BY date DESC",
      array(end($split))
    );
  }

  if ($query->rowCount() != 0 || end($split) == 'refund') {
    $data = $query->fetch();

    if ($data['date'] == date('Y-m-d') || end($split) == 'refund') {
      echo json_encode(array(
        "id" => $data["id"], // Id permettant de retrouver le ticket (doit être identique que celui sur le QR Code)
        "username" => $data["login"], // Login de la personne si elle en possède un: recoupement avec Ginger (doit être identique que celui sur le QR Code)
        "articles" => array(
          array(1671, -1)
        ),
        "foundationId" => 2,
        "message" => "Une ecocup a été prêtée dans la journée, elle est récupérée et en échange, 1€ sera remboursé",
        "positiveCommand" => array(
          'name' => 'Récupérer et rembourser',
          'arguments' => array(
            'refund' => '1'
          )
        )
      ));
    }
    else {
      echo json_encode(array(
        "id" => $data["id"], // Id permettant de retrouver le ticket (doit être identique que celui sur le QR Code)
        "username" => $data["login"], // Login de la personne si elle en possède un: recoupement avec Ginger (doit être identique que celui sur le QR Code)
        "message" => "Une écocup a déjà été prêtée mais non rendue dans la journée (prêtée le ".date('d/m/Y', strtotime($data['date'])).")",
        "positiveCommand" => array(
          'name' => 'Récupérer',
          'command' => 'refund'
        ),
        "neutralCommand" => array(
          'name' => 'Ignorer',
          'command' => 'ignore'
        )
      ));
    }
  }
  else {
    echo json_encode(array(
      "id" => end($split),
      "username" => end($split), // Login de la personne si elle en possède un: recoupement avec Ginger (doit être identique que celui sur le QR Code)
      "message" => "Il est possible de prêter une écocup contre 1€ et doit être rendu dans la journée pour être remboursé",
      "articles" => array(
        array(1671, 1)
      ),
      "foundationId" => 2,
      "positiveCommand" => array(
        'name' => 'Payer et préter'
      )
    ));
    exit;
  }
