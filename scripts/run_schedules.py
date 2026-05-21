#!/usr/bin/env python3
"""
Python runner to trigger the PHP schedule processor without cron.
Usage:
  python scripts/run_schedules.py --once
  python scripts/run_schedules.py --interval 60
  python scripts/run_schedules.py --interval 30 --incubator 1
"""
import argparse
import subprocess
import sys
import time
from datetime import datetime
import os

parser = argparse.ArgumentParser(description='Run GHOST schedule processor (calls PHP CLI).')
parser.add_argument('--interval', '-i', type=int, default=30, help='Seconds between runs. Default 30s. If set to 0 or omitted behaviour is once.')
parser.add_argument('--incubator', type=int, default=None, help='Optional incubator id to limit processing')
parser.add_argument('--php-path', default='php', help='Path to PHP executable')
parser.add_argument('--php-script', default='scripts/run_schedules.php', help='Path to PHP runner (relative to repo root)')
parser.add_argument('--grace', type=int, default=180, help='Grace period seconds for device offline handling (default 180)')
args = parser.parse_args()

php_cmd_base = [args.php_path, args.php_script]
if args.incubator:
    php_cmd_base.append(str(args.incubator))
# pass grace explicitly as a named flag
php_cmd_base.append(f"--grace={args.grace}")

LAST_RUN_FILE = os.path.join(os.path.dirname(__file__), 'run_schedules.last')

def touch_last_run():
    try:
        with open(LAST_RUN_FILE, 'w') as f:
            f.write(datetime.utcnow().isoformat())
    except Exception:
        pass


def run_once():
    now = datetime.utcnow().isoformat()
    cmd = php_cmd_base
    try:
        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=300)
        print(f"[{now}] exit={proc.returncode}")
        if proc.stdout:
            print(proc.stdout.strip())
        if proc.stderr:
            print(proc.stderr.strip(), file=sys.stderr)
    except Exception as e:
        print(f"[{now}] Exception: {e}", file=sys.stderr)
    finally:
        touch_last_run()

if args.interval is None or args.interval <= 0:
    run_once()
    sys.exit(0)

print(f"Starting schedule runner: interval={args.interval}s incubator={args.incubator}")
try:
    while True:
        run_once()
        time.sleep(max(1, args.interval))
except KeyboardInterrupt:
    print('Stopping runner')
    sys.exit(0)
