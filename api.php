<?php

require(__DIR__."/scripts/extract.php");
require(__DIR__."/scripts/mangas.php");
require(__DIR__."/scripts/novels.php");
require(__DIR__."/scripts/read.php");
require(__DIR__."/scripts/networking.php");
require(__DIR__."/scripts/utils.php");

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

if ($dbg && isset($_GET["dm"])) {
    echo("<style>body {background-color: #9ca8b9;}</style>");
}

$env = [];
loadEnv();







@$a = $_GET["a"];

if ($a=="search" && $rtype=="GET") {
    [$q] = mdtpi(["q"]);

    starttime("mangas_search");
    //$mangas = mdSearch($q);
    $mangas = searchMangas($q, 1, 500);

    $mangas = array_map(function($e) {
        $e["type"] = "manga";
        return($e);
    }, $mangas);
    stoptime("mangas_search");



    starttime("novels_search");
    $novels = searchNovels($q, 1, 500);

    $novels = array_map(function($e) {
        $e["type"] = "novel";
        return($e);
    }, $novels);
    stoptime("novels_search");

    $rl = array_merge($novels, $mangas);


    starttime(etn: "sorting");
    usort($rl, function($a, $b) use ($q) {
        similar_text($q, $a["title"], $percentA);
        similar_text($q, $b["title"], $percentB);
        
        return $percentB <=> $percentA;
    });

    stoptime(etn: "sorting");

    ok($rl);
}



else if ($a=="search_mangas" && $rtype=="GET") {
    [$q] = mdtpi(["q"]);

    starttime("mangas_search");
    //$mangas = mdSearch($q);

    $mangas = searchMangas($q, 1, 500);

    $mangas = array_map(function($e) {
        $e["type"] = "manga";
        return($e);
    }, $mangas);
    stoptime("mangas_search");

    ok($mangas);
}



else if ($a=="search_novels" && $rtype=="GET") {
    [$q] = mdtpi(["q"]);

    starttime("novels_search");
    $novels = searchNovels($q, 1, 500);

    $novels = array_map(function($e) {
        $e["type"] = "novel";
        return($e);
    }, $novels);
    stoptime("novels_search");

    ok($novels);
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

    $rl = mdMangaCovers($id, 1, 256);
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



else if ($a=="bookmark" && $rtype=="GET") {
    [$url] = mdtpi(["url"]);

    [$host, $type, $dd] = getProvider($url);

    if ($type==null) {
        err("unknown_provider", "This URL is not associated with any of the compatible providers");
    }

    if ($type=="manga") {
        [$dbr, $dbm] = req("INSERT INTO webtoons (url)
        VALUES (:url)
        ON DUPLICATE KEY UPDATE
        url=:url", [
            "url"=> $url
        ]);
    } else {
        [$dbr, $dbm] = req("INSERT INTO novels (url)
        VALUES (:url)
        ON DUPLICATE KEY UPDATE
        url=:url", [
            "url"=> $url
        ]);
    }

    redisMsg("fetchData", [
        "url"=> $url
    ]);

    ok();
}





else if ($a=="redistest") {
    redisMsg("test", [
        "message"=> $_GET["msg"]
    ]);

    ok();
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