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

    echo($url."<br/>");

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
    
    $sts = $r["status"];

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





function getMangaSearchProviders() {
    $sources = file_get_contents("sources.json");

    return(json_decode($sources, true)["mangas"]);
}

function getNovelSearchProviders() {
    $sources = file_get_contents("sources.json");

    return(json_decode($sources, true)["novels"]);
}

function extractOps($r, $ops) {
    foreach($ops as $op) {
        $opn = $op[0];

        if ($opn=="split") {
            $r = explode($op[1], $r);
        } else if ($opn=="subarray") {
            if (count($op)>2) {
                $r = array_slice($r, $op[1], $op[2]);
            } else {
                $r = array_slice($r, $op[1]);
            }
        } else if ($opn=="substr") {
            if (count($op)>2) {
                $r = substr($r, $op[1], $op[2]);
            } else {
                $r = substr($r, $op[1]);
            }
        } else if ($opn=="cutbtw") {
            if (count($op)>2) {
                //echo(htmlspecialchars($op[2])."<br/>");
                $r = explode($op[2], explode($op[1], $r)[1])[0];
            } else {
                $r = explode($op[1], $r)[0];
            }
        }
    }

    return($r);
}

function searchMangas($q, $pvdn) {
    $sources = getMangaSearchProviders();
    $pvd = $sources[$pvdn];
    
    $hd = [
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36 OPR/117.0.0.0",
        "Referer: ".$pvd["baseUrl"]
    ];

    
    $pl = $pvd["search"]["payload"];
    $pl[$pvd["search"]["query_key"]] = $q;

    if ($pvd["cloudflare"]) {
        [$r, $rh] = pfgc($pvd["baseUrl"].$pvd["search"]["endpoint"], $pvd["search"]["type"], $hd, $pl);
    } else {
        [$r, $rh] = fgc($pvd["baseUrl"].$pvd["search"]["endpoint"], $pvd["search"]["type"], $hd, $pl);
    }

    $ex = $pvd["search"]["extract"];

    $r = extractOps($r, $ex["ops"]);

    $rl=[];
    foreach($r as $re) {
        $title = extractOps($re, $ex["name"]);
        $url = extractOps($re, $ex["link"]);
        if (substr($url, 0, 4)!="http") {
            $url = $pvd["baseUrl"].(substr($url, 0, 1)=="/" ? "" : "/").$url;
        }
        $thumb = extractOps($re, $ex["thumb"]);

        $rl[] = [$title, $url, $thumb];
    }
    return($rl);
}

function searchNovels($q, $pvdn) {
    $sources = getNovelSearchProviders();
    $pvd = $sources[$pvdn];
    
    $pl = $pvd["search"]["payload"];
    $pl[$pvd["search"]["query_key"]] = $q;

    $hd = [
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36 OPR/117.0.0.0",
        "Referer: ".$pvd["baseUrl"],
    ];

    [$r, $rh] = pfgc($pvd["baseUrl"].$pvd["search"]["endpoint"], $pvd["search"]["type"], $hd, $pl);

    if ($r===false) {
        return(false);
    }

    echo(listformat($rh)."<br/><br/>".$r);
}




@$a = $_GET["a"];

if ($a=="search" && $rtype=="GET") {
    [$q] = mdtpi(["q"]);

    $sources = getMangaSearchProviders();
    $rl=[];
    foreach($sources as $pvdn=>$v) {
        if (!$v["enabled"]) {
            continue;
        }
        
        $rl = array_merge($rl, searchMangas($q, $pvdn));
    }

    ok($rl);
}




if (!$dsp || true) {
    err("unknown_endpoint", "The '".$a."' endpoint is unknown", 501, array(
        "request_type"=> $rtype 
    ));
}

?>