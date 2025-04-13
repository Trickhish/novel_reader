<?php

$dbg = !empty($_GET["dbg"]);
$tet = microtime(true);
$rtype = $_SERVER['REQUEST_METHOD'];
$dsp=false;

if (empty($_GET["dbg"])) {
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    foreach(array_keys($_GET) as $k) {
        echo("\$_GET[".$k."] = ".$_GET[$k]."<br/>\n");
    }
    echo("<br/>\n");
}

function listformat($l) {
    $s = json_encode($l, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    $s = str_replace(PHP_EOL, "<br/>", $s);
    $s = str_replace(" ", "&nbsp;", $s);
    return($s);
}

function fgc($url, $method="GET", $hd=[], $p=[]) {
    $ch = curl_init();

    $headers=[];

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

    echo($url."<br/><br/>");

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

    $r = $r["solution"]; // response, headers, cookies, status
    return([$r["response"], $r["headers"]]);
}

function gzd( $data ){
    $g=tempnam('/tmp','ff');
    @file_put_contents( $g, $data );
    ob_start();
    readgzfile($g);
    $d=ob_get_clean();
    unlink($g);
    return $d;
}

function cutbtw($s, $ss, $es) {
    $s = substr($s, strpos($s, $ss)+strlen($ss));
    $s = substr($s, 0, strpos($s, $es));

    return($s);
}

function cutbtwm($s, $ssl, $esl) {
    foreach($ssl as $ss) {
        $s = substr($s, strpos($s, $ss)+strlen($ss));
    }
    foreach($esl as $es) {
        $s = substr($s, 0, strpos($s, $es));
    }
    return($s);
}

function mdtvi($pl=[]) {
    foreach ($pl as $p) {
        if (!isset($pl)) {
            err("A mandatory parameter wasn't specified");
        }
    }
}

function mdtpi($pl=[]) {
    $l = [];
    $vl = [];

    foreach ($pl as $p) {
        if (@!isset($_GET[$p])) {
            if (@!isset($_POST[$p])) {
                $l[] = $p;
            } else {
                $vl[] = $_POST[$p];
            }
        } else {
            $vl[] = $_GET[$p];
        }
    }

    if (count($l) != 0) {
        err("missing_parameters", "Mandatory parameters [".implode(", ", $l)."] weren't specified", 400, array("missing_parameters"=> $l));
    }

    return($vl);
}

function ok($ct=null, $et=array()) {
    global $dbg;
    global $tet;

    $et["total"] = intval((microtime(true)-$tet)*1000);

    if ($dbg) {
        echo(listformat(array(
            "success"=> true,
            "content"=> $ct,
            "execution_time"=> $et
        )));
    } else {
        echo(json_encode(array(
            "success"=> true,
            "content"=> $ct,
            "execution_time"=> $et
        ), JSON_PRETTY_PRINT));
    }
    exit();
}

function err($ec="", $em="", $sc=400, $ct=null, $et=array()) {
    global $dbg;
    global $tet;

    $et["total"] = intval((microtime(true)-$tet)*1000);

    http_response_code($sc);

    if ($dbg) {
        echo(listformat(array(
            "success"=> false,
            "error_code"=> $ec,
            "error_message"=> $em,
            "content"=> $ct,
            "execution_time"=> $et
        )));
    } else {
        echo(json_encode(array(
            "success"=> false,
            "error_code"=> $ec,
            "error_message"=> $em,
            "content"=> $ct,
            "execution_time"=> $et
        ), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }

    exit();
}

function sqlinit() {
    global $bdd;

    if ($bdd!=null) {
        try {
            $stmt = $bdd->query("DELETE FROM tokens WHERE expiry_date<NOW()");
            return;
        } catch (PDOException $e) {
        }
    }
    $bdd = new PDO("mysql:host=localhost;dbname=reader", "reader", "\$DMd**nI4vvrhAk0h%CZ7lJr%tVy3qCc!vN67$8&");

    try {
        $stmt = $bdd->query("DELETE FROM tokens WHERE expiry_date<NOW()");
        return;
    } catch (PDOException $e) {
        throw new Exception("Database Error: " . $e->getMessage(), 400);
    }
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

function disp() {
    global $dsp;

    $dsp=true;
}





function getSearchProviders() {
    $sources = file_get_contents("sources.json");

    return(json_decode($sources, true));
}

function search($q, $pvdn) {
    $sources = getSearchProviders();
    $pvd = $sources[$pvdn];
    
    $hd = [
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36 OPR/117.0.0.0",
        "Referer: ".$pvd["baseUrl"],
        "accept: application/json, text/javascript, */*; q=0.01",
        "Accept-Encoding: gzip, deflate, br, zstd",
        "content-type: application/x-www-form-urlencoded; charset=UTF-8",
        "x-requested-with: XMLHttpRequest",
        "Cookie: _ga=GA1.1.1557541590.1744476239; __cflb=02DiuHeaTueuYie8hq3xjy9ZBKxoR3jpw262hZStSMX1V; XSRF-TOKEN=eyJpdiI6IkQ2ZXZHU1hodVVkVXR2bVBCaEVZT0E9PSIsInZhbHVlIjoibm5USTFvaWpXYWVpM3VEVGxYOU9qSmR0d05sdjM4Z1pmUDVYd1FNUy9QU2V0UHBJZHJycU1zRmxnOGpKb3crKzhpMk0yQ1ZWeWhFZjNhekZ4b29KVTZrdVhTZVNyWWh3UmNPdkpob1NTS2dkVmtLRVVqckFJRmprMTZFSzd6MHoiLCJtYWMiOiIyZmRmMWQwODA5MmJjMzU3OTM0ZGZiNmMwNzljODM4YjJlMjYxMmY1YWZjOTNmOTI5NmE5MTAxMGIxYzNkZDljIn0%3D; mangakakalot_session=eyJpdiI6Ilk5OWZLTVJmQWNXdTI5dzB1TVBpMUE9PSIsInZhbHVlIjoiTnZFSktJM0k1UEw2YlVGMERVZUVTQjdnMHpyQ1UwcE9uV2loRGwwSittV2F6aXlzd3BpVXlKQ2dPdU8zcUF1N1FGWVBnckgvdFZyU1pxL000M3FEdjZXQmxPcmtxbjVLTDloMXREckVWdVhXcUZ1Rzkzd0FFQnBmZXFINWtaSnkiLCJtYWMiOiJkMmY3N2RlMGQ4ZTVjZGEyNzgwYmQwNDZiYTg3ZjllOWExOWZlMjBkMzcwZjg4MWNkOTA2NjE5ZTM2ZjNjYjJhIn0%3D; _ga_SFMBZBJXPJ=GS1.1.1744482182.2.1.1744483745.0.0.0; cf_clearance=WC.iJaKuSN0g2XfTg_.hFITVloDtG27jBkLdVJCP3MY-1744484168-1.2.1.1-VZNLMG9S.Hx8DNgoRRW6jT0b0zSjcIPN4rdsRpCUNMnuqO8wRdMEBbUk_4_penTQnh_AwzDGWdFuvFhmHiCJ4uo8v9jC2JsRJg08xhe77SFhN9riWaz.u41FiTFQFwok.ae23O64G6dgzT.zO1iIxQdJD9KF9JD.ke2ldGFq0gJ5jg3gcyxpInhzTAdT4J891Lp.CIUTDFe2wrjHjBWucc0A_e5HWeGVUXb6CyTQZa16LURdQtL8ojZCWcEFDeyzqJF9S5twl5KV5KGw6K8DJk5YS33yDETbz6SEcyojL4dd.x_Pi64ihGjnml7pDuqgN8qPyHl3rknqbl0EvjrI.X.h92_w3p5aJp.WP9g6bfz9yGaN.PFGrMpLKdJ52Wde"
    ];

    $pl = $pvd["search"]["payload"];
    $pl[$pvd["search"]["query_key"]] = $q;

    [$r, $rh] = pfgc($pvd["baseUrl"].$pvd["search"]["endpoint"], $pvd["search"]["type"], $hd, $pl);

    echo($r."<br/>");

    $r = json_decode($r, true);

    echo(listformat($rh)."<br/><br/>".listformat($r));
}





@$a = $_GET["a"];

if ($a=="search" && $rtype=="GET") {
    [$q] = mdtpi(["q"]);

    $sources = getSearchProviders();
    foreach($sources as $pvdn=>$v) {
$       search($q, $pvdn);

        break;
    }

    ok();
}




if (!$dsp || true) {
    err("unknown_endpoint", "The '".$a."' endpoint is unknown", 501, array(
        "request_type"=> $rtype 
    ));
}

?>