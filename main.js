
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
        var ctn = document.querySelector("#novel_search .search_results");
        if (sip.value.length < 3) {
            removeAllChildren(ctn);
            document.querySelector("#loading_circle").classList.remove("active");
            return;
        }
        if (sip.value==sipv) {
            document.querySelector("#loading_circle").classList.remove("active");
            return;
        }
        sipv=sip.value
        
        document.querySelector("#loading_circle").classList.add("active");

        removeAllChildren(ctn);

        if (sipt!=null) {
            clearTimeout(sipt);
        }
        sipt = setTimeout(()=>{
            console.log(sip.value);
            
            //removeAllChildren(ctn);

            //document.querySelector("#loading_circle").classList.add("active");

            get("search", {"q":sip.value}, (r)=>{
                console.log(r);

                removeAllChildren(ctn);
                document.querySelector("#loading_circle").classList.remove("active");

                var ct = r["content"];
                var bkmd = false;
                for (var e of ct) {
                    if (e.title==null) {
                        continue;
                    }

                    // /api/cover?id=${e.id} ${e.thumb}
                    // onerror="this.src='/res/image_placeholder.jpg';"
                    
                    var ce = document.createElement("div");
                    ce.className = "search_result "+e.type+(bkmd ? " marked" : "");
                    ce.title = e.id;
                    ce.innerHTML = `
                        <img class="thumbnail"  src="${e.thumb ?? "/api/cover?id="+e.id}" />
                        <h2>${e.title}</h2>
                        <svg class="bookmark_btn${bkmd ? ' marked' : ''}" viewBox="0 0 25.00 25.00" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.55"></g><g id="SVGRepo_iconCarrier"> <path fill-rule="evenodd" clip-rule="evenodd" d="M18.507 19.853V6.034C18.5116 5.49905 18.3034 4.98422 17.9283 4.60277C17.5532 4.22131 17.042 4.00449 16.507 4H8.50705C7.9721 4.00449 7.46085 4.22131 7.08577 4.60277C6.7107 4.98422 6.50252 5.49905 6.50705 6.034V19.853C6.45951 20.252 6.65541 20.6407 7.00441 20.8399C7.35342 21.039 7.78773 21.0099 8.10705 20.766L11.907 17.485C12.2496 17.1758 12.7705 17.1758 13.113 17.485L16.9071 20.767C17.2265 21.0111 17.6611 21.0402 18.0102 20.8407C18.3593 20.6413 18.5551 20.2522 18.507 19.853Z" stroke-width="0.65" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                    `;
                    ctn.appendChild(ce);

                    ce.addEventListener("click", ($ev)=>{

                    });
                }
            });
        }, 300);
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

