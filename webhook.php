<?php
/**
 * Processes the message requested
 *
 * @param $update
 */
function processMessage($update)
{
  // Open debug file, to write the request to
  $myFile = fopen("debug.txt", "w") or die("Unable to open file!");
  ob_start();
  var_dump($update);
  $result = ob_get_clean();

  fwrite($myFile, $result);
  fclose($myFile);
  if ($update["result"]["action"] == "sayHello") {
    $name = $update['result']['parameters']['given-name'];
    sendMessage(array(
      "source" => $update["result"]["source"],
      "speech" => "Hello " . $name,
      "displayText" => "Hello " . $name,
      "contextOut" => array()
    ));
  }

  if($update["result"]["action"] == "make.note") {
    // Need to write note to the database, and send the caretaker a confirmation or fail
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