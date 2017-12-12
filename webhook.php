<?php

require_once "config.php";
require_once "Database.php";

// Require the configuration
global $_CONFIG;
// Connect to the database
$_DATABASE = new Database($_CONFIG["host"], $_CONFIG["database"], $_CONFIG["user"], $_CONFIG["password"]);

/**
 * Processes the message requested
 *
 * @param $update
 */
function processMessage($update)
{
  global $_DATABASE;
  // Open debug file, to write the request to
  $myFile = fopen("debug.txt", "w") or die("Unable to open file!");
  ob_start();
  var_dump($update);
  $result = ob_get_clean();
  fwrite($myFile, $result);
  fclose($myFile);

  // Switch the action
  switch ($update["result"]["action"]) {
    case "make.note";
      // Need to write note to the database, and send the caretaker a confirmation or fail
      $note = $update['result']['parameters']['note'];
      $patient = $update['result']['parameters']['patient'];

      $_DATABASE->query("INSERT INTO note(IdCaretaker, IdPatient, data, timestamp) VALUES(?, ?, ?, CURRENT_TIMESTAMP)",
        array(1, 1, json_encode($note)));

      sendMessage(array(
        "source" => $update["result"]["source"],
        "speech" => "Ok, your note for ".$patient." has been saved.",
        "displayText" => "Ok, your note for ".$patient." has been saved.",
        "contextOut" => array()
      ));
      break;
    case "ask.notes":
      $count = $update['result']['parameters']['number'];
      $patient = $update['result']['parameters']['patient'];

      $rows = $_DATABASE->query("SELECT * FROM note WHERE IdPatient = ? LIMIT ?",
        array(1, $count));

      if(!empty($rows[0])) {
        $note = $rows[0]["note"] + 1;
      } else {
        $note = $rows["note"];
      }

      sendMessage(array(
        "source" => $update["result"]["source"],
        "speech" => $note,
        "displayText" => $note,
        "contextOut" => array()
      ));
      break;
  }
}

/**
 * Encodes the parameters given, and echoes them
 *
 * @param $parameters
 */
function sendMessage($parameters)
{
  echo json_encode($parameters);
}

// Things starts here
$update_response = file_get_contents("php://input");
$update = json_decode($update_response, true);
if (isset($update["result"]["action"])) {
  processMessage($update);
}

// Disconnect from the database
$_DATABASE->disconnect();