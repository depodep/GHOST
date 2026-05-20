#!/usr/bin/env python3
"""
============================================================
GHOST Incubator — Schedule Session Manager
File: /GHOST/schedule_manager.py

Purpose:
  - Checks for pending scheduled sessions
  - Updates session status based on device availability
  - Handles FAILED_TO_START when:
    * Device is already running a session
    * Device goes offline after grace period
  - Automatically starts scheduled sessions when device is online

Usage:
  python schedule_manager.py

Database Tables Used:
  - schedules (id, incubator_id, batch_id, status, scheduled_date, scheduled_time)
  - sessions (id, incubator_id, schedule_id, status, started_at, ended_at)
  - hardware_state (incubator_id, device_status, last_seen)
  - session_logs (session_id, event_type, message)

Logic Flow:
  1. Query pending schedules that have passed start time
  2. For each pending schedule:
     a. Check if device is already running a session → FAILED_TO_START
     b. Check device online status + grace period → FAILED_TO_START (if offline)
     c. Device online? → Auto-start session immediately
  3. Log all state changes and reasons
============================================================
"""

import os
import sys
import time
import json
import sqlite3
from datetime import datetime, timedelta
from pathlib import Path

# Try to use PyMySQL, fallback to sqlite3
try:
    import pymysql
    USING_MYSQL = True
except ImportError:
    pymysql = None
    USING_MYSQL = False

# ============================================================
# CONFIGURATION
# ============================================================

# MySQL Configuration (adjust to match your environment)
DB_HOST = os.getenv('DB_HOST', 'localhost')
DB_USER = os.getenv('DB_USER', 'root')
DB_PASS = os.getenv('DB_PASS', '')
DB_NAME = os.getenv('DB_NAME', 'ghost_incubator')

# Grace period for device coming online (in minutes)
# If device goes offline and doesn't come back within this period, mark session as FAILED_TO_START
GRACE_PERIOD_MINUTES = 5

# Check interval in seconds (how often the script polls the database)
CHECK_INTERVAL = 60

# ============================================================
# DATABASE FUNCTIONS
# ============================================================

def get_mysql_connection():
    """Establish MySQL connection"""
    if not USING_MYSQL or pymysql is None:
        print("[ERROR] PyMySQL is not installed in this Python environment.")
        return None

    try:
        connection = pymysql.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASS,
            database=DB_NAME,
            charset='utf8mb4',
            cursorclass=pymysql.cursors.DictCursor
        )
        return connection
    except Exception as e:
        print(f"[ERROR] MySQL connection failed: {e}")
        return None

def execute_query(query, params=None, fetch_one=False):
    """Execute SELECT query"""
    try:
        conn = get_mysql_connection()
        if not conn:
            return None
        
        cursor = conn.cursor()
        if params:
            cursor.execute(query, params)
        else:
            cursor.execute(query)
        
        result = cursor.fetchone() if fetch_one else cursor.fetchall()
        cursor.close()
        conn.close()
        return result
    except Exception as e:
        print(f"[ERROR] Query execution failed: {e}")
        return None

def execute_update(query, params=None):
    """Execute INSERT/UPDATE/DELETE query"""
    try:
        conn = get_mysql_connection()
        if not conn:
            return False
        
        cursor = conn.cursor()
        if params:
            cursor.execute(query, params)
        else:
            cursor.execute(query)
        
        conn.commit()
        affected_rows = cursor.rowcount
        cursor.close()
        conn.close()
        return affected_rows > 0
    except Exception as e:
        print(f"[ERROR] Update execution failed: {e}")
        return False

# ============================================================
# SCHEDULE MANAGER FUNCTIONS
# ============================================================

def get_pending_schedules():
    """
    Query schedules that are:
    - status = 'pending'
    - scheduled_time has passed (or is now)
    """
    query = """
        SELECT 
            s.id as schedule_id,
            s.incubator_id,
            s.batch_id,
            s.title,
            s.scheduled_date,
            s.scheduled_time,
            COALESCE(s.target_temp, ts.target_temp, 37.50) AS target_temp,
            COALESCE(s.target_humidity, ts.target_humidity, 55.00) AS target_humidity,
            i.device_prototype_status
        FROM schedules s
        LEFT JOIN incubators i ON s.incubator_id = i.id
        LEFT JOIN temperature_settings ts ON ts.incubator_id = s.incubator_id
        WHERE s.status = 'pending'
        AND CONCAT(s.scheduled_date, ' ', s.scheduled_time) <= NOW()
        ORDER BY s.scheduled_date ASC, s.scheduled_time ASC
    """
    
    schedules = execute_query(query)
    return schedules if schedules else []

def check_device_status(incubator_id):
    """
    Check if device is online.
    Returns: {
        'online': bool,
        'device_status': str ('online' or 'offline'),
        'last_seen': datetime or None,
        'session_running': bool
    }
    """
    query = """
        SELECT 
            device_status,
            last_seen,
            session_status,
            session_started_at
        FROM hardware_state
        WHERE incubator_id = %s
    """
    
    result = execute_query(query, (incubator_id,), fetch_one=True)
    
    if not result:
        return {
            'online': False,
            'device_status': 'unknown',
            'last_seen': None,
            'session_running': False
        }
    
    is_online = result.get('device_status') == 'online'
    session_running = result.get('session_status') == 'running'
    
    return {
        'online': is_online,
        'device_status': result.get('device_status'),
        'last_seen': result.get('last_seen'),
        'session_running': session_running
    }

def check_grace_period(incubator_id, scheduled_time):
    """
    Check if device has been offline past the grace period.
    If device went offline after scheduled_time and hasn't come back, return True (failed).
    """
    query = """
        SELECT last_seen FROM hardware_state
        WHERE incubator_id = %s
    """
    
    result = execute_query(query, (incubator_id,), fetch_one=True)
    
    if not result or not result.get('last_seen'):
        # Never seen this device
        return True
    
    last_seen = result.get('last_seen')
    grace_deadline = datetime.now() - timedelta(minutes=GRACE_PERIOD_MINUTES)
    
    # If last seen is before the grace deadline, it failed the grace period
    return last_seen < grace_deadline

def is_session_already_running(incubator_id):
    """Check if a session is already running for this incubator"""
    query = """
        SELECT COUNT(*) as count FROM sessions
        WHERE incubator_id = %s AND status = 'running'
    """
    
    result = execute_query(query, (incubator_id,), fetch_one=True)
    return result.get('count', 0) > 0 if result else False

def create_session(schedule_id, incubator_id, batch_id, target_temp, target_humidity):
    """Create a new session record"""
    query = """
        INSERT INTO sessions 
        (incubator_id, batch_id, schedule_id, status, target_temp, target_humidity, auto_started)
        VALUES (%s, %s, %s, 'running', %s, %s, 1)
    """
    
    success = execute_update(query, (incubator_id, batch_id, schedule_id, target_temp, target_humidity))
    return success

def mark_schedule_failed(schedule_id, reason):
    """
    Mark schedule as FAILED_TO_START and log the reason.
    Uses a custom status field or updates to a failed state.
    """
    # Update schedule status - add a reason field to track why it failed
    query = """
        UPDATE schedules 
        SET status = 'failed', notes = %s
        WHERE id = %s
    """
    
    success = execute_update(query, (reason, schedule_id))
    
    if success:
        log_schedule_event(schedule_id, 'failed_to_start', reason)
    
    return success

def log_schedule_event(schedule_id, event_type, message):
    """Log scheduler events in activity_logs to avoid session_logs FK constraints."""
    query = """
        INSERT INTO activity_logs
        (role, user_id, action, details, ip_address)
        VALUES ('system', NULL, %s, %s, '127.0.0.1')
    """

    log_message = f"Schedule #{schedule_id}: {message}"
    execute_update(query, (event_type, log_message))


def mark_schedule_done(schedule_id):
    """Mark schedule as done after successful session start."""
    query = """
        UPDATE schedules
        SET status = 'done'
        WHERE id = %s
    """
    return execute_update(query, (schedule_id,))


def mark_batch_incubating(batch_id):
    """Promote linked batch from scheduled to incubating when session starts."""
    if not batch_id:
        return True
    query = """
        UPDATE batches
        SET status = 'incubating'
        WHERE id = %s AND status = 'scheduled'
    """
    return execute_update(query, (batch_id,))

def send_device_start_command(incubator_id, schedule_id):
    """
    Send command to device to start the scheduled session.
    This could queue a command in hardware_commands table.
    """
    query = """
        INSERT INTO hardware_commands 
        (incubator_id, command, issued_at)
        VALUES (%s, %s, NOW())
    """
    
    command = f"start_session_{schedule_id}"
    success = execute_update(query, (incubator_id, command))
    
    if success:
        log_schedule_event(schedule_id, 'session_start_command_sent', 
                          f'Auto-start command sent to incubator {incubator_id}')
    
    return success

# ============================================================
# MAIN PROCESSING LOGIC
# ============================================================

def process_pending_schedules():
    """
    Main function: Check all pending schedules and update their status.
    """
    schedules = get_pending_schedules()
    
    if not schedules:
        print(f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] No pending schedules to process.")
        return
    
    print(f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] Processing {len(schedules)} pending schedule(s)...")
    
    for schedule in schedules:
        schedule_id = schedule.get('schedule_id')
        incubator_id = schedule.get('incubator_id')
        title = schedule.get('title')
        scheduled_time = schedule.get('scheduled_time')
        target_temp = schedule.get('target_temp')
        target_humidity = schedule.get('target_humidity')
        
        print(f"\n  → Schedule #{schedule_id}: '{title}' (Incubator #{incubator_id})")
        
        # ========== CHECK 1: Is a session already running? ==========
        if is_session_already_running(incubator_id):
            reason = f"Session already running on incubator {incubator_id}"
            print(f"    [FAILED] {reason}")
            mark_schedule_failed(schedule_id, reason)
            continue
        
        # ========== CHECK 2: Device status & grace period ==========
        device_info = check_device_status(incubator_id)
        
        if not device_info['online']:
            # Device is offline - check grace period
            if check_grace_period(incubator_id, scheduled_time):
                reason = f"Device offline after grace period ({GRACE_PERIOD_MINUTES} min)"
                print(f"    [FAILED] {reason}")
                mark_schedule_failed(schedule_id, reason)
                continue
            else:
                print(f"    [WAITING] Device offline but within grace period. Retrying...")
                # Don't change status, will check again next cycle
                continue
        
        # ========== CHECK 3: Device is online → AUTO-START ==========
        print(f"    [ONLINE] Device is online. Creating session...")
        
        # Create session record
        session_created = create_session(
            schedule_id, incubator_id, 
            schedule.get('batch_id'),
            target_temp, target_humidity
        )
        
        if session_created:
            # Send command to device to start
            send_device_start_command(incubator_id, schedule_id)
            mark_schedule_done(schedule_id)
            mark_batch_incubating(schedule.get('batch_id'))
            print(f"    [SUCCESS] Session started automatically for schedule #{schedule_id}")
        else:
            reason = "Failed to create session record"
            print(f"    [ERROR] {reason}")
            mark_schedule_failed(schedule_id, reason)

def run_daemon(interval=CHECK_INTERVAL):
    """
    Run the schedule manager as a daemon, checking periodically.
    """
    print("=" * 60)
    print("GHOST Schedule Session Manager")
    print(f"Database: {DB_NAME} @ {DB_HOST}")
    print(f"Grace Period: {GRACE_PERIOD_MINUTES} minutes")
    print(f"Check Interval: {interval} seconds")
    print("=" * 60)
    print()
    
    try:
        while True:
            process_pending_schedules()
            print(f"\n[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] Sleeping {interval}s...")
            time.sleep(interval)
    except KeyboardInterrupt:
        print("\n\n[STOPPED] Schedule manager stopped by user.")
        sys.exit(0)

# ============================================================
# CLI ENTRY POINT
# ============================================================

if __name__ == '__main__':
    import argparse
    
    parser = argparse.ArgumentParser(
        description='GHOST Schedule Session Manager',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  python schedule_manager.py                    # Run daemon (continuous)
  python schedule_manager.py --once             # Run once and exit
  python schedule_manager.py --interval 30      # Run daemon with 30s interval
        """
    )
    
    parser.add_argument('--once', action='store_true', 
                       help='Run once and exit (no daemon mode)')
    parser.add_argument('--interval', type=int, default=CHECK_INTERVAL,
                       help=f'Check interval in seconds (default: {CHECK_INTERVAL})')
    parser.add_argument('--grace-period', type=int, default=GRACE_PERIOD_MINUTES,
                       help=f'Grace period in minutes (default: {GRACE_PERIOD_MINUTES})')
    
    args = parser.parse_args()
    
    # Update grace period if provided
    if args.grace_period != GRACE_PERIOD_MINUTES:
        GRACE_PERIOD_MINUTES = args.grace_period
    
    # Check database connection
    print("[INFO] Checking database connection...")
    conn = get_mysql_connection()
    if not conn:
        print("[ERROR] Cannot connect to database. Check configuration.")
        sys.exit(1)
    conn.close()
    print("[OK] Database connection successful.")
    print()
    
    # Run once or as daemon
    if args.once:
        process_pending_schedules()
    else:
        run_daemon(args.interval)
