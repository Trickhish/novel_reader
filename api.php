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

function disp() {
    global $dsp;

    $dsp=true;
}





@$a = $_GET["a"];

if ($a=="search" && $rtype=="GET") {
    [$q] = mdtpi(["q"]);

    $hd = [
        
    ];

    [$r, $rh] = fgc("https://www.lightnovelworld.com/lnsearchlive", "POST", $hd, array(
        "inputContent"=> $q,
    ));

    echo(listformat($rh)."<br/><br/>".listformat($r));

    ok();
}




if (!$dsp || true) {
    err("unknown_endpoint", "The '".$a."' endpoint is unknown", 501, array(
        "request_type"=> $rtype 
    ));
}

?>