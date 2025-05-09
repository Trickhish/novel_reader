<?php

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
    $dt = [];
    $r = extractOps($r, $xt["ops"]??null, $dt);

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
            $dt = ["url"=> $url];
            $rurl = extractOps("", $rurl, $dt);
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
    $dt = [];
    $r = extractOps($r, $xt["ops"]??null, $dt);
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
        $dt = [];
        $r = extractOps($r, $xt["ops"]??null, $dt);
    }

    $rl=[];
    $dt = [];
    foreach($xt as $k=>$v) {
        if ($k=="ops") {
            continue;
        }

        $rl[$k] = extractOps($r, $v, $dt);
        //echo($k."=".(is_array($rl[$k]) ? json_encode($rl[$k]) : $rl[$k])."<br/><br/>");
    }
    stoptime("images_extraction");
    

    return($rl);
}

?>