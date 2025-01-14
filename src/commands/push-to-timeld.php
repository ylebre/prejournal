<?php

declare(strict_types=1);
require_once(__DIR__ . '/../platform.php');
require_once(__DIR__ . '/../database.php');
require_once(__DIR__ . '/../api/timeld.php');
/*
In .env set:
TIMELD_HOST=https://timeld.org/api
TIMELD_USERNAME=michielbdejong (username at Timeld instance)
TIMELD_PASSWORD=...
TIMELD_PROJECT=fedb/fedt
TIMELD_TIMESHEET=fedb/from-pounder-source
PREJOURNAL_ADMIN_PARTY=true
# PREJOURNAL_USERNAME=michiel (username at Prejournal instance)
# PREJOURNAL_PASSWORD=...

Then run: php src/cli-single.php push-to-timeld http://time.pondersource.com/michiel
Note that in admin party you can run this for any worker, doesn't have to match
a specific prejournal user.
*/

// $worker e.g. "http://time.pondersource.com/ismoil"
// $arr an array of associative hashes, each with fields:
// $arr[$i]["amount"] e.g. 8
// $arr[$i]["timestamp_"] e.g. "2022-03-25 00:00:00"
// $arr[$i]["id"] e.g. 123
// $arr[$i]["description"] e.g. "Testing diff propagation" 
function pushMovementsToTimesheet($worker, $arr)
{
    if (!isset($_SERVER["TIMELD_PROJECT"])) {
        // echo "TIMELD_PROJECT not set!";
        return;
    }
    $project = $_SERVER["TIMELD_PROJECT"]; // e.g. "fedb/fedt"
    $timesheet = $_SERVER["TIMELD_TIMESHEET"]; // e.g. "fedb/from-pounder-source"
    $hostUrl = $_SERVER["PREJOURNAL_HOST"];
    
    var_dump([
        "Push movement to timeld!",
        $worker,
        $project,
        $timesheet
    ]);
    var_dump($arr);
    $data = array(
        '{"@id":"' . $project . '","@type":"Project"}',
        '{"@id":"' . $timesheet . '","project":[{"@id":"' . $project . '"}],"@type":"Timesheet"}',
    );
    date_default_timezone_set('UTC');
    for ($i = 0; $i < count($arr); $i++) {
        $data[] = json_encode([
            "activity" => $arr[$i]["description"],
            "session" => [
                "@id" =>  $timesheet
            ],
            "duration" => intval($arr[$i]["amount"]) * 60,
            "start" => [
                "@value" => date(DATE_ATOM, strtotime($arr[$i]["timestamp_"])),
                "@type" => "http://www.w3.org/2001/XMLSchema#dateTime"
            ],
            "@type" => "Entry",
            "vf:provider" => [
                "@id" => $worker
            ],
            "external" => [
                "@id" => $hostUrl . "/movement/" . $arr[$i]["id"]
            ]
        ], JSON_UNESCAPED_SLASHES);
    }

    $result = importTimld(implode("\n", $data));

    var_dump($result);

    if (isset($result["code"])) {
        if ($result["code"] === "Forbidden") {
            return ["You have forbidden access you need right username"];
        //exit;
        } elseif ($result["code"] === "BadRequest") {
            return ["Malformed domain entity"];
        }
    }

    if ($result  === null) {
        return ["The API timeld was import succesfully"];
    }

}

function pushToTimeld($context, $command) {
     if($context["adminParty"]) {
        $conn = getDbConn();
        $worker = $command[1];
        $params = [
            "worker" => getComponentId($worker)
        ];
        var_dump($params);
        $query = "SELECT m.id, m.timestamp_, m.amount, c2.name as project, s.description " .
        "FROM movements m INNER JOIN components c2 ON m.tocomponent=c2.id " .
        "INNER JOIN statements s ON s.movementid = m.id " .
        "WHERE m.type_='worked' and m.fromcomponent = :worker";
        var_dump($query);
        $res = $conn->executeQuery($query, $params);
        $arr = $res->fetchAllAssociative();
        var_dump($arr);
        return pushMovementsToTimesheet($worker, $arr);
     } else {
        return ["This command only works in admin party mode"];
     }
}