
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


window.addEventListener("load", ()=>{
    if ((window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches && localStorage.getItem("theme")==null) || localStorage.getItem('theme')=="dark") {
        changeTheme('dark');
    }

    let vh = window.innerHeight * 0.01;
    document.documentElement.style.setProperty('--vh', `${vh}px`);
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

