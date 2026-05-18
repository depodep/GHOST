# Temperature Automation — Quick Reference

## Temperature Control Thresholds

### Example Configuration
```
Target Temperature: 37.5°C
Tolerance: 0.3°C
```

### Heating Logic
```
Heater ON when:  T < 37.2°C  (target - 0.3)
Heater OFF when: T >= 37.5°C (target)

STATE: THERMO_HEATING
```

### Exhaust Fan Logic (Cooling)
```
Exhaust ON when:  T >= 37.8°C  (target + 0.3)
Exhaust OFF when: T <= 37.5°C  (target)

STATE: THERMO_COOLING
Hysteresis: 0.5°C prevents fan chatter
```

### Emergency Protection
```
Critical Threshold: 39.0°C

If T >= 39.0°C:
  ├─ HEATERS → OFF (immediate)
  ├─ EXHAUST → ON (forced)
  ├─ CIRCULATION → ON (forced)
  ├─ STATE → THERMO_EMERGENCY
  └─ ALERT → Sent to server

Clears when: T < 38.5°C (critical - hysteresis)
```

### Circulation Fan
```
Always ON during MODE_RUNNING
Continues even if heaters OFF
Provides heat distribution
```

## State Diagram

```
                    ┌──────────────────┐
                    │   THERMO_IDLE    │
                    └────────┬─────────┘
                             │
                    T < target-0.3°C
                             │
                    ┌────────▼─────────┐
                    │  THERMO_HEATING  │
                    │  Heater: ON      │
                    │  Exhaust: OFF    │
                    │  Fan: ON         │
                    └────────┬─────────┘
                             │
                    T >= target
                             │
                    ┌────────▼─────────┐
    ┌──────────────→│   THERMO_IDLE    │←─────────────┐
    │               │  Heater: OFF     │              │
    │               │  Exhaust: OFF    │              │
    │               │  Fan: ON         │              │
    │               └──────────────────┘              │
    │                       ▲                          │
    │          T <= target   │     T >= target+0.3    │
    │                        │                        │
    │               ┌────────┴─────────┐              │
    │               │ THERMO_COOLING   │              │
    │               │ Heater: OFF      │              │
    │               │ Exhaust: ON      │              │
    │               │ Fan: ON          │              │
    │               └──────────────────┘              │
    │                                                 │
    └─────────────────────────────────────────────────┘
              (Always return to IDLE)


           ┌──────────────────────────────────────┐
           │   EMERGENCY CONDITION                │
           │   T >= CRITICAL_TEMP (39°C)          │
           │   ├─ THERMO_EMERGENCY                │
           │   ├─ All heaters OFF                 │
           │   ├─ Exhaust forced ON               │
           │   ├─ Alert sent to server            │
           │   └─ Lock all automation             │
           │                                      │
           │   Clears when: T < 38.5°C            │
           └──────────────────────────────────────┘
```

## Implementation Functions

### Main Temperature Automation
```cpp
void runTemperatureAutomation() {
    // Runs every loop iteration
    // Checks emergency condition
    // Implements heating/cooling logic
    // Manages circulation fan
    // Non-blocking (< 1ms execution)
}
```

### Emergency Alert
```cpp
void postEmergencyAlertToServer() {
    // Sends critical temperature alert
    // Includes current temp + threshold
    // Posts to /api/emergency_alert
}
```

### Parameter Fetching
```cpp
void fetchParametersFromServer() {
    // Runs every 30 seconds
    // Gets target_temp, humidity targets
    // Saves to EEPROM (survives reboot)
    // Transitions IDLE → RUNNING when params loaded
}
```

### Telemetry Upload
```cpp
void postSensorDataToServer() {
    // Runs every 60 seconds
    // Includes:
    //   - Current temp/humidity
    //   - Relay states
    //   - Thermostat state
    //   - Emergency status
    //   - WiFi/Server status
}
```

## Serial Debug Messages

```
[Heater] ON  — 37.1°C < 37.2°C (target: 37.5°C)
[Heater] OFF — 37.5°C >= 37.5°C
[Exhaust] ON  — 37.9°C >= 37.8°C (cooling active)
[Exhaust] OFF — 37.5°C <= 37.5°C (at target)
[EMERGENCY] CRITICAL TEMP REACHED: 39.5°C >= 39.0°C
[EMERGENCY] Heaters OFF, Exhaust ON, Circulation ON
[EMERGENCY] CLEARED — Returning to normal automation
[Session] Running: Temp=37.5°C Humidity=58.2% (Target: 37.5°C) [State: HEATING]
[Server] Telemetry posted (HTTP 200): T=37.5°C H=58.2% State=IDLE
```

## Key Variables

```cpp
float currentTemp;              // Current temperature from DHT22
float savedTargetTemp;          // Target temperature (from server)
ThermostatState currentThermoState;  // Current automation state
bool emergencyShutdown;         // Emergency flag
bool exhaustTurnedOnByThermo;   // Tracks if thermostat turned fan ON
float heatingThreshold;         // savedTargetTemp - 0.3
float coolingThreshold;         // savedTargetTemp + 0.3
```

## Timing Configuration

```cpp
#define SENSOR_INTERVAL      10000UL   // Read temp/humidity
#define SETTINGS_INTERVAL    30000UL   // Fetch server settings
#define LOG_INTERVAL         60000UL   // Post telemetry
#define CRITICAL_TEMP_THRESHOLD    39.0   // Emergency limit
#define EXHAUST_HYSTERESIS         0.5    // Cooling hysteresis
```

---

**Temperature automation is the core of incubation success.**  
All control logic is deterministic and non-blocking.
