<?php

$redis = new Redis();

function initredis() {
    global $redis;

    if (!isset($redis)) {
        $redis = new Redis();
    }

    if (!$redis->isConnected()) {
        $redis->connect('127.0.0.1', 6379);
    }
}

function redisMsg($a, $pl) {
    global $redis;
    initredis();

    $redis->rpush('readapp_jobs', json_encode([
        'action' => $a,
        'payload' => $pl,
        'timestamp' => time(),
    ]));
}

function sqlinit() {
    global $bdd;

    if ($bdd!=null) {
        /*try {
            $stmt = $bdd->query("DELETE FROM tokens WHERE expiry_date<NOW()");
            return;
        } catch (PDOException $e) {
        }*/
    }
    $bdd = new PDO("mysql:host=localhost;dbname=reader", "reader", "\$DMd**nI4vvrhAk0h%CZ7lJr%tVy3qCc!vN67$8&");

    /*try {
        $stmt = $bdd->query("DELETE FROM tokens WHERE expiry_date<NOW()");
        return;
    } catch (PDOException $e) {
        throw new Exception("Database Error: " . $e->getMessage(), 400);
    }*/
}

function req($q, $vars=array(), $ff=PDO::FETCH_ASSOC) { // PDO::FETCH_ASSOC, PDO::FETCH_NUM
    global $bdd;
    global $dbg;
    sqlinit();

    $ar=-1;
    $lid=-1;
    $r=[];
    try {
        $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $bdd->beginTransaction();

        $stmt = $bdd->prepare($q);
    
        foreach($vars as $k=>$v) {
            $stmt->bindValue(':'.$k, $v);
            if ($dbg) {
                echo(':'.$k." = ".$v."<br/>");
            }
        }
    
        $stmt->execute();
        $lid = $bdd->lastInsertId();
                
        if (str_contains($q, "SELECT")) {
            $r = $stmt->fetchAll($ff);
        }

        $ar = $stmt->rowCount();

        $bdd->commit();
    } catch (PDOException $e) {
        if ($bdd->inTransaction()) {
            $bdd->rollBack();
        }
        err("database_error", "DB Error: ".$e->getMessage(), 400);
        //throw new Exception("Database Error: " . $e->getMessage(), 400);
    } finally {
        $stmt = null;
        $bdd = null;
    }

    return([$r, $ar, $lid]);
}








function fgc($url, $method="GET", $hd=[], $p=[]) {
    global $dbg;

    $ch = curl_init();

    $headers=[];

    if ($method=="GET") {
        $s="";
        foreach($p as $k=>$v) {
            if ($s=="") {
                $s.="?";
            } else {
                $s.="&";
            }
            $s.=$k."=".urlencode($v);
        }
        $url.=$s;
    }

    if ($dbg) {
        echo($method." => ".$url."<br/>");
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $hd);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, $method=="POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $p);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    curl_setopt($ch, CURLOPT_HEADERFUNCTION,
      function($curl, $header) use (&$headers)
      {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) // ignore invalid headers
          return $len;

        $headers[strtolower(trim($header[0]))][] = trim($header[1]);

        return $len;
      }
    );

    $resp = curl_exec($ch);
    curl_close($ch);

    return([$resp, $headers]);
}

function pfgc($url, $method="GET", $hd=[], $p=[]) {
    global $dbg;

    $phd = [
        "Content-Type: application/json"
    ];

    if ($method=="GET") {
        $s="";
        foreach($p as $k=>$v) {
            if ($s=="") {
                $s.="?";
            } else {
                $s.="&";
            }
            $s.=$k."=".$v;
        }
        $url.=$s;
    }

    if ($dbg) {
        echo($method." => ".$url."<br/>");
    }

    $pp = [
        "cmd"=> ($method=="POST" ? "request.post" : "request.get"),
        "url"=> $url,
        "maxTimeout"=> 60000,
        "follow_redirects" => true,
        "headers"=> $hd
    ];
    if ($method=="POST") {
        $pp["postData"] = $p;
    }

    [$r, $rh] = fgc("http://localhost:8191/v1", "POST", $phd, json_encode($pp));

    $r = json_decode($r, true);
    
    //$sts = $r["status"];

    if (array_key_exists("solution", $r)) {
        $r = $r["solution"]; // response, headers, cookies, status
        return([$r["response"], $r["headers"]]);
    } else {
        if ($dbg) {
            $msg = $r["message"];
            echo("There is no solution in the response from flaresolverr: ".implode(",", array_keys($r))."<br/>".$msg."<br/>");
        }
        return([false, false]);
    }
}




function mfgc($requests) {
    $multiHandle = curl_multi_init();
    $curlHandles = [];
    $responses = [];

    foreach ($requests as $key => $req) {
        $ch = curl_init();

        $url = $req['url'];
        $method = $req['method'] ?? 'GET';
        $headers = $req['headers'] ?? [];
        $params = $req['params'] ?? [];

        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $curlHandles[$key] = $ch;
        curl_multi_add_handle($multiHandle, $ch);
    }

    do {
        $status = curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);

    foreach ($curlHandles as $key => $ch) {
        $responses[$key] = curl_multi_getcontent($ch);
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }

    curl_multi_close($multiHandle);

    return $responses;
}


?>