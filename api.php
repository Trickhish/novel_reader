<?php

$dbg = !empty($_GET["dbg"]);
$rtype = $_SERVER['REQUEST_METHOD'];
$dsp=false;
$execution_time = [
    "total"=> microtime(true)*1000
];

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



function starttime($etn) {
    global $execution_time;

    $execution_time[$etn] = microtime(true)*1000;
}
function stoptime($etn) {
    global $execution_time;

    $execution_time[$etn] = intval((microtime(true)*1000)-$execution_time[$etn]);
}

function listformat($l, $lpf="") {
    $s = json_encode($l, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $s = str_replace(" ", "&nbsp;", $s);
    //$s = nl2br($s);
    $s = str_replace(PHP_EOL, "<br/>".$lpf, $s);

    return($s);
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
    global $execution_time;

    //$et["total"] = intval((microtime(true)-$tet)*1000);
    foreach($execution_time as $etn=>$et) {
        //echo((microtime(true)*1000).", ".$et.", ".intval((microtime(true)*1000)-$et)."<br/>");
        if ($et > 100000000) {
            $execution_time[$etn] = intval(((microtime(true)*1000)-$et));
        }
    }

    if ($dbg) {
        echo(listformat(array(
            "success"=> true,
            "content"=> $ct,
            "execution_time"=> $execution_time
        )));
    } else {
        echo(json_encode(array(
            "success"=> true,
            "content"=> $ct,
            "execution_time"=> $execution_time
        ), JSON_UNESCAPED_SLASHES));
    }
    exit();
}

function err($ec="", $em="", $sc=400, $ct=null, $et=array()) {
    global $dbg;
    global $execution_time;

    //$et["total"] = intval((microtime(true)-$tet)*1000);
    foreach($execution_time as $etn=>$et) {
        //echo((microtime(true)*1000).", ".$et.((microtime(true)*1000)-$et)."<br/>");
        if ($et > 100000000) {
            $execution_time[$etn] = intval(((microtime(true)*1000)-$et));
        }
    }

    http_response_code($sc);

    if ($dbg) {
        echo(listformat(array(
            "success"=> false,
            "error_code"=> $ec,
            "error_message"=> $em,
            "content"=> $ct,
            "execution_time"=> $execution_time
        )));
    } else {
        echo(json_encode(array(
            "success"=> false,
            "error_code"=> $ec,
            "error_message"=> $em,
            "content"=> $ct,
            "execution_time"=> $execution_time
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

function extractOps($r, $ops, $lvl=0) {
    global $dbg;

    if ($ops==null) {
        return($r);
    }

    $pre = str_repeat("&emsp;&emsp;&emsp;", $lvl);

    if ($dbg) {
        echo("<br/>");
        if ($r==null) {
            echo($pre);
            echo("Source is empty<br/>");
        } else {
            $rs=$r;
            if (is_array($rs)) {
                $rs=json_encode($r);
            }

            echo($pre);
            if (strlen($rs)<400) {
                echo("Source: ".htmlspecialchars($rs)."<br/>");
            } else {
                echo("Source (".strlen($r)."): ".htmlspecialchars(substr($rs, 0, 400))."...<br/>");
            }
        }
    }

    foreach($ops as $op) {
        $opn = $op[0];

        if ($dbg) {
            echo(listformat($op, $pre)."<br/>\n");
        }

        if ($opn=="split") {
            if (is_array($r)) {
                $r = array_map(function ($e) use ($op) {
                    return(explode($op[1], $e));
                }, $r);
            } else {
                $r = explode($op[1], $r);
            }
        
        } else if ($opn=="subarray") {
            $r = array_slice($r, $op[1], $op[2]??null);
        
        } else if ($opn=="substr") {
            if (is_array($r)) {
                $r = array_map(function ($e) use ($op) {
                    return(substr($e, $op[1], $op[2]??null));
                }, $r);
            } else {
                $r = substr($r, $op[1], $op[2]??null);
            }
        
        } else if ($opn=="cutbtw") {
            if (is_array($r)) {
                $r = array_map(function ($e) use ($op) {
                    if (count($op)>2) {
                        return(explode($op[2], explode($op[1], $e)[1])[0]);
                    } else {
                        return(explode($op[1], $e)[0]);
                    }
                }, $r);
            } else {
                if (count($op)>2) {
                    $r = explode($op[2], explode($op[1], $r)[1])[0];
                } else {
                    $r = explode($op[1], $r)[0];
                }
            }
        
        } else if ($opn=="json_decode") {
            $r = json_decode($r, true);
        
        } else if ($opn=="dictSelect") {
            $r = $r[$op[1]];
        
        } else if ($opn=="concat") {
            if (is_array($r)) {
                $r = array_map(function ($e) use ($ops) {
                    $rs = "";
                    for($i=1; $i<count($ops); $i++) {
                        $op = $ops[$i];

                        if (is_array($op)) {
                            $rs.=extractOps($e, $op, $lvl+1);
                        } else {
                            $rs.=$op;
                        }
                    }

                    return($rs);
                }, $r);
            } else {
                $rs = "";
                for($i=1; $i<count($ops); $i++) {
                    $op = $ops[$i];

                    if (is_array($op)) {
                        $rs.=extractOps($r, $op, $lvl+1);
                    } else {
                        $rs.=$op;
                    }
                }

                $r=$rs;
            }

        } else if ($opn=="decode_slashes") {
            $r = stripcslashes($r);
            $r = str_replace('\\/', '/', $r);
        
        } else if ($opn=="array") {
            $l=[];

            for($i=1; $i<count($ops); $i++) {
                $op = $ops[$i];

                $l[] = extractOps($r, $op, $lvl+1);
            }

            $r=$l;
        } else if ($opn=="dict") {
            if (is_array($r)) {
                $r = array_map(function ($e) use ($op) {
                    return($e);
                }, $r);
            } else {
                
            }
        } else if ($opn=="!=") {
            if (is_array($r)) {
                $r = array_map(function ($e) use ($op) {
                    return($e!=$op[1]);
                }, $r);
            } else {
                $r = ($r!=$op[1]);
            }
        } else if ($opn=="=" || $opn=="==") {
            if (is_array($r)) {
                $r = array_map(function ($e) use ($op) {
                    return($e==$op[1]);
                }, $r);
            } else {
                $r = ($r==$op[1]);
            }
        } else if ($opn=="ago_to_ts") {
            if (is_array($r)) {
                $r = array_map(function ($e) use ($op) {
                    return(strtotime($e));
                }, $r);
            } else {
                $r = strtotime($r);
            }
        } else if ($opn=="trim") {
            if (is_array($r)) {
                $r = array_map(function ($e) use ($op) {
                    return(trim($e));
                }, $r);
            } else {
                $r = trim($r);
            }
        } else if ($opn=="remove_strings") {
            if (is_array($r)) {
                $r = array_map(function ($e) use ($op) {
                    for($i=1; $i<count($op); $i++) {
                        $e=str_replace($op[$i], "", $e);
                    }
                    return($e);
                }, $r);
            } else {
                for($i=1; $i<count($op); $i++) {
                    $r=str_replace($op[$i], "", $r);
                }
            }
        } else if ($opn=="mult") {
            if (is_array($r)) {
                $r = array_map(function ($e) use ($op) {
                    return($e*$op[1]);
                }, $r);
            } else {
                $r = $r*$op[1];
            }
        }

        if ($dbg) {
            echo($pre);
            echo("Result is ".(is_array($r) ? "an Array" : "a string")." of length ".(is_array($r) ? count($r) : strlen($r))."<br/>");
            $rs=$r;
            if (is_array($rs)) {
                $rs=json_encode($rs);
            }
            echo($pre);
            echo(htmlspecialchars(substr($rs, 0, 400)));
            if (strlen($rs)>400) {
                echo("...");
            }
            echo("<br/><br/>");
        }
    }

    if ($dbg) {
        echo("<br/><br/>");
    }

    return($r);
}

function pvdSearchMangas($q, $pvdn) {
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

        if ($ex["thumb"]==null) {
            $thumb=null;
        } else {
            $thumb = extractOps($re, $ex["thumb"]);
        }

        $rl[] = [
            "title"=>$title,
            "url"=>$url,
            "thumb"=>$thumb
        ];
    }
    return($rl);
}

function pvdSearchNovels($q, $pvdn) {
    $sources = getNovelSearchProviders();
    $pvd = $sources[$pvdn];
    
    $pl = $pvd["search"]["payload"];
    $pl[$pvd["search"]["query_key"]] = $q;

    $hd = [
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36 OPR/117.0.0.0",
        "Referer: ".$pvd["baseUrl"],
    ];


    if ($pvd["cloudflare"]) {
        [$r, $rh] = pfgc($pvd["baseUrl"].$pvd["search"]["endpoint"], $pvd["search"]["type"], $hd, $pl);
    } else {
        [$r, $rh] = fgc($pvd["baseUrl"].$pvd["search"]["endpoint"], $pvd["search"]["type"], $hd, $pl);
    }

    if ($r===false) {
        return(false);
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

        if ($ex["thumb"]==null) {
            $thumb=null;
        } else {
            $thumb = extractOps($re, $ex["thumb"]);
        }

        $rl[] = [
            "title"=>$title,
            "url"=>$url,
            "thumb"=>$thumb
        ];
    }

    return($rl);
}

function searchNovels($q, $n=3, $tl=500) {
    $sources = getNovelSearchProviders();
    $rl=[];

    $t = microtime(true)*1000;

    foreach($sources as $pvdn=>$v) {
        if (!$v["enabled"]) {
            continue;
        }
        
        $r = pvdsearchNovels($q, $pvdn);

        if ($r==null) {
            continue;
        }

        $rl = array_merge($rl, $r);

        if (count($rl) > $n || ((microtime(true)*1000)-$t)>$tl) {
            break;
        }
    }

    return($rl);
}

function searchMangas($q, $n=3, $tl=500) {
    $sources = getMangaSearchProviders();
    $rl=[];

    $t = microtime(true)*1000;

    foreach($sources as $pvdn=>$v) {
        if (!$v["enabled"]) {
            continue;
        }
        
        $r = pvdSearchMangas($q, $pvdn);

        if ($r==null) {
            continue;
        }

        $rl = array_merge($rl, $r);

        if (count($rl) > $n || ((microtime(true)*1000)-$t)>$tl) {
            break;
        }
    }

    return($rl);
}

function getProvider($url) {
    $ru = parse_url($url);
    $host = str_replace("www.", "", $ru["host"]);

    $Msources = getMangaSearchProviders();
    $Nsources = getNovelSearchProviders();

    if (array_key_exists($host, $Msources)) {
        $tp="manga";
        $src = $Msources[$host];
    } else if (array_key_exists($host, $Msources)) {
        $tp="novel";
        $src = $Nsources[$host];
    } else {
        return([null, null, null]);
    }

    return([$host, $tp, $src]);
}

function getData($url, $pvd) {
    $hd = [
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36 OPR/117.0.0.0",
        "Referer: ".$pvd["baseUrl"],
    ];

    $pl = $pvd["data"]["payload"];
    //$pl[$pvd["search"]["query_key"]] = $q;

    starttime("data_request");
    if ($pvd["cloudflare"]) {
        [$r, $rh] = pfgc($url, $pvd["data"]["type"], $hd, $pl);
    } else {
        [$r, $rh] = fgc($url, $pvd["data"]["type"], $hd, $pl);
    }
    stoptime("data_request");

    if ($r===false) {
        return(false);
    }

    $xt = $pvd["data"]["extract"];

    starttime("data_extraction");
    $r = extractOps($r, $xt["ops"]??null);

    $rl=[];
    foreach($xt as $k=>$v) {
        if ($k=="ops") {
            continue;
        }

        $rl[$k] = extractOps($r, $v);
        //echo($k."=".(is_array($rl[$k]) ? json_encode($rl[$k]) : $rl[$k])."<br/><br/>");
    }
    stoptime("data_extraction");

    return($rl);
}

function getId($url, $pvd) {
    $hd = [
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36 OPR/117.0.0.0",
        "Referer: ".$pvd["baseUrl"],
    ];

    $pl = $pvd["id"]["payload"];
    //$pl[$pvd["search"]["query_key"]] = $q;

    starttime("data_request");
    if ($pvd["cloudflare"]) {
        [$r, $rh] = pfgc($url, $pvd["data"]["type"], $hd, $pl);
    } else {
        [$r, $rh] = fgc($url, $pvd["data"]["type"], $hd, $pl);
    }
    stoptime("data_request");

    if ($r===false) {
        return(false);
    }

    $xt = $pvd["data"]["extract"];

    starttime("data_extraction");
    $r = extractOps($r, $xt["ops"]??null);

    $rl=[];
    foreach($xt as $k=>$v) {
        if ($k=="ops") {
            continue;
        }

        $rl[$k] = extractOps($r, $v);
        //echo($k."=".(is_array($rl[$k]) ? json_encode($rl[$k]) : $rl[$k])."<br/><br/>");
    }
    stoptime("data_extraction");

    return($rl);
}

function getChapters($url, $pvd) {
    $hd = [
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36 OPR/117.0.0.0",
        "Referer: ".$pvd["baseUrl"],
    ];

    $pl = $pvd["data"]["payload"];
    //$pl[$pvd["search"]["query_key"]] = $q;

    starttime("data_request");
    if ($pvd["cloudflare"]) {
        [$r, $rh] = pfgc($url, $pvd["data"]["type"], $hd, $pl);
    } else {
        [$r, $rh] = fgc($url, $pvd["data"]["type"], $hd, $pl);
    }
    stoptime("data_request");

    if ($r===false) {
        return(false);
    }

    $xt = $pvd["data"]["extract"];

    starttime("data_extraction");
    $r = extractOps($r, $xt["ops"]??null);

    $rl=[];
    foreach($xt as $k=>$v) {
        if ($k=="ops") {
            continue;
        }

        $rl[$k] = extractOps($r, $v);
        //echo($k."=".(is_array($rl[$k]) ? json_encode($rl[$k]) : $rl[$k])."<br/><br/>");
    }
    stoptime("data_extraction");

    return($rl);
}



@$a = $_GET["a"];

if ($a=="search" && $rtype=="GET") {
    [$q] = mdtpi(["q"]);

    starttime("novels_search");
    $novels = searchNovels($q, 1, 500);
    stoptime("novels_search");

    starttime("mangas_search");
    $mangas = searchMangas($q, 1, 500);
    stoptime("mangas_search");

    starttime(etn: "sorting");

    $novels = array_map(function($e) {
        $e["type"] = "novel";
        return($e);
    }, $novels);

    $mangas = array_map(function($e) {
        $e["type"] = "manga";
        return($e);
    }, $mangas);

    $rl = array_merge($novels, $mangas);

    usort($rl, function($a, $b) use ($q) {
        similar_text($q, $a["title"], $percentA);
        similar_text($q, $b["title"], $percentB);
        
        return $percentB <=> $percentA;
    });

    stoptime(etn: "sorting");

    ok($rl);
}



else if ($a=="data" && $rtype=="GET") {
    [$url] = mdtpi(["url"]);

    [$pvdn, $type, $pvd] = getProvider($url);

    if ($pvdn==null) {
        err("unknown_provider", "No provider was found for this url");
    }

    $dt = getData($url, $pvd);

    ok($dt);
}



else if ($a=="chapters" && $rtype=="GET") {
    [$url] = mdtpi(["url"]);

    [$pvdn, $type, $pvd] = getProvider($url);

    if ($pvdn==null) {
        err("unknown_provider", "No provider was found for this url");
    }

    $chl = getChapters($url, $pvd);

    ok($chl);
}


if (!$dsp || true) {
    err("unknown_endpoint", "The '".$a."' endpoint is unknown", 501, array(
        "request_type"=> $rtype 
    ));
}

?>