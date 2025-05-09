<?php


function replaceVars($ct, $dict) {
    return(preg_replace_callback('/\$\[([^\]\r\n]{1,15})\]/', function ($matches) use ($dict) {
        $key = $matches[1];
        return $dict[$key] ?? $matches[0];
    }, $ct));
}


function extractOps($r, $ops, &$dt, $lvl=0) {
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
        echo($pre);
        echo("DT: ".implode(", ", array_keys($dt))."<br/>");
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
                echo("Source (".strlen($rs)."): ".htmlspecialchars(substr($rs, 0, 400))."...<br/>");
            }
        }
    }

    foreach($ops as $op) {
        $opn = $op[0];

        if ($dbg) {
            echo("OP [".$opn."] -> ".listformat($op, $pre)."<br/>\n");
        }

        $loop=false;
        if (substr($opn, 0, 1)=="@") {
            $opn = substr($opn, 1);
            $loop=true;
        } else if (is_array($r) && array_is_list($r)) {
            $loop=true;
        }


        if ($opn=="split") {
            if ($loop) {
                $r = array_map(function ($e) use ($op) {
                    return(explode($op[1], $e));
                }, $r);
            } else {
                $r = explode($op[1], $r);
            }
        
        } else if ($opn=="filter") {
            $r = array_filter($r, function ($e) use ($r, $op, &$dt) {
                return(extractOps($e, $op[1], $dt));
            });
        } else if ($opn=="subarray") {
            $r = array_slice($r, $op[1], $op[2]??null);
        
        } else if ($opn=="substr") {
            if ($loop) {
                $r = array_map(function ($e) use ($op) {
                    return(substr($e, $op[1], $op[2]??null));
                }, $r);
            } else {
                $r = substr($r, $op[1], $op[2]??null);
            }
        
        } else if ($opn=="cutbtw") {
            if ($loop) {
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
            if (!is_array($r)) {
                $r = null;
            } else {
                $r = $r[$op[1]];
            }
        } else if ($opn=="arraySelect") {
            if (array_is_list($r)) {
                $r = array_slice($r, $op[1], 1)[0];
            } else {
                $r = array_slice(array_values($r), $op[1], 1)[0];
            }
        } else if ($opn=="concat") {
            //echo((is_array($r) ? "IS ARRAY" : "IS NOT ARRAY")."<br/>");
            if ($loop) {
                $r = array_map(function ($e) use ($op, $dt, $lvl) {
                    $rs = "";
                    for($i=1; $i<count($op); $i++) {
                        $lop = $op[$i];

                        if (is_array($lop)) {
                            $rs.=extractOps($e, $lop, $dt, ($lvl??0)+1);
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

                $l[] = extractOps($r, $op, $dt, $lvl+1);
            }

            $r=$l;
        } else if ($opn=="!=") {
            if ($loop) {
                $r = array_map(function ($e) use ($op) {
                    return($e!=$op[1]);
                }, $r);
            } else {
                $r = ($r!=$op[1]);
            }
        } else if ($opn=="=" || $opn=="==") {
            if ($loop) {
                $r = array_map(function ($e) use ($op) {
                    return($e==$op[1]);
                }, $r);
            } else {
                $r = ($r==$op[1]);
            }
        } else if ($opn=="equals") {
            $a = $op[1];
            if (is_array($a)) {
                $a = extractOps($r, $a, $dt);
            }

            $b = $op[2];
            if (is_array($b)) {
                $b = extractOps($r, $b, $dt);
            }

            $r = ($a==$b);
        } else if ($opn=="ago_to_ts") {
            if ($loop) {
                $r = array_map(function ($e) use ($op) {
                    return(strtotime($e));
                }, $r);
            } else {
                $r = strtotime($r);
            }
        } else if ($opn=="date_to_ts") {
            if ($loop) {
                $r = array_map(function ($e) use ($op) {
                    return(strtotime($e));
                }, $r);
            } else {
                $r = strtotime($r);
            }
        } else if ($opn=="trim") {
            if ($loop) {
                $r = array_map(function ($e) use ($op) {
                    return(trim($e));
                }, $r);
            } else {
                $r = trim($r);
            }
        } else if ($opn=="remove_strings") {
            if ($loop) {
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
            if ($loop) {
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
                    $dt["CURRENT"] = $r;
                    $k = replaceVars($k, $dt);
                    $v = replaceVars($v, $dt);

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
                $dt["CURRENT"] = $r;
                $k = replaceVars($k, $dt);
                $v = replaceVars($v, $dt);
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
            $dt["CURRENT"] = $r;
            if (count($wheres) > 0) {
                $where=" WHERE ";
                foreach($wheres as $k=>$v) {
                    $k = replaceVars($k, $dt);
                    $v = replaceVars($v, $dt);

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
        } else if ($opn=="lcstoreops") {
            $dt[$op[1]] = extractOps($r, $op[2], $dt);
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
                $r = extractOps($r, $ex["ops"], $dt);
            }

            $r = extractOps($r, $ex[$key], $dt);
        } else if ($opn=="contains") {
            if ($loop) {
                $r = array_map(function ($e) use ($op) {
                    return(str_contains($e, $op[1]));
                }, $r);
            } else {
                $r = str_contains($r, $op[1]);
            }
        } else if ($opn=="dict") {
            if ($loop) {
                $r = array_map(function ($e) use ($op, $dt, $lvl, $r) {
                    $dt["CURRENT"] = $r;

                    $nr = [];
                    foreach($op[1] as $k=>$v) {
                        if (is_array($v)) {
                            $nr[$k] = extractOps($e, $v, $dt, $lvl+1);
                        } else {
                            $nr[$k]=replaceVars($v, $dt);
                        }
                    }
                    return($nr);

                }, $r);
            } else {
                $nr = [];
                $dt["CURRENT"] = $r;
                foreach($op[1] as $k=>$v) {
                    if (is_array($v)) {
                        $nr[$k] = extractOps($r, $v, $dt, $lvl+1);
                    } else {
                        $nr[$k] = replaceVars($v, $dt);
                    }
                }
                $r = $nr;
            }
        } else if ($opn=="appendBase") {
            if ($loop) {
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


?>