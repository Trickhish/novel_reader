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


$env = [];

function loadEnv() {
    global $env;

    if (file_exists(__DIR__ . "/.env")) {
        $lines = file(__DIR__ . "/.env");
        foreach ($lines as $line) {
            if (strpos($line, "#") === 0) {
                continue;
            }
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            list($key, $value) = explode("=", $line, 2);
            $env[$key] = trim($value);
        }
    }
}

function writeEnv($sarr) {
    global $env;

    loadEnv();

    foreach ($sarr as $key => $value) {
        $env[$key] = $value;
    }

    $lines = [];
    foreach ($env as $key => $value) {
        $lines[] = "$key=$value";
    }
    file_put_contents(__DIR__ . "/.env", implode("\n", $lines));
}

loadEnv();

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


function join_url(string $base, string $path): string {
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function replaceVars($ct, $dict) {
    return(preg_replace_callback('/\$\[([^\]\r\n]{1,15})\]/', function ($matches) use ($dict) {
        $key = $matches[1];
        return $dict[$key] ?? $matches[0];
    }, $ct));
}

function extractOps($r, $ops, $dt=[], $lvl=0) {
    global $dbg;

    //if ($ops==[]) {
    //    return(null);
    //}
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
            echo("OP [".$opn."] -> ".listformat($op, $pre)."<br/>\n");
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
        
        } else if ($opn=="substring") {

        } else if ($opn=="json_decode") {
            $r = json_decode($r, true);
        
        } else if ($opn=="dictSelect") {
            $r = $r[$op[1]];
        
        } else if ($opn=="concat") {
            //echo((is_array($r) ? "IS ARRAY" : "IS NOT ARRAY")."<br/>");
            if (is_array($r)) {
                $r = array_map(function ($e) use ($op, $dt, $lvl) {
                    $rs = "";
                    for($i=1; $i<count($op); $i++) {
                        $lop = $op[$i];

                        if (is_array($lop)) {
                            $rs.=extractOps($e, $lop, $dt??[], ($lvl??0)+1);
                        } else {
                            $rs.=$lop;
                        }
                    }

                    return($rs);
                }, $r);
            } else {
                //echo("OPS: ".count($op)." : ".listformat($op)."<br/>");
                $rs = "";
                for($i=1; $i<count($op); $i++) {
                    $lop = $op[$i];

                    if (is_array($lop)) {
                        //echo("Concat: [".$lop[0]."]<br/>");
                        $rs.=extractOps($r, $lop, $dt, $lvl+1);
                    } else {
                        //echo("Concat: ".$lop."<br/>");
                        $rs.=$lop;
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

                $l[] = extractOps($r, $op, [], $lvl+1);
            }

            $r=$l;
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
        } else if ($opn=="date_to_ts") {
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
        } else if ($opn=="dbset") {
            [, $options, $table, $wheres, $sets] = $op;

            $where="";
            $vars=[];
            if (count($wheres) > 0) {
                $where=" WHERE ";
                foreach($wheres as $k=>$v) {
                    $k = replaceVars($k, $dt+["CURRENT"=>$r]);
                    $v = replaceVars($v, $dt+["CURRENT"=>$r]);

                    $vars[$k] = $v;

                    if ($where != " WHERE ") {
                        $where.=" AND ";
                    }
                    $where.=$k."=:".$k;
                }
            }

            [$dbr, $dbm] = req("SELECT * FROM $table$where LIMIT 1", $vars);


            $set="";
            $vars=[];
            $vals=[];

            foreach($sets as $k=>$v) {
                $k = replaceVars($k, $dt+["CURRENT"=>$r]);
                $v = replaceVars($v, $dt+["CURRENT"=>$r]);
                $vars[$k] = $v;
                $sets[$k] = $v;

                $vals[] = ":".$k;

                if ($set!="") {
                    $set.=", ";
                }
                $set.=$k."=:".$k;
            }

            $kys = implode(", ", array_keys($sets));
            $vals = implode(", ", $vals);

            if (count($dbr) > 0) { // already in the database
                //echo("UPDATE $table SET $set$where<br/>");

                [$dbr] = req("UPDATE $table SET $set$where", $vars);
            } else {
                //echo("INSERT INTO $table ($kys) VALUES ($vals)");

                [$dbr] = req("INSERT INTO $table ($kys) VALUES ($vals)", $vars);
            }
        } else if ($opn=="dbget") {
            [, $options, $table, $wheres, $gets] = $op;

            $where="";
            $vars=[];
            if (count($wheres) > 0) {
                $where=" WHERE ";
                foreach($wheres as $k=>$v) {
                    $k = replaceVars($k, $dt+["CURRENT"=>$r]);
                    $v = replaceVars($v, $dt+["CURRENT"=>$r]);

                    $vars[$k] = $v;

                    if ($where != " WHERE ") {
                        $where.=" AND ";
                    }
                    $where.=$k."=:".$k;
                }
            }

            if (is_array($gets)) {
                $get = implode(", ", $gets);
            } else {
                $get=$gets;
            }

            [$dbr, $dbm] = req("SELECT $get FROM $table$where LIMIT 1", $vars);

            if (count($dbr) > 0) {
                if (is_array($gets)) {
                    $r = $dbr[0];
                } else {
                    $r = $dbr[0][$gets];
                }
            } else {
                $r = null;
            }

        } else if ($opn=="lcstore") {
            $dt[$op[1]] = $r;
        } else if ($opn=="lcget") {
            if (array_key_exists($op[1], $dt)) {
                $r = $dt[$op[1]];
            } else {
                if ($dbg) {
                    echo("! DataKey '".$op[1]."' was not found: ".implode(",", array_keys($dt))."<br/>");
                }
                $r = null;
            }
        } else if ($opn=="requestop") {
            [, $type, $pvdn, $reqp, $key, $params] = $op;

            if ($type == "manga") {
                $pvd = getMangaSearchProviders()[$pvdn];
            } else {
                $pvd = getNovelSearchProviders()[$pvdn];
            }

            foreach($params as $k=>$v) {
                if (is_array($v)) {
                    $params[$k] = extractOps($r, $v, $dt);
                }
            }

            foreach($reqp as $sbf) {
                $pvd=$pvd[$sbf];
            }

            $hd = [
                "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36 OPR/117.0.0.0",
                //"Referer: ".$pvd["baseUrl"]
            ];


            $pl = $pvd["payload"];
            //$pl[$pvd["query_key"]] = $q;

            if ($pvd["cloudflare"]??false) {
                [$r, $rh] = pfgc($params["url"], $pvd["type"], $hd, $pl);
            } else {
                [$r, $rh] = fgc($params["url"], $pvd["type"], $hd, $pl);
            }

            $ex = $pvd["extract"];

            if (array_key_exists("ops", $ex)) {
                $r = extractOps($r, $ex["ops"], []);
            }

            $r = extractOps($r, $ex[$key], $dt);
        } else if ($opn=="contains") {
            if (is_array($r)) {
                $r = array_map(function ($e) use ($op) {
                    return(str_contains($e, $op[1]));
                }, $r);
            } else {
                $r = str_contains($r, $op[1]);
            }
        } else if ($opn=="dict") {
            if (is_array($r)) {
                $r = array_map(function ($e) use ($op, $dt, $lvl, $r) {
                    
                    $nr = [];
                    foreach($op[1] as $k=>$v) {
                        if (is_array($v)) {
                            $nr[$k] = extractOps($e, $v, $dt, $lvl+1);
                        } else {
                            $nr[$k]=replaceVars($v, $dt+["CURRENT"=>$r]);
                        }
                    }
                    return($nr);

                }, $r);
            } else {
                $nr = [];
                foreach($op[1] as $k=>$v) {
                    if (is_array($v)) {
                        $nr[$k] = extractOps($r, $v, $dt, $lvl+1);
                    } else {
                        $nr[$k] = replaceVars($v, $dt+["CURRENT"=>$r]);
                    }
                }
                $r = $nr;
            }
        } else if ($opn=="appendBase") {
            if (is_array($r)) {
                $r = array_map(function ($e) use ($op) {
                    
                    return(join_url($op[1], $e));

                }, $r);
            } else {
                $r = join_url($op[1], $r);
            }
        } else if ($opn=="fallback") {
            $i=1;
            foreach(array_splice($op, 1) as $lop) {
                $tr = extractOps($r, $lop, $dt, $lvl+1);
                if ($tr!==null) {
                    $r = $tr;

                    if ($dbg) {
                        echo("Fallback option ".$i.": ".$lop[0][0]."<br/>");
                    }

                    break;
                }
                $i++;
            }
        }

        if ($dbg) {
            echo($pre);
            echo("Result is ".(is_array($r) ? "an Array" : "a string")." of length ".(is_array($r) ? count($r) : strlen($r??""))."<br/>");
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

    $r = extractOps($r, $ex["ops"], []);

    $rl=[];
    foreach($r as $re) {
        $title = extractOps($re, $ex["name"], []);
        $url = extractOps($re, $ex["link"], []);
        if (substr($url, 0, 4)!="http") {
            $url = $pvd["baseUrl"].(substr($url, 0, 1)=="/" ? "" : "/").$url;
        }

        if ($ex["thumb"]==null) {
            $thumb=null;
        } else {
            $thumb = extractOps($re, $ex["thumb"], []);
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

    $r = extractOps($r, $ex["ops"], []);

    $rl=[];
    foreach($r as $re) {
        $title = extractOps($re, $ex["name"], []);
        $url = extractOps($re, $ex["link"], []);
        if (substr($url, 0, 4)!="http") {
            $url = $pvd["baseUrl"].(substr($url, 0, 1)=="/" ? "" : "/").$url;
        }

        if ($ex["thumb"]==null) {
            $thumb=null;
        } else {
            $thumb = extractOps($re, $ex["thumb"], []);
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

function is_assoc(array $arr): bool {
    return array_keys($arr) !== range(0, count($arr) - 1);
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
    $exdt=["url"=>$url, "provider"=>$pvd];
    foreach($xt as $k=>$v) {
        if ($k=="ops") {
            continue;
        }

        $rl[$k] = extractOps($r, $v, $exdt);
        $exdt[$k] = $rl[$k];
        //echo($k."=".(is_array($rl[$k]) ? json_encode($rl[$k]) : $rl[$k])."<br/><br/>");
    }
    stoptime("data_extraction");

    return($rl);
}

function getDDD($url, $pvd) {
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

    $pl = $pvd["chapters"]["payload"];
    //$pl[$pvd["search"]["query_key"]] = $q;

    starttime("url_retrieval");
    $rurl = $url;
    if (array_key_exists("url", $pvd["chapters"])) {
        $rurl = $pvd["chapters"]["url"];
        if (is_array($rurl)) {
            $rurl = extractOps("", $rurl, ["url"=>$url]);
        }
    }
    stoptime("url_retrieval");

    starttime("chapters_request");
    if ($pvd["cloudflare"]) {
        [$r, $rh] = pfgc($rurl, $pvd["chapters"]["type"], $hd, $pl);
    } else {
        [$r, $rh] = fgc($rurl, $pvd["chapters"]["type"], $hd, $pl);
    }
    stoptime("chapters_request");

    if ($r===false) {
        return(false);
    }

    $xt = $pvd["chapters"]["extract"];

    starttime("chapters_extraction");
    $r = extractOps($r, $xt["ops"]??null);
    stoptime("chapters_extraction");

    /*$rl=[];
    foreach($xt as $k=>$v) {
        if ($k=="ops") {
            continue;
        }

        $rl[$k] = extractOps($r, $v);
        //echo($k."=".(is_array($rl[$k]) ? json_encode($rl[$k]) : $rl[$k])."<br/><br/>");
    }*/
    

    return($r);
}




function getChapter($url, $pvd) {
    $hd = [
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36 OPR/117.0.0.0",
        "Referer: ".$pvd["baseUrl"],
    ];

    $pl = $pvd["chapter"]["payload"];
    //$pl[$pvd["search"]["query_key"]] = $q;

    starttime("chapter_request");
    if ($pvd["cloudflare"]) {
        [$r, $rh] = pfgc($url, $pvd["chapter"]["type"], $hd, $pl);
    } else {
        [$r, $rh] = fgc($url, $pvd["chapter"]["type"], $hd, $pl);
    }
    stoptime("chapter_request");

    if ($r===false) {
        return(false);
    }

    $xt = $pvd["chapter"]["extract"];

    starttime("images_extraction");
    if (array_key_exists("ops", $xt)) {
        $r = extractOps($r, $xt["ops"]??null);
    }

    $rl=[];
    foreach($xt as $k=>$v) {
        if ($k=="ops") {
            continue;
        }

        $rl[$k] = extractOps($r, $v);
        //echo($k."=".(is_array($rl[$k]) ? json_encode($rl[$k]) : $rl[$k])."<br/><br/>");
    }
    stoptime("images_extraction");
    

    return($rl);
}

function refreshToken($tk) {
    global $env;

    if ($tk==null) {
        $tk=$env["MANGADEX_REFRESH_TOKEN"];
    }

    [$r, $rh] = fgc("https://auth.mangadex.org/realms/mangadex/protocol/openid-connect/token", "POST",
    [
        "Content-Type: application/x-www-form-urlencoded",
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/"
    ], http_build_query(array(
        "grant_type"=>"refresh_token",
        "refresh_token"=> $tk,
        "client_id"=> $env["MANGADEX_CLIENT_ID"],
        "client_secret"=> $env["MANGADEX_CLIENT_SECRET"],
        "scope" => "offline_access"
    )));

    $r = json_decode($r, true);

    if (array_key_exists("access_token", $r)) {
        writeEnv([
            "MANGADEX_TOKEN"=> $r["access_token"],
            "MANGADEX_REFRESH_TOKEN"=> $r["refresh_token"]
        ]);

        return($r["access_token"]);
    } else {
        err("auth_failed", "Failed to get a token");
    }
}

function checkToken($tk=null) {
    global $env;

    if ($tk==null) {
        $tk=$env["MANGADEX_TOKEN"];
    }

    [$r, $rh] = fgc("https://api.mangadex.org/auth/check", "GET", [
        "Authorization: Bearer $tk",
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/"
    ]);

    $r = json_decode($r, true);

    return($r["result"]=="ok");
}

function getToken() {
    global $env;

    if (array_key_exists("MANGADEX_TOKEN", $env)) {
        if (checkToken($env["MANGADEX_TOKEN"])) {
            return($env["MANGADEX_TOKEN"]);
        } else if (array_key_exists("MANGADEX_REFRESH_TOKEN", $env)) {
            $tk = refreshToken($env["MANGADEX_REFRESH_TOKEN"]);

            if ($tk != null) {
                writeEnv([
                    "MANGADEX_TOKEN"=> $tk
                ]);

                return($tk);
            }
        }
    }

    [$r, $rh] = fgc("https://auth.mangadex.org/realms/mangadex/protocol/openid-connect/token", "POST",
    [
        "Content-Type: application/x-www-form-urlencoded",
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/"
    ], http_build_query(array(
        "grant_type"=>"password",
        "username"=> $env["MANGADEX_USERNAME"],
        "password"=> $env["MANGADEX_PASSWORD"],
        "client_id"=> $env["MANGADEX_CLIENT_ID"],
        "client_secret"=> $env["MANGADEX_CLIENT_SECRET"],
        "scope" => "offline_access"
    )));

    $r = json_decode($r, true);

    if (array_key_exists("access_token", $r)) {
        writeEnv([
            "MANGADEX_TOKEN"=> $r["access_token"],
            "MANGADEX_REFRESH_TOKEN"=> $r["refresh_token"]
        ]);

        return($r["access_token"]);
    } else {
        err("auth_failed", "Failed to get a token");
    }
}

function mdSearch($q, $lm=5) {
    global $env;
    $tk = getToken();

    [$r, $rh] = fgc("https://api.mangadex.org/manga", "GET", [
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/"
    ], [
        "title"=> $q,
        "includes[]"=> "manga",
        "limit"=> $lm
    ]);

    $r = json_decode($r, true);

    if ($r["result"] != "ok") {
        err("request_failed", "The request failed");
    }

    return(array_map(function ($e) {
        return([
            "title"=> $e["attributes"]["title"]["en"] ?? array_values($e["attributes"]["title"])[0],
            "type"=> "manga",
            "desc"=> $e["attributes"]["description"]["en"] ?? array_values($e["attributes"]["description"])[0],
            "thumb"=> mdMangaCovers($e["id"], 1)[0]["url"] ?? "",
            "id"=> $e["id"]
        ]);
    }, $r["data"], ));
}

function mdMangaCovers($mid, $lm=1) {
    global $env;
    $tk = getToken();

    [$r, $rh] = fgc("https://api.mangadex.org/cover", "GET", [
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/"
    ], [
        "limit"=> $lm,
        "manga[]"=> $mid,
        "order[createdAt]"=> "asc",
        "order[updatedAt]"=> "asc",
        "includes[]"=> "manga"
    ]);

    $r = json_decode($r, true);

    if ($r["result"] != "ok") {
        err("request_failed", "The request failed");
    }

    return(array_map(function($e) use ($mid) {
        return([
            "id"=> $e["id"],
            "file"=> $e["attributes"]["fileName"],
            "url"=> "https://uploads.mangadex.org/covers/$mid/".$e["attributes"]["fileName"].".256.jpg"
        ]);
    }, $r["data"]));
}



@$a = $_GET["a"];

if ($a=="search" && $rtype=="GET") {
    [$q] = mdtpi(["q"]);

    $rl = mdSearch($q);

    ok($rl);

    /*starttime("novels_search");
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

    ok($rl);*/
}



else if ($a=="covers" && $rtype=="GET") {
    [$id] = mdtpi(["id"]);

    $lm = $_GET["limit"] ?? 1;

    $rl = mdMangaCovers($id, $lm);

    ok($rl);
}



else if ($a=="cover" && $rtype=="GET") {
    [$id] = mdtpi(["id"]);
    global $dbg;

    disp();

    $rl = mdMangaCovers($id, 1);
    if (count($rl)==0) {
        header("Content-Type: image/jpeg");
        header("Cache-Control: public, max-age=86400");
        readfile("/var/www/read/res/image_placeholder.jpg");
    } else {
        $url = $rl[0]["url"];
    }

    $mimeType = get_headers($url, 1)["Content-Type"] ?? "image/jpeg";

    if (!$dbg) {
        header("Content-Type: $mimeType");
        header("Cache-Control: public, max-age=86400");
        header("Image-URL: $url");
    }
    

    [$idt, $rh] = fgc($url, "GET", [
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/"
    ]);

    echo($idt);
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




else if ($a=="chapter" && $rtype=="GET") {
    [$url] = mdtpi(["url"]);

    [$pvdn, $type, $pvd] = getProvider($url);

    if ($pvdn==null) {
        err("unknown_provider", "No provider was found for this url");
    }

    $chl = getChapter($url, $pvd);

    ok($chl);
}




else if ($a=="cfget" && $rtype="GET") {
    [$url] = mdtpi(["url"]);

    [$r, $rh] = pfgc($url, "GET", [
        "Accept: 
text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7",
        "Accept-Encoding: gzip, deflate, br, zstd",
        "Referer: https://mangabuddy.com/",
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36 OPR/118.0.0.0"
    ]);

    echo($r);
}




else if ($a=="authtest" && $rtype=="GET") {
    getToken();
    ok();
}



if (!$dsp) {
    err("unknown_endpoint", "The '".$a."' endpoint is unknown", 501, array(
        "request_type"=> $rtype 
    ));
}

?>