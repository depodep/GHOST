# GHOST Incubator — ESP8266 Automation Implementation

**Date:** May 18, 2026  
**Firmware Version:** v2.0 (Production)  
**Status:** ✅ Fully Implemented & Tested

---

## 1. PRODUCTION MODE ENABLED

### TEST_MODE: `false`
- ❌ Test mode relay control via `test.php` **DISABLED**
- ✅ **Actual incubation automation ENABLED**
- ✅ Automatic temperature control active
- ✅ Live server synchronization enabled

```cpp
const bool  TEST_MODE = false;  // ← PRODUCTION: Actual incubation automation enabled
```

---

## 2. TEMPERATURE CONTROL LOGIC

### Heating Control
When temperature drops **below target - 0.3°C**, heaters turn ON:
```
Example: Target = 37.5°C
Threshold = 37.2°C
→ If T < 37.2°C: Heaters ON
```

**Implementation:**
```cpp
float heatingThreshold = savedTargetTemp - 0.3;  // Turn heater ON below this
if (currentTemp < heatingThreshold && !heaterGroupOn) {
    setHeater(true);  // Both heater elements + fan ON
    currentThermoState = THERMO_HEATING;
}
```

### Exhaust Fan (Cooling) Control
When temperature exceeds **target + 0.3°C**, exhaust fan turns ON:
```
Example: Target = 37.5°C
Threshold = 37.8°C
→ If T >= 37.8°C: Exhaust ON
→ If T <= 37.5°C: Exhaust OFF (hysteresis prevents chatter)
```

**Implementation:**
```cpp
float coolingThreshold = savedTargetTemp + 0.3;  // Turn exhaust ON above this
if (currentTemp >= coolingThreshold && !exhaustOn) {
    setExhaust(true);
    exhaustTurnedOnByThermo = true;
    currentThermoState = THERMO_COOLING;
}
```

---

## 3. CIRCULATION FAN (Always ON During Incubation)

- **Always active** during incubation to ensure even heat distribution
- Improves temperature uniformity throughout incubator
- Continues even when heaters are off (circulation only)

```cpp
// CIRCULATION FAN (Heater fan)
// Always ON during incubation unless emergency
if (sessionMode == MODE_RUNNING && !heaterFanOn) {
    heaterFanOn = true;
    writeHeaterFan(true);
}
```

---

## 4. EMERGENCY PROTECTION

### Critical Temperature Threshold: 39.0°C

When **temperature ≥ 39°C**, emergency shutdown activates:

**Actions:**
1. ❌ Heaters OFF immediately
2. ✅ Exhaust fan ON
3. ✅ Circulation fan ON
4. 📡 Emergency alert sent to server
5. 🔴 Emergency state flag set (locks out automation until cleared)

**Reset Condition:**
- Emergency clears when temperature drops below **38.5°C** (hysteresis)

**Implementation:**
```cpp
if (currentTemp >= CRITICAL_TEMP_THRESHOLD) {  // >= 39°C
    if (!emergencyShutdown) {
        emergencyShutdown = true;
        setHeater(false);           // Kill heaters
        setExhaust(true);           // Force exhaust on
        heaterFanOn = true;         // Keep circulation
        postEmergencyAlertToServer();
    }
    return;  // Exit automation while emergency active
}
```

---

## 5. SESSION MANAGEMENT

### Session States

| State | Behavior |
|-------|----------|
| **IDLE** | Waiting for server parameters; all heating disabled |
| **RUNNING** | Active incubation; automation enabled; temperature control active |
| **COMPLETED** | Session time expired; all relays OFF; no automation |

### Auto-Transition to RUNNING

When "Start Incubation" is pressed on server:
1. Server creates active incubation session
2. ESP8266 fetches active session parameters
3. Mode automatically transitions from `IDLE` → `RUNNING`
4. Automation starts immediately
5. **Session resumes automatically after ESP reboot** (saved in EEPROM)

```cpp
if (sessionMode == MODE_IDLE && !TEST_MODE) {
    sessionMode = MODE_RUNNING;
    Serial.println(F("[Session] Switched to MODE_RUNNING"));
    Serial.println(F("[Automation] INCUBATION STARTED"));
}
```

---

## 6. RECEIVED PARAMETERS FROM SERVER

ESP8266 fetches and stores these settings from server every 30 seconds:

```json
{
    "target_temp": 37.5,           // Target temperature (°C)
    "min_temp": 37.2,              // (future use)
    "max_temp": 37.8,              // (future use)
    "target_hum": 55.0,            // Target humidity (%)
    "min_hum": 50.0,               // (future use)
    "max_hum": 60.0,               // (future use)
    "turning_interval": 8,         // Turning interval (hours)
    "session_name": "Batch001",    // Batch name
    "session_id": 123,             // Session ID
    "hatch_day": 21                // (extended - not yet used)
    "automation_enabled": true     // (extended - not yet used)
    "incubation_status": "running" // (extended - not yet used)
}
```

---

## 7. TELEMETRY (Server Updates)

### Posted Every 60 Seconds

ESP8266 sends comprehensive status to server for monitoring:

```json
{
    "incubator_id": 1,
    "temp": 37.45,
    "humidity": 58.2,
    "heater_group": true,
    "heater_fan": true,
    "heater_1": true,
    "heater_2": true,
    "eggswing": false,
    "exhaust": false,
    "mode": "RUNNING",
    "session_id": 123,
    "target_temp": 37.5,
    "automation_enabled": true,
    "emergency_shutdown": false,
    "thermostat_state": "IDLE",
    "wifi_connected": true,
    "server_connected": true,
    "uptime_ms": 3600000
}
```

### Serial Debug Logs

Real-time logging for monitoring:
```
[Heater] ON  — 37.1°C < 37.2°C (target: 37.5°C)
[Exhaust] ON  — 37.9°C >= 37.8°C (cooling active)
[Exhaust] OFF — 37.5°C <= 37.5°C (at target)
[EMERGENCY] CRITICAL TEMP REACHED: 39.5°C >= 39.0°C
[EMERGENCY] Heaters OFF, Exhaust ON, Circulation ON
[Session] Running: Temp=37.5°C Humidity=58.2% (Target: 37.5°C) [State: IDLE]
[Server] Telemetry posted (HTTP 200): T=37.5°C H=58.2% State=IDLE
```

---

## 8. OFFLINE OPERATION (Server Disconnected)

### Robust Local-Only Incubation

When WiFi/server becomes unavailable:
- ✅ **Automation continues** using last synced settings
- ✅ Temperature control remains active
- ✅ Emergency protection still active
- ✅ Relays continue switching normally
- 🔄 Auto-reconnect attempts every 5 seconds
- 📝 Status queued for when server returns

**No action needed from user** — incubation is not interrupted

---

## 9. RELAY STARTUP PROTECTION

### Boot Sequence

1. **EEPROM initialized**
2. **All relays set to OFF** (verified before anything else)
3. **Relay GPIO pins configured**
4. **Relay states verified OFF** in memory
5. **Startup cycle runs** (if enabled - currently disabled for safety)
6. **DHT sensor initialized**
7. **Session mode determined** (IDLE or RUNNING)
8. **Automation begins**

**Result:** No accidental heater activation on ESP startup

```cpp
// RELAY STARTUP: All relays OFF
writeHeaterFan(false);
writeHeater1(false);
writeHeater2(false);
writeEggSwing(false);
writeExhaust(false);

heaterGroupOn = false;
heater1On = false;
heater2On = false;
exhaustOn = false;
```

---

## 10. NON-BLOCKING TIMING (No delay() in Automation)

All timing uses `millis()` for non-blocking operation:

| Task | Interval |
|------|----------|
| Read DHT22 sensor | 10 seconds |
| Temperature automation | Real-time (< 10ms) |
| Thermostat polling | Every 10-60 seconds (integrated with sensor reads) |
| Relay switching | Immediate (< 1ms) |
| Server telemetry | 60 seconds |
| Settings sync | 30 seconds |
| Server reconnect | 5 seconds |

**No blocking delays in automation loop** — ensures responsive control

```cpp
if (now - lastSensorRead >= SENSOR_INTERVAL) {
    lastSensorRead = now;
    readTemperature();
    readHumidity();
}

runTemperatureAutomation();  // Called every loop iteration
```

---

## 11. MODULAR ARCHITECTURE

### State Machine: ThermostatState Enum

```cpp
enum ThermostatState {
    THERMO_IDLE,        // No heating or cooling active
    THERMO_HEATING,     // Heaters ON
    THERMO_COOLING,     // Exhaust fan ON
    THERMO_EMERGENCY    // Critical temperature — emergency shutdown
};
```

### Manager-Style Organization

- **runTemperatureAutomation()** — Core temperature control logic
- **postEmergencyAlertToServer()** — Emergency handling
- **fetchParametersFromServer()** — Settings synchronization
- **postSensorDataToServer()** — Telemetry upload
- **checkServerConnectivity()** — mDNS hostname resolution + connection management

---

## 12. SERIAL DEBUG OUTPUT

### Example Boot Log

```
========================================
  GHOST Incubator — ESP8266 v2.0
  INCUBATION AUTOMATION FIRMWARE
========================================

[Relay Init] Setting all relays to OFF...
[Relay Init] All relays verified OFF
[mDNS] Initialized successfully
[EEPROM] Valid parameters found!
[Parameters] Target:37.5°C (37.2-37.8)
[Parameters] Humidity:55.0% (50.0-60.0)
[Parameters] Turning interval: 8 hours
[Parameters] Session: Batch001 (ID:123)

╔════════════════════════════════════════════╗
║        SYSTEM STARTUP COMPLETE             ║
╠════════════════════════════════════════════╣
║ WiFi:     CONNECTED                        ║
║ Server:   incubator.local:80               ║
║ Temp:     37.5°C (Humidity: 58.2%)         ║
║                                            ║
║ MODE:     AUTOMATION (Production)          ║
║ Session:  RUNNING                          ║
║ Status:   Active incubation in progress    ║
║                                            ║
╚════════════════════════════════════════════╝
```

### Example Runtime Log (Every 4 seconds)

```
[AUTOMATION] WiFi=CONNECTED | Mode=INCUBATING | Temp=37.5°C (Target: 37.5°C) | Hum=58.2% | State=IDLE
[AUTOMATION] Relays: heater=OFF fan=ON h1=OFF h2=OFF swing=OFF exhaust=OFF
[Server] Telemetry posted (HTTP 200): T=37.5°C H=58.2% State=IDLE
```

---

## 13. IMPLEMENTATION SUMMARY

| Requirement | Status | Implementation |
|-------------|--------|-----------------|
| Disable TEST MODE | ✅ | `const bool TEST_MODE = false;` |
| Use live server settings | ✅ | Auto-fetch every 30s, store in EEPROM |
| Temperature automation | ✅ | `runTemperatureAutomation()` with state machine |
| Heating control | ✅ | ON when T < target-0.3°C, OFF when T >= target |
| Exhaust fan control | ✅ | ON when T >= target+0.3°C, OFF when T <= target |
| Circulation fan | ✅ | Always ON during incubation |
| Emergency protection | ✅ | Critical temp >= 39°C triggers safe shutdown |
| Offline operation | ✅ | Uses last synced settings, auto-reconnects |
| Auto-resume after reboot | ✅ | Session state saved in EEPROM |
| Non-blocking timing | ✅ | All timing uses millis(), no delays |
| Serial debugging | ✅ | Comprehensive logs for all state changes |
| Relay startup safety | ✅ | All relays OFF on boot, verified in code |
| Modular structure | ✅ | Separate manager functions, clear separation of concerns |
| Telemetry to server | ✅ | Full status posted every 60 seconds |

---

## 14. NEXT STEPS / OPTIONAL ENHANCEMENTS

- [ ] Add hatch day countdown tracking
- [ ] Implement egg turning schedule automation
- [ ] Add humidity control automation
- [ ] Support multiple temperature profiles (different species)
- [ ] WiFi signal strength monitoring
- [ ] EEPROM wear leveling for frequent updates
- [ ] OTA firmware updates
- [ ] Local web dashboard (http://ghost.local)

---

## 15. CRITICAL FILE LOCATIONS

- **Main Firmware:** [GHOST_ESP8266_v2.ino](GHOST_ESP8266_v2.ino)
- **Server Base Path:** `/GHOST` (XAMPP htdocs)
- **API Endpoints:** `/ajax/hardware_api.php`, `/api/log_temperature`, `/api/get_settings`
- **Test Interface:** [test.php](test.php)

---

**Status: READY FOR PRODUCTION** 🟢

All automation features implemented and tested. System ready for live incubation sessions.
