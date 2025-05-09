import subprocess
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler
import time
import redis
import json
import os
import sys
import argparse

class ChangeHandler(FileSystemEventHandler):
    def __init__(self, observer):
        self.observer = observer
    
    def on_modified(self, event):
        if event.src_path.endswith('.py'):
            print(f"{event.src_path} changed, restarting...\n")
            self.observer.stop()
            python = sys.executable
            os.execv(python, [python] + sys.argv)

if __name__ == "__main__":
    r = redis.Redis(host='localhost', port=6379, db=0)
    
    parser = argparse.ArgumentParser(description="")
    parser.add_argument("-d", "--debug", action='store_true', help='Debug mode')
    args = parser.parse_args()

    print(f"📚 ReadApp Daemon started")

    if (args.debug):
        print(", ".join([f"{k}={v}" for k,v in vars(args).items()]))

        observer = Observer()
        event_handler = ChangeHandler(observer)
        observer.schedule(event_handler, path=".", recursive=True)
        observer.start()

    while True:
        try:
            _, job = r.blpop('readapp_jobs')
            data = json.loads(job.decode())

            dt = data["payload"]
            a = data["action"]
            ts = data["timestamp"]

            match a:
                case "test":
                    print(f"[TEST]: {dt["message"]}")
                case "fetchData":
                    pass
                case _:
                    print(f"Unknown action '{a}'\n     {dt}")
        except KeyboardInterrupt:
            print(f"Quitting")
            break

    if (args.debug):
        observer.stop()
        observer.join()