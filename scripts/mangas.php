<?php

function getMangaSearchProviders() {
    $sources = file_get_contents("sources.json");

    return(json_decode($sources, true)["mangas"]);
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

    if (substr($pvd["search"]["endpoint"], 0, 4)=="http") {
        $url = $pvd["search"]["endpoint"];
    } else {
        $url = $pvd["baseUrl"].$pvd["search"]["endpoint"];
    }

    if ($pvd["cloudflare"]) {
        [$r, $rh] = pfgc($url, $pvd["search"]["type"], $hd, $pl);
    } else {
        [$r, $rh] = fgc($url, $pvd["search"]["type"], $hd, $pl);
    }

    $ex = $pvd["search"]["extract"];

    $dt = [];
    $r = extractOps($r, $ex["ops"], $dt);

    $rl=[];
    foreach($r as $re) {
        $title=null;
        if (array_key_exists("name", $ex)) {
            $title = extractOps($re, $ex["name"], $dt);
        }

        $link=null;
        if (array_key_exists("link", $ex)) {
            $url = extractOps($re, $ex["link"], $dt);
            if (substr($url, 0, 4)!="http") {
                $url = $pvd["baseUrl"].(substr($url, 0, 1)=="/" ? "" : "/").$url;
            }
        }

        $thumb=null;
        if (array_key_exists("thumb", $ex)) {
            $thumb = extractOps($re, $ex["thumb"], $dt);
        }

        $id=null;
        if (array_key_exists("id", $ex)) {
            $id = extractOps($re, $ex["id"], $dt);
        }

        $rl[] = [
            "title"=>$title,
            "url"=>$url,
            "thumb"=>$thumb,
            "id"=> $id
        ];
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
        
        starttime("manga_search: $pvdn");
        $r = pvdSearchMangas($q, $pvdn);
        stoptime("manga_search: $pvdn");

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
    $dt = [];
    $r = extractOps($r, $xt["ops"]??null, $dt);

    $rl=[];
    foreach($xt as $k=>$v) {
        if ($k=="ops") {
            continue;
        }

        $rl[$k] = extractOps($r, $v, $dt);
        //echo($k."=".(is_array($rl[$k]) ? json_encode($rl[$k]) : $rl[$k])."<br/><br/>");
    }
    stoptime("data_extraction");

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

function mdExtractCover($dt) {
    $mid = $dt["id"];

    $dt = array_filter($dt["relationships"], function($e) {
        return($e["type"]=="cover_art");
    });

    $dt = array_values($dt);

    $cv = $dt[0];

    return([
        "id"=> $cv["id"],
        "filename"=> $cv["attributes"]["fileName"],
        "url"=> "https://uploads.mangadex.org/covers/".$mid."/".$cv["attributes"]["fileName"].".256.jpg"
    ]);
}

function mdSearch($q, $lm=5) {
    global $env;
    $tk = getToken();

    [$r, $rh] = fgc("https://api.mangadex.org/manga", "GET", [
        "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/"
    ], [
        "title"=> $q,
        "includes[]"=> "manga",
        "includes[]"=> "cover_art",
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
            //"thumb"=> mdExtractCover($e)["url"] ?? "",
            "id"=> $e["id"],
            "url"=> "https://mangadex.org/title/".$e["id"]
        ]);
    }, $r["data"], ));
}

function mdMangaCovers($mid, $lm=1, $res=256) {
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

    return(array_map(function($e) use ($mid, $res) {
        return([
            "id"=> $e["id"],
            "file"=> $e["attributes"]["fileName"],
            "url"=> "https://uploads.mangadex.org/covers/$mid/".$e["attributes"]["fileName"].".$res.jpg"
        ]);
    }, $r["data"]));
}


function getManga($pid) {
    if (is_int($pid)) {
        [$r, $rn] = req("SELECT * FROM webtoons WHERE id=:pid LIMIT 1", [
            "pid"=> $pid
        ]);
    } else {
        [$r, $rn] = req("SELECT * FROM webtoons WHERE pid=:pid LIMIT 1", [
            "pid"=> $pid
        ]);
    }

    if (count($r)==0) {
        return(null);
    } else {
        return($r[0]);
    }
}

?>