<?php
function processMessage($update) {
$myfile = fopen("debug.txt", "w") or die("Unable to open file!");
ob_start();
var_dump($update);
$result = ob_get_clean();

    fwrite($myfile, $result);
    fclose($myfile);
    if($update["result"]["action"] == "sayHello"){
	$name = $update['result']['parameters']['given-name'];
        sendMessage(array(
            "source" => $update["result"]["source"],
            "speech" => "Hello " . $name,
            "displayText" => "Hello " . $name,
            "contextOut" => array()
        ));
    }
}

function sendMessage($parameters) {
    echo json_encode($parameters);
}

$update_response = file_get_contents("php://input");
$update = json_decode($update_response, true);
if (isset($update["result"]["action"])) {
    processMessage($update);
}
?>
