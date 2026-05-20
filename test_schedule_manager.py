#!/usr/bin/env python3
"""
============================================================
GHOST Schedule Manager — Test Suite
File: /GHOST/test_schedule_manager.py

Tests the schedule manager logic with mock data.
============================================================
"""

import unittest
import sys
from datetime import datetime, timedelta
from unittest.mock import patch, MagicMock

# Mock the schedule_manager module for testing
class MockScheduleManager:
    """Mock implementation to test logic without database"""
    
    def __init__(self):
        self.grace_period_minutes = 5
        self.schedules_db = []
        self.sessions_db = []
        self.hardware_state_db = {}
        self.session_logs = []
        self.commands_issued = []
    
    def add_schedule(self, schedule_id, incubator_id, title, status='pending', 
                    target_temp=37.5, target_humidity=60):
        """Add a mock schedule"""
        self.schedules_db.append({
            'schedule_id': schedule_id,
            'incubator_id': incubator_id,
            'title': title,
            'status': status,
            'target_temp': target_temp,
            'target_humidity': target_humidity,
            'scheduled_time': datetime.now().time()
        })
    
    def set_device_status(self, incubator_id, online=True, session_running=False, last_seen=None):
        """Set device status"""
        if last_seen is None:
            last_seen = datetime.now() if online else datetime.now() - timedelta(minutes=10)
        
        self.hardware_state_db[incubator_id] = {
            'device_status': 'online' if online else 'offline',
            'session_running': session_running,
            'last_seen': last_seen
        }
    
    def get_pending_schedules(self):
        """Get pending schedules"""
        return [s for s in self.schedules_db if s['status'] == 'pending']
    
    def check_device_status(self, incubator_id):
        """Check device status"""
        if incubator_id not in self.hardware_state_db:
            return {
                'online': False,
                'device_status': 'unknown',
                'last_seen': None,
                'session_running': False
            }
        
        state = self.hardware_state_db[incubator_id]
        return {
            'online': state['device_status'] == 'online',
            'device_status': state['device_status'],
            'last_seen': state['last_seen'],
            'session_running': state['session_running']
        }
    
    def is_session_already_running(self, incubator_id):
        """Check if session already running"""
        return any(s['incubator_id'] == incubator_id and s['status'] == 'running' 
                   for s in self.sessions_db)
    
    def check_grace_period(self, incubator_id):
        """Check if grace period expired"""
        if incubator_id not in self.hardware_state_db:
            return True
        
        last_seen = self.hardware_state_db[incubator_id]['last_seen']
        grace_deadline = datetime.now() - timedelta(minutes=self.grace_period_minutes)
        return last_seen < grace_deadline
    
    def create_session(self, schedule_id, incubator_id, batch_id, target_temp, target_humidity):
        """Create session"""
        self.sessions_db.append({
            'schedule_id': schedule_id,
            'incubator_id': incubator_id,
            'batch_id': batch_id,
            'status': 'running',
            'target_temp': target_temp,
            'target_humidity': target_humidity
        })
        return True
    
    def mark_schedule_failed(self, schedule_id, reason):
        """Mark schedule as failed"""
        for schedule in self.schedules_db:
            if schedule['schedule_id'] == schedule_id:
                schedule['status'] = 'failed'
                schedule['failure_reason'] = reason
                self.session_logs.append({
                    'schedule_id': schedule_id,
                    'event': 'failed_to_start',
                    'reason': reason
                })
                return True
        return False
    
    def send_device_start_command(self, incubator_id, schedule_id):
        """Send start command"""
        self.commands_issued.append({
            'incubator_id': incubator_id,
            'schedule_id': schedule_id,
            'command': f'start_session_{schedule_id}'
        })
        return True
    
    def process_schedule(self, schedule):
        """Process a single schedule"""
        schedule_id = schedule['schedule_id']
        incubator_id = schedule['incubator_id']
        
        # Check 1: Session already running?
        if self.is_session_already_running(incubator_id):
            reason = f"Session already running on incubator {incubator_id}"
            self.mark_schedule_failed(schedule_id, reason)
            return ('FAILED', reason)
        
        # Check 2: Device status & grace period
        device_info = self.check_device_status(incubator_id)
        
        if not device_info['online']:
            if self.check_grace_period(incubator_id):
                reason = f"Device offline after grace period ({self.grace_period_minutes} min)"
                self.mark_schedule_failed(schedule_id, reason)
                return ('FAILED', reason)
            else:
                return ('WAITING', 'Device offline but within grace period')
        
        # Check 3: Auto-start
        self.create_session(schedule_id, incubator_id, None, 
                           schedule.get('target_temp'), schedule.get('target_humidity'))
        self.send_device_start_command(incubator_id, schedule_id)
        
        # Mark schedule as done
        for sched in self.schedules_db:
            if sched['schedule_id'] == schedule_id:
                sched['status'] = 'done'
        
        return ('SUCCESS', 'Session started automatically')


# ============================================================
# TEST CASES
# ============================================================

class TestScheduleManager(unittest.TestCase):
    
    def setUp(self):
        """Setup test fixtures"""
        self.manager = MockScheduleManager()
    
    def test_successful_auto_start(self):
        """Test: Device online → auto-start succeeds"""
        # Setup
        self.manager.add_schedule(1, 1, 'Morning Turning', target_temp=37.5)
        self.manager.set_device_status(1, online=True, session_running=False)
        
        # Execute
        schedules = self.manager.get_pending_schedules()
        self.assertEqual(len(schedules), 1)
        
        status, msg = self.manager.process_schedule(schedules[0])
        
        # Assert
        self.assertEqual(status, 'SUCCESS')
        self.assertIn('started automatically', msg)
        self.assertTrue(self.manager.is_session_already_running(1))
        self.assertEqual(len(self.manager.commands_issued), 1)
    
    def test_fail_session_already_running(self):
        """Test: Fail when session already running"""
        # Setup
        self.manager.add_schedule(2, 1, 'Evening Turning')
        self.manager.set_device_status(1, online=True)
        # Pre-create a running session
        self.manager.sessions_db.append({
            'incubator_id': 1,
            'status': 'running',
            'schedule_id': 999
        })
        
        # Execute
        schedules = self.manager.get_pending_schedules()
        status, msg = self.manager.process_schedule(schedules[0])
        
        # Assert
        self.assertEqual(status, 'FAILED')
        self.assertIn('already running', msg)
        schedule = self.manager.schedules_db[0]
        self.assertEqual(schedule['status'], 'failed')
    
    def test_fail_device_offline_after_grace_period(self):
        """Test: Fail when device offline past grace period"""
        # Setup
        self.manager.add_schedule(3, 1, 'Candling Session')
        # Device offline for 10 minutes (past grace period of 5 min)
        old_time = datetime.now() - timedelta(minutes=10)
        self.manager.set_device_status(1, online=False, last_seen=old_time)
        
        # Execute
        schedules = self.manager.get_pending_schedules()
        status, msg = self.manager.process_schedule(schedules[0])
        
        # Assert
        self.assertEqual(status, 'FAILED')
        self.assertIn('grace period', msg.lower())
        schedule = self.manager.schedules_db[0]
        self.assertEqual(schedule['status'], 'failed')
    
    def test_wait_device_offline_within_grace_period(self):
        """Test: Wait when device offline but within grace period"""
        # Setup
        self.manager.add_schedule(4, 1, 'Humidity Check')
        # Device offline for 2 minutes (within grace period of 5 min)
        recent_time = datetime.now() - timedelta(minutes=2)
        self.manager.set_device_status(1, online=False, last_seen=recent_time)
        
        # Execute
        schedules = self.manager.get_pending_schedules()
        status, msg = self.manager.process_schedule(schedules[0])
        
        # Assert
        self.assertEqual(status, 'WAITING')
        self.assertIn('within grace period', msg.lower())
        # Schedule should still be pending
        schedule = self.manager.schedules_db[0]
        self.assertEqual(schedule['status'], 'pending')
    
    def test_multiple_schedules_mixed_outcomes(self):
        """Test: Process multiple schedules with different outcomes"""
        # Setup
        self.manager.add_schedule(10, 1, 'Schedule 1', target_temp=37.5)
        self.manager.add_schedule(11, 2, 'Schedule 2', target_temp=38.0)
        self.manager.add_schedule(12, 3, 'Schedule 3', target_temp=37.0)
        
        # Incubator 1: Online
        self.manager.set_device_status(1, online=True, session_running=False)
        
        # Incubator 2: Offline past grace period
        old_time = datetime.now() - timedelta(minutes=10)
        self.manager.set_device_status(2, online=False, last_seen=old_time)
        
        # Incubator 3: Offline within grace period
        recent_time = datetime.now() - timedelta(minutes=2)
        self.manager.set_device_status(3, online=False, last_seen=recent_time)
        
        # Execute
        schedules = self.manager.get_pending_schedules()
        results = []
        for schedule in schedules:
            status, msg = self.manager.process_schedule(schedule)
            results.append((schedule['schedule_id'], status))
        
        # Assert
        self.assertEqual(results[0][1], 'SUCCESS')  # Schedule 10: Success
        self.assertEqual(results[1][1], 'FAILED')   # Schedule 11: Failed
        self.assertEqual(results[2][1], 'WAITING')  # Schedule 12: Waiting
        
        # Verify command was only issued for successful schedule
        self.assertEqual(len(self.manager.commands_issued), 1)
        self.assertEqual(self.manager.commands_issued[0]['schedule_id'], 10)


# ============================================================
# RUN TESTS
# ============================================================

if __name__ == '__main__':
    print("\n" + "=" * 60)
    print("GHOST Schedule Manager — Test Suite")
    print("=" * 60 + "\n")
    
    # Run tests with verbose output
    unittest.main(verbosity=2, exit=True)
