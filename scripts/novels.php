<?php



function getNovelSearchProviders() {
    $sources = file_get_contents("sources.json");

    return(json_decode($sources, true)["novels"]);
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

    if ($r===false) {
        return(false);
    }

    $ex = $pvd["search"]["extract"];

    $dt = [];
    $r = extractOps($r, $ex["ops"], $dt);

    $rl=[];
    foreach($r as $re) {
        $dt = [];

        $nl=[];

        if (array_key_exists("link", $ex)) {
            $url = extractOps($re, $ex["link"], $dt);
            if (substr($url, 0, 4)!="http") {
                $url = $pvd["baseUrl"].(substr($url, 0, 1)=="/" ? "" : "/").$url;
            }

            $nl["url"] = $url;
        } else {
            $nl["url"] = null;
        }

        foreach([
            "name"=> "title",
            "thumb"=> "thumb",
            "id"=> "id"
        ] as $k=>$v) {
            if (array_key_exists($k, $ex)) {
                $nl[$v] = extractOps($re, $ex[$k], $dt);
            } else {
                $nl[$v] = null;
            }
        }

        $rl[] = $nl;

        /*$rl[] = [
            "title"=>$title,
            "url"=>$url,
            "thumb"=>$thumb
        ];*/
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
        
        starttime("novel_search: $pvdn");
        $r = pvdsearchNovels($q, $pvdn);
        stoptime("novel_search: $pvdn");

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

?>