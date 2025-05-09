import json
from urllib.parse import urlparse

BASEDIR = ".."

def getNovelProviders():
    return(json.load(open(BASEDIR+"/sources.json"))["novels"])

def getMangaProviders():
    return(json.load(open(BASEDIR+"/sources.json"))["mangas"])

def getProviders():
    return(json.load(open(BASEDIR+"/sources.json")))

def getProvider(url):
    pvds = getProviders()

    url = urlparse(url)
    host = url.netloc
    
    if (len(host.split(".")) > 2):
        host=host.split(".", 1)[1]

    pvd = next(((k,v) for k,v in pvds["mangas"].items() if k==host), None)
    type="manga"
    if (pvd==None):
        pvd = next(((k,v) for k,v in pvds["novels"].items() if k==host), None)
        type="novel"
    
    if pvd==None:
        return(host, None, None)
    
    return(host, type, pvd)


def fetchData(url):
    host, type, pvd = getProvider(url)

    print(host, type)

