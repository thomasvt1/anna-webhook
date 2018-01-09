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
            $patient = $update['queryResult']['parameters']['patient'];

            $rows = $_DATABASE->query("SELECT `IdCaretaker` FROM `caretaker` WHERE `userId` LIKE ? LIMIT 1",
                array($caretaker["userId"]));

            $caretaker = $rows[0]["IdCaretaker"];

            $_DATABASE->query("INSERT INTO note(IdCaretaker, IdPatient, data, timestamp) VALUES(?, ?, ?, CURRENT_TIMESTAMP)",
                array($caretaker, 1, json_encode($note)));

            sendMessage(array(
                "fulfillmentMessages" => array([
                    "platform" => "ACTIONS_ON_GOOGLE",
                    "simpleResponses" => array("simpleResponses" => [array(
                        "textToSpeech" => "Ok, your note. " . $note . ". for, " . $patient . " has been saved.",
                        "displayText" => "Ok, your note: '" . $note . "' for " . $patient . " has been saved."
                    )])]
                )));
            break;

        case "next.patient":
            // Choosing a new patient number name
            $column = null;
            $value = null;
            if(isset($update['queryResult']['parameters']['number'])) {
                $column = "IdPatient";
                $value = $update['queryResult']['parameters']['number'];
            }
            if(isset($update['queryResult']['parameters']['patient'])) {
                $column = "Firstname";
                $value = $update['queryResult']['parameters']['number'];
            }
            if(isset($update['queryResult']['parameters']['last-name'])) {
                $column = "Surname";
                $value = $update['queryResult']['parameters']['number'];
            }

            sendMessage(array(
                "fulfillmentMessages" => array([
                    "platform" => "ACTIONS_ON_GOOGLE",
                    "simpleResponses" => array("simpleResponses" => [array(
                        "textToSpeech" => $value,
                        "displayText" => $value
                    )])]
                )));

            // Check patient
            $patient = $_DATABASE->row("SELECT * FROM `patient` WHERE ? = ? LIMIT 1",
                array($column, $value));

            if(isset($patient['IdPatient'])) {
                sendMessage(array(
                    "fulfillmentMessages" => array([
                        "platform" => "ACTIONS_ON_GOOGLE",
                        "simpleResponses" => array("simpleResponses" => [array(
                            "textToSpeech" => "Okay, helping " . $patient['Firstname'] . " " . $patient['Surname']. " #" . $patient['IdPatient'],
                            "displayText" => "Okay, helping " . $patient['Firstname'] . " " . $patient['Surname'] . " #" . $patient['IdPatient']
                        )])]
                    )));
            } else {
                sendMessage(array(
                    "followupEventInput" => array(
                        "name" => "WRONGPATIENT",
                        "languageCode" => "en-US"
                    )));
            }
            break;

        case "ask.notes":
            $count = 1;
            if (!empty($update['queryResult']['parameters']['number'])) {
                $count = $update['queryResult']['parameters']['number'];
            }

            $rows = $_DATABASE->query("SELECT * FROM note WHERE IdPatient = ? ORDER BY timestamp DESC LIMIT ?",
                array(1, $count));

            if ($count > 1) {
                $speech = "";
                foreach (range(0, --$count) as $i) {
                    $speech = $speech . " Note " . ++$i . ". " . $rows[--$i]["data"] . ".";
                }
                sendMessage(array(
                    "source" => $update["queryResult"]["source"],
                    "speech" => $speech,
                    "displayText" => $speech,
                    "contextOut" => array()
                ));
            } else {
                $note = "Sorry, no notes were found";
                if (!empty($rows[0])) {
                    $note = $rows[0]["data"];
                }
                sendMessage(array(
                    "source" => $update["queryResult"]["source"],
                    "speech" => $note,
                    "displayText" => $note,
                    "contextOut" => array()
                ));
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
