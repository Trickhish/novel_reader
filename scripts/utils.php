<?php


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






function is_assoc(array $arr): bool {
    return array_keys($arr) !== range(0, count($arr) - 1);
}

function join_url(string $base, string $path): string {
    return rtrim($base, '/') . '/' . ltrim($path, '/');
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

function disp() {
    global $dsp;

    $dsp=true;
}


?>