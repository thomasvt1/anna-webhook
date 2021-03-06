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

    $caretaker = $_DATABASE->row("SELECT * FROM `caretaker` WHERE `userId` LIKE ? LIMIT 1", array($update['originalDetectIntentRequest']['payload']['user']['userId']));

    if ($caretaker["firstname"] == null) {

        $_DATABASE->query("INSERT INTO unknown_caretaker(userId, timestamp) VALUES(?, CURRENT_TIMESTAMP)",
            array($update['originalDetectIntentRequest']['payload']['user']['userId']));

        sendMessage(array(
            "fulfillmentMessages" => array([
                "platform" => "ACTIONS_ON_GOOGLE",
                "simpleResponses" => array("simpleResponses" => [array(
                    "textToSpeech" => "Sorry, your device hasn't been registered yet. Please contact your administrator.",
                    "displayText" => "Sorry, your device hasn't been registered yet. Please contact your administrator."
                )])],
            )));
        exit;
    }

    // Switch the action
    switch ($update["queryResult"]["action"]) {
        case "welcome.hello";
            //Say personalized hello and check if user in DB
            sendMessage(array(
                "fulfillmentMessages" => array([
                    "platform" => "ACTIONS_ON_GOOGLE",
                    "simpleResponses" => array("simpleResponses" => [array(
                        "textToSpeech" => "Hi, " . $caretaker["firstname"] . ", I'm miss Anna. Who are we helping today?",
                        "displayText" => "Hi " . $caretaker["firstname"] . ", I'm miss Anna. Who are we helping today?"
                    )])],
                )));
            break;

        case "test.hook";
            sendMessage(array(
                "followupEventInput" => array(
                    "name" => "WRONGPATIENT",
                    "languageCode" => "en-US"
                ),
                "fulfillmentMessages" => array([
                    "platform" => "ACTIONS_ON_GOOGLE",
                    "simpleResponses" => array("simpleResponses" => [array(
                        "textToSpeech" => "Api request has been solved",
                        "displayText" => "Api request has been solved"
                    )])]
                )));
            break;

        case "make.note";
            // Need to write note to the database, and send the caretaker a confirmation or fail
            $note = $update['queryResult']['parameters']['note'];
            $patient = $_DATABASE->row("SELECT * FROM `patient` WHERE `IdPatient` = ".intval($update['queryResult']['parameters']['patient'])."
             OR `Firstname` LIKE '".$update['queryResult']['parameters']['patient']."'
             OR `Surname` LIKE '".$update['queryResult']['parameters']['patient']."' LIMIT 1");

            $_DATABASE->query("INSERT INTO note(IdCaretaker, IdPatient, data, timestamp) VALUES(?, ?, ?, CURRENT_TIMESTAMP)",
                array($caretaker['IdCaretaker'], $patient['IdPatient'], json_encode($note)));

            sendMessage(array(
                "fulfillmentMessages" => array([
                    "platform" => "ACTIONS_ON_GOOGLE",
                    "simpleResponses" => array("simpleResponses" => [array(
                        "textToSpeech" => "Ok, your note. " . $note . ". for, " . $patient['Firstname'] . " has been saved.",
                        "displayText" => "Ok, your note: '" . $note . "' for " . $patient['Firstname'] . " has been saved."
                    )])]
                )));
            break;

        case "next.patient":
            // Choosing a new patient number name
            $patient = $_DATABASE->row("SELECT * FROM `patient` WHERE `IdPatient` = ".intval($update['queryResult']['parameters']['patient'])."
             OR `Firstname` LIKE '".$update['queryResult']['parameters']['patient']."'
             OR `Surname` LIKE '".$update['queryResult']['parameters']['patient']."' LIMIT 1");

            if(isset($patient['IdPatient'])) {
                sendMessage(array(
                    "fulfillmentMessages" => array([
                        "platform" => "ACTIONS_ON_GOOGLE",
                        "simpleResponses" => array("simpleResponses" => [array(
                            "textToSpeech" => "Okay, helping " . $patient['Firstname'] . " " . $patient['Surname']. " #" . $patient['IdPatient'] . ". What can I do for you?",
                            "displayText" => "Okay, helping " . $patient['Firstname'] . " " . $patient['Surname'] . " #" . $patient['IdPatient'] . ". What can I do for you?"
                        )])]
                    )));
                exit;
            } else {
                sendMessage(array(
                    "followupEventInput" => array(
                        "name" => "WRONGPATIENT",
                        "languageCode" => "en-US"
                    )));
                exit;
            }
            break;

        case "ask.notes":
            $count = 1;
            $patient = $_DATABASE->row("SELECT * FROM `patient` WHERE `IdPatient` = ".intval($update['queryResult']['parameters']['patient'])."
             OR `Firstname` LIKE '".$update['queryResult']['parameters']['patient']."'
             OR `Surname` LIKE '".$update['queryResult']['parameters']['patient']."' LIMIT 1");
            if (!empty($update['queryResult']['parameters']['number'])) {
                $count = intval($update['queryResult']['parameters']['number']);
            } else {
                if (strpos($update['queryResult']['queryText'], 'notes') !== false) {
                    $count = 3;
                }
            }

            $rows = $_DATABASE->query("SELECT * FROM note WHERE IdPatient = ? ORDER BY timestamp DESC LIMIT ?",
                array($patient['IdPatient'], $count));

            if ($count > 1) {
                $speech = "";
                foreach (range(0, --$count) as $i) {
                    $speech = $speech . " Note " . ++$i . ". " . $rows[--$i]["data"] . ".";
                }
                sendMessage(array(
                    "fulfillmentMessages" => array([
                        "platform" => "ACTIONS_ON_GOOGLE",
                        "simpleResponses" => array("simpleResponses" => [array(
                            "textToSpeech" => $speech,
                            "displayText" => $speech
                        )])]
                    )));
                exit;
            } else {
                $note = "Sorry, no notes were found";
                if (!empty($rows[0])) {
                    $note = $rows[0]["data"];
                }
                sendMessage(array(
                    "fulfillmentMessages" => array([
                        "platform" => "ACTIONS_ON_GOOGLE",
                        "simpleResponses" => array("simpleResponses" => [array(
                            "textToSpeech" => $note,
                            "displayText" => $note
                        )])]
                    )));
            }
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

// Open debug file, to write the request to
$myFile = fopen("debug.txt", "w") or die("Unable to open file!");
ob_start();
var_dump($update_response);
$result = ob_get_clean();
fwrite($myFile, $result);
fclose($myFile);

if (isset($update["queryResult"]["action"])) {
    processMessage($update);
}

// Disconnect from the database
$_DATABASE->disconnect();
