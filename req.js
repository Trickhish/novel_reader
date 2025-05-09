function fgc(file, mode=false) { var requete = null; if (mode == undefined || mode == '') mode = false; if (window.XMLHttpRequest) {requete = new XMLHttpRequest();} else if (window.ActiveXObject) { requete = new ActiveXObject('Microsoft.XMLHTTP');} else return('false'); requete.open('GET', file, mode); requete.send(null); return requete.responseText;}

function fgca(url, s, e=function(){}) {
    var rq = new XMLHttpRequest();
    rq.open('GET', url, true);

    //rq.setRequestHeader("X-Token", tk);

    rq.onload = function() {
        var r = rq.responseText;
        s(r, rq.status);
    };
    rq.onerror = e;

    rq.send();
}

function posta(gp, pp={}, s, e=function(){}) {
    var rq = new XMLHttpRequest();
    rq.open('POST', "/api/"+gp, true);

    //console.log(pp);

    var fd = new FormData();
    for (var [pk, pv] of Object.entries(pp)) {
        fd.append(pk, pv);
    }

    //rq.setRequestHeader("X-Token", tk);

    rq.onload = function() {
        var r = rq.responseText;
        s(r, rq.status);
    }

    rq.onerror=e;

    rq.send(fd);
}

function postr(gp, pp={}) {
    var rq = new XMLHttpRequest();
    rq.open('POST', "/api/"+gp, false);

    //console.log(pp);

    //rq.setRequestHeader("X-Token", tk);

    var fd = new FormData();
    for (var [pk, pv] of Object.entries(pp)) {
        fd.append(pk, pv);
    }
    rq.send(fd);
    var r = rq.responseText;
    //console.log(gp+' => '+r);

    return(r);
}

// Synchronous api call
function action(p) {
    var r = fgc('/api/'+p);
    //console.log(p+' => '+r);

    return(r);
}

// Asynchronous api call
function aaction(p, s=function(){}, e=function(){}) {
    fgca('/api/'+p, s, e);
}

function get(p, params={}, s=function(){}, e=function(){}) {
    if (!window.navigator.onLine) { // no internet
        console.log("NO INTERNET");
        return;
    }

    var pt="";
    for (var [pk, pv] of Object.entries(params)) {
        if (pt=='') {
            pt="?";
        } else {
            pt+="&";
        }
        pt+=pk+"="+encodeURIComponent(pv);
    }

    fgca('/api/'+p+pt, (r, sc)=> {
        if (sc>=200 && sc<=299) { // success
            s(JSON.parse(r));
        } else {
            e(JSON.parse(r), sc);
        }
    }, (err)=> { // error : maybe no internet
        console.log("error: ",err);
    });
}

function post(p, params={}, s=function(){}, e=function(){}) {
    if (!window.navigator.onLine) { // no internet
        console.log("NO INTERNET");
        return;
    }

    posta(p, params, (r, sc)=>{
        if (sc>=200 && sc<=299) { // success
            s(r);
        } else {
            e(r, sc);
        }
    }, (err)=>{ // error : maybe no internet
        console.log("error: ",err);
    });
}

