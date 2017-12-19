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

    // Switch the action
    switch ($update["result"]["action"]) {
        case "welcome.hello";
            // Need to get the caretaker name
            $userid = $update['originalRequest']['data']['user']['userId'];

            $rows = $_DATABASE->query("SELECT `firstname` FROM `caretaker` WHERE `userId` LIKE ? LIMIT 1",
                array($userid));

            $name = $rows[0]["firstname"];

            sendMessage(array(
                "source" => $update["result"]["source"],
                "speech" => "Hi, " . $name . ", I'm miss Anna. Who are we helping today?",
                "displayText" => "Hi " . $name . ", I'm miss Anna. Who are we helping today?",
                "contextOut" => array()
            ));
            break;
        case "make.note";
            // Need to write note to the database, and send the caretaker a confirmation or fail
            $note = $update['result']['parameters']['note'];
            $patient = $update['result']['parameters']['patient'];

            $userid = $update['originalRequest']['data']['user']['userId'];

            $rows = $_DATABASE->query("SELECT `IdCaretaker` FROM `caretaker` WHERE `userId` LIKE ? LIMIT 1",
                array($userid));

            $caretaker = $rows[0]["IdCaretaker"];

            $_DATABASE->query("INSERT INTO note(IdCaretaker, IdPatient, data, timestamp) VALUES(?, ?, ?, CURRENT_TIMESTAMP)",
                array($caretaker, 1, json_encode($note)));

            sendMessage(array(
                "source" => $update["result"]["source"],
                "speech" => "Ok, your note. " . $note . " for " . $patient . " has been saved.",
                "displayText" => "Ok, your note: '" . $note . "' for " . $patient . " has been saved.",
                "contextOut" => array()
            ));
            break;
        case "ask.notes":
            $count = 1;
            if (!empty($update['result']['parameters']['number'])) {
                $count = $update['result']['parameters']['number'];
            }

            $rows = $_DATABASE->query("SELECT * FROM note WHERE IdPatient = ? ORDER BY timestamp DESC LIMIT ?",
                array(1, $count));

            if ($count > 1) {
                $speech = "";
                foreach (range(0, --$count) as $i) {
                    $speech = $speech . " Note " . ++$i . ". " . $rows[--$i]["data"] . ".";
                }
                sendMessage(array(
                    "source" => $update["result"]["source"],
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
                    "source" => $update["result"]["source"],
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
var_dump($update);
$result = ob_get_clean();
fwrite($myFile, $result);
fclose($myFile);

if (isset($update["result"]["action"])) {
    processMessage($update);
}

// Disconnect from the database
$_DATABASE->disconnect();
