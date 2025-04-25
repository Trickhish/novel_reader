
function activate(sel) {
    var e = document.querySelector(sel);
    if (e) {
        e.classList.add("active");
    } else {
        setTimeout(()=>{
            activate(sel);
        }, 500);
    }
}

function deactivate(sel) {
    var e = document.querySelector(sel);
    if (e) {
        e.classList.remove("active");
    } else {
        setTimeout(()=>{
            deactivate(sel);
        }, 500);
    }
}

function changeTheme(f='') {
    if (f!='dark' && (document.documentElement.getAttribute("data-theme")=="dark" || f=='light')) {
        document.documentElement.setAttribute('data-theme', 'light');
        localStorage.setItem('theme', 'light');

        deactivate("#light_mode");
        activate("#dark_mode");
    }
    else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme', 'dark');

        activate("#light_mode");
        deactivate("#dark_mode");
    }
}

function parentDnDM(e, l) {
    if (e==null) {
        return(false);
    }

    if (l.some((cl)=> e.classList.contains(cl))) {
        return(true);
    } else {
        return(parentDnDM(e.parentElement, l));
    }
}
function getAllDnDM(e) {
    if (e==null) {
        return([]);
    } else {
        var dndm = e.getAttribute("data-dndm");
        if (dndm==null) {
            dndm=[];
        } else {
            dndm=dndm.split("/");
        }
        return(dndm.concat(getAllDnDM(e.parentElement)));
    }
}

function removeAllChildren(e) {
    while (e.firstChild) {
        e.removeChild(e.lastChild);
    }
}

window.addEventListener("load", ()=>{
    if ((window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches && localStorage.getItem("theme")==null) || localStorage.getItem('theme')=="dark") {
        changeTheme('dark');
    }

    let vh = window.innerHeight * 0.01;
    document.documentElement.style.setProperty('--vh', `${vh}px`);
});

window.addEventListener('DOMContentLoaded', () => {
    const sip = document.getElementById('novel_search_input');
    var sipt=null;
    var sipv="";
    function search(ev) {
        if (sip.value.length < 3 || sip.value==sipv) {
            return;
        }
        sipv=sip.value

        if (sipt!=null) {
            clearTimeout(sipt);
        }
        sipt = setTimeout(()=>{
            console.log(sip.value);

            get("search", {"q":sip.value}, (r)=>{
                console.log(r);

                var ctn = document.querySelector("#novel_search .search_results");
                removeAllChildren(ctn);

                var ct = r["content"];
                var bkmd = false;
                for (var e of ct) {
                    if (e.title==null) {
                        continue;
                    }
                    
                    var ce = document.createElement("div");
                    ce.className = "search_result "+e.type+(bkmd ? " marked" : "");
                    ce.innerHTML = `
                        <img class="thumbnail" onerror="this.src='/res/image_placeholder.jpg';" src="${e.thumb}" />
                        <h2>${e.title}</h2>
                        <img class="bookmark_btn${bkmd ? ' marked' : ''}" src="res/bookmarkws.png" />
                    `;
                    ctn.appendChild(ce);
                }
            });
        }, 500);
    }
    sip.addEventListener('change', search);
    sip.addEventListener('keyup', search);
});

window.onclick = function(e){
    var tg = e.target;
    var wtl = getAllDnDM(tg);

    Array.from(document.getElementsByClassName("dismiss")).forEach((el)=>{
        if (!wtl.some((sel) => Array.from(document.querySelectorAll(sel)).includes(el))) { // el.classList.contains(cl)
            el.classList.remove("active");
        }
    });
}

if ((window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches && localStorage.getItem("theme")==null) || localStorage.getItem('theme')=="dark") {
    changeTheme('dark');
}

if (localStorage.getItem('theme')!=null) {
    changeTheme(localStorage.getItem('theme'));
}

