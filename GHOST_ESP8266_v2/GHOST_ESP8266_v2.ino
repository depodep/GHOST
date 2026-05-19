/*
 * ============================================================
 *  GHOST Egg Incubator — ESP8266 Firmware  v2.0
 *
 *  Hardware:
 *    ESP8266 NodeMCU / Wemos D1 Mini
 *    DHT22    — temperature + humidity sensor (D2)
 *    Heater 1 relay (D5)
 *    Heater 2 relay (D6)
 *    Heater fan relay (D7)
 *    Egg swing relay (D1)
 *    Exhaust relay (D0)
 *
 *  Libraries required (install via Library Manager):
 *    - DHT sensor library   by Adafruit
 *    - Adafruit Unified Sensor by Adafruit
 *    - ArduinoJson          v6.x by Benoit Blanchon
 *    - ESP8266WiFi          (bundled with ESP8266 board package)
 *    - ESP8266HTTPClient    (bundled with ESP8266 board package)
 * ============================================================
 */

#include <ESP8266WiFi.h>
#include <ESP8266WebServer.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>
#include <EEPROM.h>
#include <DHT.h>
#include <ArduinoJson.h>
#include <time.h>

// ─────────────────────────────────────────────
//  ★  USER CONFIGURATION — EDIT THESE  ★
// ─────────────────────────────────────────────

// ─────────────────────────────────────────────
//  HOME NETWORK (Router WiFi)
// ─────────────────────────────────────────────
// ESP8266 connects to home WiFi to access server
const char* WIFI_SSID     = "Incubator wifi";
const char* WIFI_PASSWORD = "Incubator2026";

// ─────────────────────────────────────────────
//  SERVER CONFIGURATION (Laptop Server)
// ─────────────────────────────────────────────

// const char* SERVER_IP     = "192.168.70.46";  
const char* SERVER_IP     = "10.153.245.46";  
const int   SERVER_PORT   = 80;               
const char* SERVER_BASE_PATH = "/GHOST";       
const int   INCUBATOR_ID  = 1;                 
// ─────────────────────────────────────────────
//  LOCAL AP NETWORK (Optional - for debugging)
// ─────────────────────────────────────────────
const bool  ENABLE_AP_MODE = false;           // Set to true for local AP access
const char* AP_SSID       = "GHOST_Incubator";
const char* AP_PASSWORD   = "ghost2024pass";

// ─────────────────────────────────────────────
//  TEST MODE (for relay control via test.php)
// ─────────────────────────────────────────────
const bool  TEST_MODE = false;  
// Startup relay sequence: keep disabled by default to avoid boot pulses on real loads.
const bool  ENABLE_STARTUP_RELAY_CYCLE = false;
const unsigned long STARTUP_RELAY_OFF_MS = 200;
const unsigned long STARTUP_RELAY_ON_MS  = 300;
const unsigned long BOOT_ALL_MODULES_MS  = 10000;

// ─────────────────────────────────────────────
//  EEPROM ADDRESSES FOR LOCAL STORAGE
// ─────────────────────────────────────────────
// Max EEPROM on ESP8266: 4096 bytes
#define EEPROM_SIZE         4096
#define EEPROM_TARGET_TEMP  0      // float (4 bytes)
#define EEPROM_MIN_TEMP     4      // float (4 bytes)
#define EEPROM_MAX_TEMP     8      // float (4 bytes)
#define EEPROM_TARGET_HUM   12     // float (4 bytes)
#define EEPROM_MIN_HUM      16     // float (4 bytes)
#define EEPROM_MAX_HUM      20     // float (4 bytes)
#define EEPROM_TURNING_INT  24     // int (4 bytes)
#define EEPROM_SESSION_NAME 28     // string (50 bytes) - batch name
#define EEPROM_SESSION_ID   78     // int (4 bytes) - session ID from server
#define EEPROM_INIT_FLAG    82     // byte (1 byte) - 0xFF = initialized
#define EEPROM_SWING_SEC    83     // int (4 bytes) - last swing duration sec
#define EEPROM_SESSION_MODE 87     // byte (1 byte) - SessionMode

// ─────────────────────────────────────────────
//  PIN DEFINITIONS
// ─────────────────────────────────────────────
#define DHT_PIN        D2   // DHT22 data pin (temperature + humidity)
#define DHT_TYPE       DHT22

// Relay pin assignments (boot-safer ESP8266 layout)
#define RELAY_HEATER_1     D5   // Heater element 1
#define RELAY_HEATER_2     D6   // Heater element 2
#define RELAY_HEATER_FAN   D7   // Heater fan
#define RELAY_EGGSWING     D1   // Egg swing motor
#define RELAY_EXHAUST      D0   // Exhaust fan / ventilation

// ─────────────────────────────────────────────
//  RELAY TRIGGER POLARITY
//  Set per relay module type:
//    true  = active LOW  (IN=LOW turns relay ON)
//    false = active HIGH (IN=HIGH turns relay ON)
// Most 5V relay boards with H/L jumper in L mode are active LOW.
// ─────────────────────────────────────────────
const bool RELAY_ACTIVE_LOW_DEFAULT    = true;
const bool RELAY_ACTIVE_LOW_HEATER_FAN = RELAY_ACTIVE_LOW_DEFAULT;
const bool RELAY_ACTIVE_LOW_HEATER_1   = RELAY_ACTIVE_LOW_DEFAULT;
const bool RELAY_ACTIVE_LOW_HEATER_2   = RELAY_ACTIVE_LOW_DEFAULT;
const bool RELAY_ACTIVE_LOW_EGGSWING   = RELAY_ACTIVE_LOW_DEFAULT;
const bool RELAY_ACTIVE_LOW_EXHAUST    = RELAY_ACTIVE_LOW_DEFAULT;

// ─────────────────────────────────────────────
//  TIMING  (milliseconds)
// ─────────────────────────────────────────────
#define SENSOR_INTERVAL      10000UL   // Read sensors every 10 s
#define LOG_INTERVAL         60000UL   // Log to server every 60 s
#define SETTINGS_INTERVAL    30000UL   // Fetch temp settings every 30 s
#define IDLE_SETTINGS_INTERVAL  5000UL  // Check for new session quickly while idle
#define SCHEDULE_INTERVAL    30000UL   // Check turning schedules every 30 s
#define COMMAND_INTERVAL      5000UL   // Poll manual commands every 5 s
#define STATUS_INTERVAL       4000UL   // Send live heartbeat every 4 s
#define SWING_DURATION       30000UL   // Swing motors run 30 s per cycle
#define WIFI_RECONNECT_MS    10000UL   // Retry Wi-Fi every 10 s
// Exhaust control
#define EXHAUST_CYCLE_INTERVAL_MS   30000UL  // 30 s
#define EXHAUST_CYCLE_DURATION_MS   30000UL  // 30 s
#define EXHAUST_OVERHEAT_DELTA        0.2f   // C above target

// ─────────────────────────────────────────────
//  SENSOR & CLIENT OBJECTS
// ─────────────────────────────────────────────
DHT                    dht(DHT_PIN, DHT_TYPE);
WiFiClient             wifiClient;  // For server communication
HTTPClient             httpClient;  // For HTTP requests to server
ESP8266WebServer       testServer(80);  // HTTP server for test endpoints only

// ─────────────────────────────────────────────
//  LIVE SENSOR VALUES
// ─────────────────────────────────────────────
float currentTemp     = 0.0;
float currentHumidity = 0.0;
bool  tempOK          = false;
bool  humOK           = false;

// ─────────────────────────────────────────────
//  SESSION STATE (LOCAL - stored in EEPROM)
// ─────────────────────────────────────────────
// Modes: IDLE (waiting for params), RUNNING (session active), COMPLETED
enum SessionMode { MODE_IDLE, MODE_RUNNING, MODE_COMPLETED };
SessionMode sessionMode = MODE_IDLE;

// Session info
char  sessionName[50]   = "";           // Batch/session name
int   sessionId         = 0;            // From server
char  nextSessionName[50] = "";         // Next scheduled batch/session name
int   nextSessionId       = 0;           // Next scheduled batch/session id
time_t nextSessionStartEpoch = 0;        // Next scheduled start time (Unix epoch)
bool   ntpTimeReady = false;             // True once NTP time looks valid
unsigned long sessionStartedAt = 0;     // Timestamp when started
unsigned long sessionEndsAt    = 0;     // Calculated end time
bool  hasValidParams    = false;        // True if EEPROM has saved params

// Parameters loaded from EEPROM (or received from server)
float savedTargetTemp   = 37.50;
float savedMinTemp      = 37.00;
float savedMaxTemp      = 38.00;
float savedTargetHum    = 55.00;
float savedMinHum       = 50.00;
float savedMaxHum       = 60.00;
float savedTurningInt   = 8.0f;
int   savedSwingDurationSec = 30;
bool  turningLockdownActive = false;
int   turningLockdownDaysRemaining = -1;

// ─────────────────────────────────────────────
//  RELAY STATE
// ─────────────────────────────────────────────
// Group + individual relay state flags
bool heaterGroupOn = false; // overall heater group state
bool heaterFanOn   = false;
bool heater1On     = false;
bool heater2On     = false;
bool eggswingOn    = false;
bool exhaustOn     = false;
bool  idleRelayTestHold = false;

// Manual override flags (set by dashboard commands)
bool heaterManualOverride = false;   // true = dashboard forced a state
bool swingManualOverride  = false;

// ─────────────────────────────────────────────
//  TIMERS (for local operation - offline mode)
// ─────────────────────────────────────────────
unsigned long lastSensorRead    = 0;
unsigned long lastScheduleFetch = 0;
unsigned long lastServerPost    = 0;
unsigned long lastSettingsFetch = 0;
unsigned long swingStartedAt    = 0;
unsigned long lastSwingScheduledAt = 0;
unsigned long lastStatusPrint   = 0;
unsigned long lastHeartbeatSent = 0;
unsigned long lastExhaustCycleAt = 0;
unsigned long exhaustCycleStartedAt = 0;
unsigned long lastIdleRelayPoll = 0;
bool exhaustCycleActive = false;

const char* getCurrentModeLabel() {
    if (TEST_MODE) return "TEST_MODE";
    if (sessionMode == MODE_RUNNING) return "INCUBATING";
    if (sessionMode == MODE_COMPLETED) return "COMPLETED";
    return "IDLE";
}

void printRelayStates(const char* tag) {
    Serial.printf("[%s] Relays: heater=%s fan=%s h1=%s h2=%s swing=%s exhaust=%s\n",
        tag,
        heaterGroupOn ? "ON" : "OFF",
        heaterFanOn ? "ON" : "OFF",
        heater1On ? "ON" : "OFF",
        heater2On ? "ON" : "OFF",
        eggswingOn ? "ON" : "OFF",
        exhaustOn ? "ON" : "OFF");
}

void printPollingStatus(const char* tag) {
    const char* wifiStatus = (WiFi.status() == WL_CONNECTED) ? "CONNECTED" : "DISCONNECTED";
    Serial.printf("[%s] WiFi=%s | Mode=%s | Temp=%.1fC | Hum=%.1f%%\n",
        tag, wifiStatus, getCurrentModeLabel(), currentTemp, currentHumidity);
    printRelayStates(tag);
}

uint8_t relayLevel(bool on, bool activeLow) {
    return on ? (activeLow ? LOW : HIGH) : (activeLow ? HIGH : LOW);
}

void writeHeaterFan(bool on) {
    digitalWrite(RELAY_HEATER_FAN, relayLevel(on, RELAY_ACTIVE_LOW_HEATER_FAN));
}

void writeHeater1(bool on) {
    digitalWrite(RELAY_HEATER_1, relayLevel(on, RELAY_ACTIVE_LOW_HEATER_1));
}

void writeHeater2(bool on) {
    digitalWrite(RELAY_HEATER_2, relayLevel(on, RELAY_ACTIVE_LOW_HEATER_2));
}

void writeEggSwing(bool on) {
    digitalWrite(RELAY_EGGSWING, relayLevel(on, RELAY_ACTIVE_LOW_EGGSWING));
}

void writeExhaust(bool on) {
    digitalWrite(RELAY_EXHAUST, relayLevel(on, RELAY_ACTIVE_LOW_EXHAUST));
}

void runStartupRelayCycle() {
    if (!ENABLE_STARTUP_RELAY_CYCLE) {
        return;
    }

    Serial.println(F("[Startup] Relay cycle: OFF -> ON -> OFF"));

    setHeater(false);
    setSwing(false);
    setExhaust(false);
    delay(STARTUP_RELAY_OFF_MS);

    setHeater(true);
    setSwing(true);
    setExhaust(true);
    delay(STARTUP_RELAY_ON_MS);

    setHeater(false);
    setSwing(false);
    setExhaust(false);
    delay(STARTUP_RELAY_OFF_MS);

    printRelayStates("STARTUP_CYCLE");
}

void runBootAllModulesSequence() {
    Serial.printf("[Boot] All modules ON for %lu ms before server sync\n", BOOT_ALL_MODULES_MS);

    setHeater(true);
    setSwing(true);
    setExhaust(true);
    printRelayStates("BOOT_ALL_ON");

    delay(BOOT_ALL_MODULES_MS);

    setHeater(false);
    setSwing(false);
    setExhaust(false);
    printRelayStates("BOOT_ALL_OFF");
    Serial.println(F("[Boot] Startup module sequence complete"));
}

bool systemTimeLooksValid() {
    time_t now = time(nullptr);
    return now > 1700000000;
}

bool syncTimeFromNtp() {
    if (WiFi.status() != WL_CONNECTED) {
        return false;
    }

    configTime(0, 0, "pool.ntp.org", "time.nist.gov", "time.google.com");
    const unsigned long start = millis();
    while (!systemTimeLooksValid() && millis() - start < 10000UL) {
        delay(250);
        yield();
    }

    ntpTimeReady = systemTimeLooksValid();
    if (ntpTimeReady) {
        Serial.println(F("[NTP] Time synchronized"));
    } else {
        Serial.println(F("[NTP] Time sync not ready"));
    }
    return ntpTimeReady;
}

void tryStartScheduledSessionOffline() {
    if (sessionMode != MODE_IDLE) {
        return;
    }
    if (nextSessionId == 0 || nextSessionStartEpoch == 0 || !systemTimeLooksValid()) {
        return;
    }

    time_t nowEpoch = time(nullptr);
    if (nowEpoch < nextSessionStartEpoch) {
        return;
    }

    sessionMode = MODE_RUNNING;
    sessionId = nextSessionId;
    strncpy(sessionName, nextSessionName, sizeof(sessionName) - 1);
    sessionName[sizeof(sessionName) - 1] = '\0';
    sessionStartedAt = millis();
    sessionEndsAt = sessionStartedAt + 21UL * 24UL * 3600UL * 1000UL;
    lastSwingScheduledAt = 0;
    setHeater(true);
    bootNeedsServerConfirm = true;
    Serial.printf("[Session] Offline scheduled start triggered at %ld for #%d %s\n",
        (long)nowEpoch, sessionId, sessionName);
    Serial.println(F("[Mode] Switched to INCUBATING (offline scheduled start)"));
}



// ─────────────────────────────────────────────
//  SETUP
// ─────────────────────────────────────────────
void setup() {
    Serial.begin(115200);
    delay(300);

    Serial.println(F("\n========================================"));
    Serial.println(F("  GHOST Incubator — ESP8266 v2.0"));
    Serial.println(F("  STA MODE (Connecting to Lia)"));
    Serial.println(F("========================================\n"));

    // Initialize EEPROM
    EEPROM.begin(EEPROM_SIZE);
    
    // Relay pins — default OFF before anything else
    pinMode(RELAY_HEATER_FAN, OUTPUT);
    pinMode(RELAY_HEATER_1, OUTPUT);
    pinMode(RELAY_HEATER_2, OUTPUT);
    pinMode(RELAY_EGGSWING, OUTPUT);
    pinMode(RELAY_EXHAUST, OUTPUT);

    writeHeaterFan(false);
    writeHeater1(false);
    writeHeater2(false);
    writeEggSwing(false);
    writeExhaust(false);

    runBootAllModulesSequence();
    runStartupRelayCycle();

    // Start sensor
    dht.begin();

    // Load saved parameters from EEPROM
    loadParametersFromEEPROM();
    
    // Check if EEPROM has valid data
    if (EEPROM.read(EEPROM_INIT_FLAG) == 0xFF) {
        hasValidParams = true;
        Serial.println(F("[EEPROM] Valid parameters found!"));
        printSavedParameters();
    } else {
        hasValidParams = false;
        sessionMode = MODE_IDLE;
        Serial.println(F("[EEPROM] No saved parameters — waiting for server config"));
    }

    // Start WiFi AP (hotspot for laptop)
    startWiFiAP();
    if (WiFi.status() == WL_CONNECTED) {
        syncTimeFromNtp();
    }

    // Setup test HTTP server endpoints
    setupTestEndpoints();
    testServer.begin();

    // First sensor reads
    readTemperature();
    readHumidity();
    
    Serial.printf("\n[System] Ready!\n");
    Serial.printf("[WiFi] Status: %s\n", (WiFi.status() == WL_CONNECTED) ? "CONNECTED" : "DISCONNECTED");
    Serial.printf("[Server] Base URL: http://%s:%d%s\n", SERVER_IP, SERVER_PORT, SERVER_BASE_PATH);
    if (TEST_MODE) {
        Serial.println(F("[Mode] TEST MODE (manual relay + DHT sync)"));
    } else {
        Serial.printf("[Mode] %s\n", getCurrentModeLabel());
    }
}

// ─────────────────────────────────────────────
//  MAIN LOOP
// ─────────────────────────────────────────────
void loop() {
    // Handle test endpoints (non-blocking)
    testServer.handleClient();
    
    unsigned long now = millis();

    // ────────────────────────────────────────────────────────────
    //  TEST MODE: Always run manual relay + DHT sync loop
    // ────────────────────────────────────────────────────────────
    if (TEST_MODE) {
        if (now - lastSensorRead >= SENSOR_INTERVAL) {
            lastSensorRead = now;
            readTemperature();
            readHumidity();
            Serial.printf("[TEST_DHT] Temp: %0.1f°C, Humidity: %0.1f%%\n", currentTemp, currentHumidity);
        }

        // Post DHT data to server every 5 seconds
        static unsigned long lastTestPost = 0;
        if (now - lastTestPost >= 5000) {
            lastTestPost = now;
            postTestDHTToServer();
        }

        // Poll server for relay commands every 2 seconds
        static unsigned long lastTestPoll = 0;
        if (now - lastTestPoll >= 2000) {
            lastTestPoll = now;
            pollServerForTestCommands();
        }

        if (now - lastStatusPrint >= STATUS_INTERVAL) {
            lastStatusPrint = now;
            printPollingStatus("TEST_POLL");
        }

        delay(100);
        return;
    }

    // ── IDLE MODE: Waiting for parameters from server ──
    if (sessionMode == MODE_IDLE) {
        if (now - lastSensorRead >= SENSOR_INTERVAL) {
            lastSensorRead = now;
            readTemperature();
            readHumidity();
        }

        if (now - lastIdleRelayPoll >= COMMAND_INTERVAL) {
            lastIdleRelayPoll = now;
            pollServerForTestCommands();
            idleRelayTestHold = heaterGroupOn || heaterFanOn || heater1On || heater2On || eggswingOn || exhaustOn;
        }

        // Heater OFF during idle unless relay testing is currently active
        if (!idleRelayTestHold) {
            if (heaterGroupOn) setHeater(false);
            if (heaterFanOn) setHeaterFan(false);
            if (exhaustOn) setExhaust(false);
            exhaustCycleActive = false;
            if (eggswingOn) setSwing(false);
        }
        if (now - lastSettingsFetch >= IDLE_SETTINGS_INTERVAL) {
            lastSettingsFetch = now;
            Serial.println(F("[IDLE] Fetching parameters from server..."));
            fetchParametersFromServer();
        }

        if (!ntpTimeReady && WiFi.status() == WL_CONNECTED) {
            syncTimeFromNtp();
        }

        tryStartScheduledSessionOffline();

        if (now - lastStatusPrint >= STATUS_INTERVAL) {
            lastStatusPrint = now;
            printPollingStatus("IDLE_POLL");
        }

        // Send a quick heartbeat so the dashboard shows Online within STATUS_INTERVAL
        if (now - lastHeartbeatSent >= STATUS_INTERVAL) {
            postDeviceHeartbeat();
        }

        delay(100);
        return;  // Skip everything else, just wait
    }

    // ── SESSION RUNNING: Use saved parameters for offline operation ──
    if (sessionMode == MODE_RUNNING) {
        
        // ────────────────────────────────────────────────────────────
        //  NORMAL MODE: Full automatic system operation
        // ────────────────────────────────────────────────────────────
        
        // Check if session should end (use saved calculated endTime)
        if (sessionEndsAt > 0 && now >= sessionEndsAt) {
            sessionMode = MODE_COMPLETED;
            setHeater(false);
            setSwing(false);
            Serial.println(F("[Session] COMPLETED!"));
            return;
        }

        // ── Read sensors ──────────────────────────
        if (now - lastSensorRead >= SENSOR_INTERVAL) {
            lastSensorRead = now;
            readTemperature();
            readHumidity();
        }

        // ── Heater thermostat control (using saved parameters) ─────────────
        if (tempOK && !heaterManualOverride) {
            // Use savedTargetTemp, savedMinTemp, savedMaxTemp
            if (currentTemp < savedMinTemp) {
                setHeater(true);
            } else if (currentTemp > savedMaxTemp) {
                setHeater(false);
            }
        } else if (!heaterManualOverride && heaterGroupOn) {
            setHeater(false);
        }

        // Keep heater fan running during session to circulate heat
        if (!heaterFanOn) {
            setHeaterFan(true);
        }

        // Exhaust: cycle for air mixing while heating, continuous when temp exceeds max temp
        if (tempOK) {
            const bool overheat = currentTemp > savedMaxTemp;
            const bool isHeating = currentTemp < savedTargetTemp;
            
            if (overheat) {
                if (!heaterFanOn) setHeaterFan(true);
                if (!exhaustOn) setExhaust(true);
                exhaustCycleActive = false;
            } else if (isHeating) {
                // Cycle exhaust on/off (30s on, 30s off) while heating toward target
                if (!exhaustCycleActive && (now - lastExhaustCycleAt >= EXHAUST_CYCLE_INTERVAL_MS)) {
                    setExhaust(true);
                    exhaustCycleActive = true;
                    exhaustCycleStartedAt = now;
                }
                if (exhaustCycleActive && (now - exhaustCycleStartedAt >= EXHAUST_CYCLE_DURATION_MS)) {
                    setExhaust(false);
                    exhaustCycleActive = false;
                    lastExhaustCycleAt = now;  // Mark when we turned OFF to create OFF interval
                }
            } else {
                // Temperature at or above target, turn off exhaust cycling
                if (exhaustOn) setExhaust(false);
                exhaustCycleActive = false;
            }
        }

        // ── Auto-stop swing after configured duration or during lockdown ──
        if (eggswingOn && !swingManualOverride) {
            unsigned long swingDurationMs = (unsigned long)(savedSwingDurationSec > 0 ? savedSwingDurationSec : 30) * 1000UL;
            if (isTurningLockdownActive() || now - swingStartedAt >= swingDurationMs) {
                setSwing(false);
                if (isTurningLockdownActive()) {
                    Serial.printf("[Swing] Auto-stop — lockdown active (%d days remaining)\n", turningLockdownDaysRemaining);
                } else {
                    Serial.println(F("[Swing] Auto-stop — cycle complete"));
                }
            }
        }

        // ── Auto-start swing on schedule (every savedTurningInt hours) ──
        if (!swingManualOverride && !eggswingOn && savedTurningInt > 0.0f && !isTurningLockdownActive()) {
            const unsigned long intervalMs = (unsigned long)(savedTurningInt * 3600.0f * 1000.0f);
            if (lastSwingScheduledAt == 0) {
                lastSwingScheduledAt = now;
            } else if (now - lastSwingScheduledAt >= intervalMs) {
                setSwing(true);
                lastSwingScheduledAt = now;
                Serial.println(F("[Swing] Auto-start — scheduled turn"));
            }
        } else if (isTurningLockdownActive()) {
            lastSwingScheduledAt = now;
        }

        // ── Check turning schedule (every 30 s) ──
        if (now - lastScheduleFetch >= SCHEDULE_INTERVAL) {
            lastScheduleFetch = now;
            // Would normally check schedule, but in offline mode
            // we could run a simple turn schedule based on savedTurningInt
            // For now, just log that we're running
            Serial.printf("[Session] Running: Temp=%0.1f°C (target %0.1f°C)\n", currentTemp, savedTargetTemp);
        }

        // ── Post sensor data to server (every 60 s) ──
        if (now - lastServerPost >= LOG_INTERVAL) {
            lastServerPost = now;
            postSensorDataToServer();
        }

        // ── Fetch updated settings from server (every 30 s) ──
        if (now - lastSettingsFetch >= SETTINGS_INTERVAL) {
            lastSettingsFetch = now;
            fetchParametersFromServer();
        }

        if (now - lastStatusPrint >= STATUS_INTERVAL) {
            lastStatusPrint = now;
            printPollingStatus("ACTUAL_POLL");
        }
        // Heartbeat to mark device as online for dashboard
        if (now - lastHeartbeatSent >= STATUS_INTERVAL) {
            postDeviceHeartbeat();
        }
    }

    // ── SESSION COMPLETED: keep heartbeating so the dashboard stays online ──
    if (sessionMode == MODE_COMPLETED) {
        if (heaterGroupOn) setHeater(false);
        if (heaterFanOn) setHeaterFan(false);
        if (exhaustOn) setExhaust(false);
        exhaustCycleActive = false;
        if (now - lastStatusPrint >= STATUS_INTERVAL) {
            lastStatusPrint = now;
            printPollingStatus("COMPLETED_POLL");
        }

        if (now - lastHeartbeatSent >= STATUS_INTERVAL) {
            postDeviceHeartbeat();
        }

        delay(100);
        return;
    }

    delay(100);
}

// ─────────────────────────────────────────────
//  WIFI SETUP (Connect to Home Network)
// ─────────────────────────────────────────────
void startWiFiAP() {
    // Connect to home network (STA mode)
    WiFi.mode(WIFI_STA);
    Serial.printf("[WiFi] Connecting to: %s\n", WIFI_SSID);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
        delay(500);
        Serial.print(".");
        attempts++;
    }
    
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println(F("\n[WiFi] Connected!"));
        Serial.println(F("╔════════════════════════════════════════════════╗"));
        Serial.println(F("║  CONNECTED TO HOME NETWORK (STA MODE)         ║"));
        Serial.println(F("╠════════════════════════════════════════════════╣"));
        Serial.printf("   ║  SSID: %s%-33s ║\n", WIFI_SSID, "");
        Serial.printf("   ║  IP:   %s%-28s ║\n", WiFi.localIP().toString().c_str(), "");
        Serial.println(F("║                                                ║"));
        Serial.println(F("║  Server: http://[SERVER_IP]/GHOST              ║"));
        Serial.println(F("║  Test:   http://[SERVER_IP]/api/test/status    ║"));
        Serial.println(F("║                                                ║"));
        Serial.println(F("╚════════════════════════════════════════════════╝\n"));
    } else {
        Serial.println(F("\n[WiFi] FAILED to connect - will retry"));
         
    }
    
     if (ENABLE_AP_MODE) {
        WiFi.softAP(AP_SSID, AP_PASSWORD);
        Serial.printf("[AP] Also running AP mode: %s\n", AP_SSID);
    }
}



// ─────────────────────────────────────────────
//  TEST ENDPOINTS SETUP (For test.php)
// ─────────────────────────────────────────────
void setupTestEndpoints() {
     // Health status endpoint — includes WiFi, IP, relay states, and server info
     testServer.on("/api/health", HTTP_GET, []() {
        StaticJsonDocument<768> doc;
        doc["device_id"] = INCUBATOR_ID;
        doc["device_ip"] = WiFi.localIP().toString();
        doc["wifi_ssid"] = WIFI_SSID;
        doc["wifi_signal"] = WiFi.RSSI();
        doc["wifi_connected"] = (WiFi.status() == WL_CONNECTED) ? true : false;
        
        // Sensor data
        doc["temp"] = currentTemp;
        doc["humidity"] = currentHumidity;
        
        // Relay status with pin levels
        doc["relays"]["heater_group"]["state"] = heaterGroupOn;
        doc["relays"]["heater_group"]["pin_level"] = heaterGroupOn ? "HIGH" : "LOW";
        
        doc["relays"]["heater_fan"]["state"] = heaterFanOn;
        doc["relays"]["heater_fan"]["pin"] = "D7";
        doc["relays"]["heater_fan"]["pin_level"] = heaterFanOn ? "HIGH" : "LOW";
        
        doc["relays"]["heater_1"]["state"] = heater1On;
        doc["relays"]["heater_1"]["pin"] = "D5";
        doc["relays"]["heater_1"]["pin_level"] = heater1On ? "HIGH" : "LOW";
        
        doc["relays"]["heater_2"]["state"] = heater2On;
        doc["relays"]["heater_2"]["pin"] = "D6";
        doc["relays"]["heater_2"]["pin_level"] = heater2On ? "HIGH" : "LOW";
        
        doc["relays"]["eggswing"]["state"] = eggswingOn;
        doc["relays"]["eggswing"]["pin"] = "D1";
        doc["relays"]["eggswing"]["pin_level"] = eggswingOn ? "HIGH" : "LOW";
        
        doc["relays"]["exhaust"]["state"] = exhaustOn;
        doc["relays"]["exhaust"]["pin"] = "D0";
        doc["relays"]["exhaust"]["pin_level"] = exhaustOn ? "HIGH" : "LOW";
        
        // Mode and session
        doc["mode"] = getCurrentModeLabel();
        doc["session_id"] = sessionId;
        doc["session_name"] = sessionName;
        
        // Server endpoints
        doc["server_ip"] = SERVER_IP;
        doc["server_port"] = SERVER_PORT;
        doc["server_base_path"] = SERVER_BASE_PATH;
        
        // Timestamps
        doc["uptime_ms"] = millis();
        doc["timestamp"] = (unsigned long)(millis() / 1000);

        String response;
        serializeJson(doc, response);
        testServer.send(200, "application/json", response);
    });

     testServer.on("/api/test/status", HTTP_GET, []() {
        StaticJsonDocument<384> doc;
        doc["temp"] = currentTemp;
        doc["humidity"] = currentHumidity;
        // Individual relay states
        doc["heater_group"] = heaterGroupOn;
        doc["heater_fan"] = heaterFanOn;
        doc["heater_1"] = heater1On;
        doc["heater_2"] = heater2On;
        doc["eggswing"] = eggswingOn;
        doc["exhaust"] = exhaustOn;
        doc["mode"] = (sessionMode == MODE_IDLE) ? "IDLE" : 
                      (sessionMode == MODE_RUNNING) ? "RUNNING" : "COMPLETED";
        doc["session_id"] = sessionId;

        String response;
        serializeJson(doc, response);
        testServer.send(200, "application/json", response);
    });

     testServer.on("/api/test/relay", HTTP_POST, []() {
        if (!testServer.hasArg("relay") || !testServer.hasArg("state")) {
            testServer.send(400, "application/json", "{\"success\":false,\"error\":\"Missing parameters\"}");
            return;
        }
        
        String relay = testServer.arg("relay");
        bool state = testServer.arg("state").toInt() != 0;
        
        // Control relay based on name
        if (relay == "heater") {
            // group: all heater relays
            setHeater(state);
            Serial.printf("[TEST] Heater group set to %s\n", state ? "ON" : "OFF");
            testServer.send(200, "application/json", "{\"success\":true,\"relay\":\"heater\"}");
        } else if (relay == "heater_fan") {
            heaterFanOn = state;
            writeHeaterFan(state);
            Serial.printf("[TEST] Heater fan set to %s\n", state ? "ON" : "OFF");
            testServer.send(200, "application/json", "{\"success\":true,\"relay\":\"heater_fan\"}");
        } else if (relay == "heater_1") {
            heater1On = state;
            writeHeater1(state);
            Serial.printf("[TEST] Heater 1 set to %s\n", state ? "ON" : "OFF");
            testServer.send(200, "application/json", "{\"success\":true,\"relay\":\"heater_1\"}");
        } else if (relay == "heater_2") {
            heater2On = state;
            writeHeater2(state);
            Serial.printf("[TEST] Heater 2 set to %s\n", state ? "ON" : "OFF");
            testServer.send(200, "application/json", "{\"success\":true,\"relay\":\"heater_2\"}");
        } else if (relay == "eggswing") {
            if (state && isTurningLockdownActive()) {
                testServer.send(200, "application/json", "{\"success\":false,\"error\":\"Egg turning disabled during lockdown\"}");
            } else {
                setSwing(state);
                Serial.printf("[TEST] Egg swing set to %s\n", state ? "ON" : "OFF");
                testServer.send(200, "application/json", "{\"success\":true,\"relay\":\"eggswing\"}");
            }
        } else if (relay == "exhaust") {
            setExhaust(state);
            Serial.printf("[TEST] Exhaust set to %s\n", state ? "ON" : "OFF");
            testServer.send(200, "application/json", "{\"success\":true,\"relay\":\"exhaust\"}");
        } else {
            testServer.send(400, "application/json", "{\"success\":false,\"error\":\"Unknown relay\"}");
        }
    });

    // 404 handler for test server
    testServer.onNotFound([]() {
        testServer.send(404, "application/json", "{\"error\":\"Test endpoint not found\"}");
    });
    
    Serial.println(F("[Setup] Test endpoints registered on /api/test/*"));
}
// ─────────────────────────────────────────────
//  TEST MODE: Poll server for relay commands
// ─────────────────────────────────────────────
void pollServerForTestCommands() {
    String url = "http://";
    url += SERVER_IP;
    url += ":";
    url += SERVER_PORT;
    url += SERVER_BASE_PATH;
    url += "/ajax/hardware_api.php?action=test_mode_get_relay&incubator_id=";
    url += INCUBATOR_ID;
    
    httpClient.begin(wifiClient, url);
    int httpCode = httpClient.GET();
    
    if (httpCode == HTTP_CODE_OK) {
        String payload = httpClient.getString();
        StaticJsonDocument<256> doc;
        auto err = deserializeJson(doc, payload);
        if (!err && doc["success"] == true && doc.containsKey("relay") && doc.containsKey("state")) {
            String relay = doc["relay"];
            bool state = doc["state"];

            if (relay == "heater") {
                setHeater(state);
                Serial.printf("[TEST CMD] Heater %s\n", state ? "ON" : "OFF");
            } else if (relay == "heater_fan") {
                heaterFanOn = state;
                writeHeaterFan(state);
                Serial.printf("[TEST CMD] Heater fan %s\n", state ? "ON" : "OFF");
            } else if (relay == "heater_1") {
                heater1On = state;
                writeHeater1(state);
                Serial.printf("[TEST CMD] Heater 1 %s\n", state ? "ON" : "OFF");
            } else if (relay == "heater_2") {
                heater2On = state;
                writeHeater2(state);
                Serial.printf("[TEST CMD] Heater 2 %s\n", state ? "ON" : "OFF");
            } else if (relay == "eggswing") {
                if (state && isTurningLockdownActive()) {
                    Serial.println(F("[TEST CMD] Egg turning disabled during lockdown"));
                } else {
                    setSwing(state);
                    Serial.printf("[TEST CMD] Egg swing %s\n", state ? "ON" : "OFF");
                }
            } else if (relay == "exhaust") {
                setExhaust(state);
                Serial.printf("[TEST CMD] Exhaust %s\n", state ? "ON" : "OFF");
            }

            printRelayStates("TEST_CMD");
            postTestDHTToServer();
        } else if (!err && doc.containsKey("error")) {
            Serial.printf("[TEST CMD] API error: %s\n", ((const char*)doc["error"]));
                idleRelayTestHold = false;
            } else if (!err) {
                idleRelayTestHold = false;
        }
    } else {
        Serial.printf("[TEST CMD] Poll failed: HTTP %d\n", httpCode);
            idleRelayTestHold = false;
    }
    
    httpClient.end();
}

// ─────────────────────────────────────────────
//  TEST MODE: POST DHT data to server
// ─────────────────────────────────────────────
void postTestDHTToServer() {
    String url = "http://";
    url += SERVER_IP;
    url += ":";
    url += SERVER_PORT;
    url += SERVER_BASE_PATH;
    url += "/ajax/hardware_api.php";
    
    // Build form data: temp + humidity + relay status + WiFi info
    String postData = "action=test_mode_set_dht&incubator_id=" + String(INCUBATOR_ID);
    postData += "&temp=" + String(currentTemp, 2);
    postData += "&humidity=" + String(currentHumidity, 2);
    postData += "&heater_on=" + String(heaterGroupOn ? 1 : 0);
    postData += "&heater_fan=" + String(heaterFanOn ? 1 : 0);
    postData += "&heater_1=" + String(heater1On ? 1 : 0);
    postData += "&heater_2=" + String(heater2On ? 1 : 0);
    postData += "&swing_on=" + String(eggswingOn ? 1 : 0);
    postData += "&exhaust=" + String(exhaustOn ? 1 : 0);
    // WiFi and device info
    postData += "&wifi_connected=" + String((WiFi.status() == WL_CONNECTED) ? 1 : 0);
    postData += "&device_ip=" + WiFi.localIP().toString();
    postData += "&wifi_ssid=" + String(WIFI_SSID);
    
    httpClient.begin(wifiClient, url);
    httpClient.addHeader("Content-Type", "application/x-www-form-urlencoded");
    int httpCode = httpClient.POST(postData);
    
    if (httpCode > 0) {
        Serial.printf("[TEST_DHT] Posted: T=%.1f°C, H=%.1f%%, Relays: H1=%d H2=%d Fan=%d Swing=%d Exhaust=%d (HTTP %d)\n", 
            currentTemp, currentHumidity, 
            heater1On, heater2On, heaterFanOn, eggswingOn, exhaustOn,
            httpCode);
    } else {
        Serial.printf("[TEST_DHT] Post failed: %s\n", httpClient.errorToString(httpCode).c_str());
    }
    
    httpClient.end();
}

// ─────────────────────────────────────────────
//  POST SENSOR DATA TO SERVER (LAPTOP)
// ─────────────────────────────────────────────
void postSensorDataToServer() {
    // Post form-encoded sensor + relay state to server hardware API
    String url = "http://";
    url += SERVER_IP;
    url += ":";
    url += SERVER_PORT;
    url += SERVER_BASE_PATH;
    url += "/ajax/hardware_api.php";

    String postData = "action=log_sensor";
    postData += "&token=ghost_hw_secret_2024";
    postData += "&incubator_id=" + String(INCUBATOR_ID);
    postData += "&temperature=" + String(currentTemp, 2);
    postData += "&humidity=" + String(currentHumidity, 2);
    postData += "&heater_on=" + String(heaterGroupOn ? 1 : 0);
    postData += "&heater_fan=" + String(heaterFanOn ? 1 : 0);
    postData += "&heater_1=" + String(heater1On ? 1 : 0);
    postData += "&heater_2=" + String(heater2On ? 1 : 0);
    postData += "&swing_on=" + String(eggswingOn ? 1 : 0);
    postData += "&exhaust=" + String(exhaustOn ? 1 : 0);
    postData += "&wifi_connected=" + String((WiFi.status() == WL_CONNECTED) ? 1 : 0);

    httpClient.begin(wifiClient, url);
    httpClient.addHeader("Content-Type", "application/x-www-form-urlencoded");
    int httpCode = httpClient.POST(postData);

    if (httpCode > 0) {
        Serial.printf("[Server] POST response: %d\n", httpCode);
        if (httpCode == HTTP_CODE_OK || httpCode == HTTP_CODE_CREATED) {
            Serial.println(F("[Server] Data sent successfully"));
        } else {
            Serial.printf("[Server] Unexpected response code: %d\n", httpCode);
        }
    } else {
        Serial.printf("[Server] POST failed: %s\n", httpClient.errorToString(httpCode).c_str());
    }

    httpClient.end();
}

// Send a lightweight heartbeat so the server marks device as online
void postDeviceHeartbeat() {
    String url = "http://";
    url += SERVER_IP;
    url += ":";
    url += SERVER_PORT;
    url += SERVER_BASE_PATH;
    url += "/ajax/hardware_api.php";

    String postData = "action=device_status";
    postData += "&token=ghost_hw_secret_2024";
    postData += "&incubator_id=" + String(INCUBATOR_ID);
    postData += "&temperature=" + String(currentTemp, 2);
    postData += "&humidity=" + String(currentHumidity, 2);
    postData += "&heater=" + String(heaterGroupOn ? 1 : 0);
    postData += "&heater_1=" + String(heater1On ? 1 : 0);
    postData += "&heater_2=" + String(heater2On ? 1 : 0);
    postData += "&fan=" + String(heaterFanOn ? 1 : 0);
    postData += "&swing=" + String(eggswingOn ? 1 : 0);
    postData += "&exhaust=" + String(exhaustOn ? 1 : 0);
    postData += "&wifi_connected=" + String((WiFi.status() == WL_CONNECTED) ? 1 : 0);
    postData += "&current_mode=" + String(sessionMode == MODE_RUNNING ? "incubating" : (sessionMode == MODE_COMPLETED ? "completed" : "idle"));
    postData += "&active_session_name=" + String(sessionName);
    postData += "&session_id=" + String(sessionId);

    httpClient.begin(wifiClient, url);
    httpClient.addHeader("Content-Type", "application/x-www-form-urlencoded");
    int httpCode = httpClient.POST(postData);

    if (httpCode > 0) {
        Serial.printf("[Heartbeat] POST response: %d\n", httpCode);
    } else {
        Serial.printf("[Heartbeat] POST failed: %s\n", httpClient.errorToString(httpCode).c_str());
    }
    httpClient.end();
    lastHeartbeatSent = millis();
}

// ─────────────────────────────────────────────
//  FETCH PARAMETERS FROM SERVER
// ─────────────────────────────────────────────
void fetchParametersFromServer() {
    // POST to server hardware API to request settings
    String url = "http://";
    url += SERVER_IP;
    url += ":";
    url += SERVER_PORT;
    url += SERVER_BASE_PATH;
    url += "/ajax/hardware_api.php";

    Serial.printf("[Server] POST %s\n", url.c_str());

    String postData = "action=get_device_config";
    postData += "&token=ghost_hw_secret_2024";
    postData += "&incubator_id=" + String(INCUBATOR_ID);

    httpClient.begin(wifiClient, url);
    httpClient.addHeader("Content-Type", "application/x-www-form-urlencoded");
    int httpCode = httpClient.POST(postData);

    if (httpCode == HTTP_CODE_OK) {
        String payload = httpClient.getString();
        StaticJsonDocument<512> doc;
        auto err = deserializeJson(doc, payload);

        if (!err) {
            float newTargetTemp = savedTargetTemp;
            float newMinTemp = savedMinTemp;
            float newMaxTemp = savedMaxTemp;
            float newTargetHum = savedTargetHum;
            float newMinHum = savedMinHum;
            float newMaxHum = savedMaxHum;
            float newTurningInt = savedTurningInt;
            int newSwingDurationSec = savedSwingDurationSec;
            bool newTurningLockdownActive = turningLockdownActive;
            int newTurningLockdownDaysRemaining = turningLockdownDaysRemaining;
            int newSessionId = sessionId;
            SessionMode newSessionMode = sessionMode;
            char newSessionName[sizeof(sessionName)] = {0};
            int newNextSessionId = nextSessionId;
            char newNextSessionName[sizeof(nextSessionName)] = {0};
            bool settingsChanged = false;

            if (doc.containsKey("target_temp")) newTargetTemp = doc["target_temp"];
            if (doc.containsKey("min_temp")) newMinTemp = doc["min_temp"];
            if (doc.containsKey("max_temp")) newMaxTemp = doc["max_temp"];
            if (doc.containsKey("target_humidity")) newTargetHum = doc["target_humidity"];
            else if (doc.containsKey("target_hum")) newTargetHum = doc["target_hum"];
            if (doc.containsKey("min_humidity")) newMinHum = doc["min_humidity"];
            else if (doc.containsKey("min_hum")) newMinHum = doc["min_hum"];
            if (doc.containsKey("max_humidity")) newMaxHum = doc["max_humidity"];
            else if (doc.containsKey("max_hum")) newMaxHum = doc["max_hum"];
            if (doc.containsKey("turning_interval")) newTurningInt = doc["turning_interval"].as<float>();
            if (doc.containsKey("turning_lockdown_active")) newTurningLockdownActive = doc["turning_lockdown_active"];
            if (doc.containsKey("turning_lockdown_days_remaining")) newTurningLockdownDaysRemaining = doc["turning_lockdown_days_remaining"];
            if (doc.containsKey("swing_duration_sec")) {
                int duration = doc["swing_duration_sec"];
                if (duration >= 5 && duration <= 300) {
                    newSwingDurationSec = duration;
                }
            }
            const char* activeSessionName = "";
            if (doc.containsKey("active_session_name") && !doc["active_session_name"].isNull()) {
                activeSessionName = doc["active_session_name"];
            } else if (doc.containsKey("session_name") && !doc["session_name"].isNull()) {
                activeSessionName = doc["session_name"];
            }
            if (activeSessionName && activeSessionName[0] != '\0') {
                strncpy(newSessionName, activeSessionName, sizeof(newSessionName) - 1);
                newSessionName[sizeof(newSessionName) - 1] = '\0';
            }
            if (doc.containsKey("session_id")) newSessionId = doc["session_id"];
            if (doc.containsKey("next_session_id")) newNextSessionId = doc["next_session_id"];
            if (doc.containsKey("next_session_start_epoch") && !doc["next_session_start_epoch"].isNull()) {
                nextSessionStartEpoch = (time_t)doc["next_session_start_epoch"].as<long>();
            } else if (doc.containsKey("next_session_start_at") && !doc["next_session_start_at"].isNull()) {
                nextSessionStartEpoch = 0;
            }
            const char* nextSessionNameValue = "";
            if (doc.containsKey("next_session_name") && !doc["next_session_name"].isNull()) {
                nextSessionNameValue = doc["next_session_name"];
            }
            if (nextSessionNameValue && nextSessionNameValue[0] != '\0') {
                strncpy(newNextSessionName, nextSessionNameValue, sizeof(newNextSessionName) - 1);
                newNextSessionName[sizeof(newNextSessionName) - 1] = '\0';
            }

            bool hasSessionStatus = doc.containsKey("session_status");
            const char* sessionStatus = doc["session_status"] | "";
            Serial.printf("[Server] session_status=%s session_name=%s session_id=%d next_session_name=%s next_session_id=%d scheduled_batches=%d\n",
                hasSessionStatus ? sessionStatus : "(unchanged)",
                activeSessionName,
                doc["session_id"] | 0,
                nextSessionNameValue,
                doc["next_session_id"] | 0,
                doc["scheduled_batch_count"] | 0);

            if (hasSessionStatus && strcmp(sessionStatus, "running") == 0) {
                if (newSessionMode != MODE_RUNNING) {
                    newSessionMode = MODE_RUNNING;
                    settingsChanged = true;
                }
            } else if (hasSessionStatus && strcmp(sessionStatus, "completed") == 0) {
                if (newSessionMode != MODE_COMPLETED) {
                    newSessionMode = MODE_COMPLETED;
                    settingsChanged = true;
                }
            } else if (hasSessionStatus && strcmp(sessionStatus, "idle") == 0) {
                if (newSessionMode != MODE_IDLE) {
                    newSessionMode = MODE_IDLE;
                    settingsChanged = true;
                }
                if (newSessionId != 0 || newSessionName[0] != '\0') {
                    newSessionId = 0;
                    newSessionName[0] = '\0';
                    settingsChanged = true;
                }
                if (newNextSessionId != 0 || newNextSessionName[0] != '\0') {
                    // Keep next-session values so idle mode can pre-load the upcoming batch.
                }
            }

            if (fabsf(savedTargetTemp - newTargetTemp) > 0.001f ||
                fabsf(savedMinTemp - newMinTemp) > 0.001f ||
                fabsf(savedMaxTemp - newMaxTemp) > 0.001f ||
                fabsf(savedTargetHum - newTargetHum) > 0.001f ||
                fabsf(savedMinHum - newMinHum) > 0.001f ||
                fabsf(savedMaxHum - newMaxHum) > 0.001f ||
                fabsf(savedTurningInt - newTurningInt) > 0.001f ||
                savedSwingDurationSec != newSwingDurationSec ||
                turningLockdownActive != newTurningLockdownActive ||
                turningLockdownDaysRemaining != newTurningLockdownDaysRemaining ||
                sessionId != newSessionId ||
                strcmp(sessionName, newSessionName) != 0 ||
                nextSessionId != newNextSessionId ||
                strcmp(nextSessionName, newNextSessionName) != 0 ||
                sessionMode != newSessionMode) {
                settingsChanged = true;
            }

            savedTargetTemp = newTargetTemp;
            savedMinTemp = newMinTemp;
            savedMaxTemp = newMaxTemp;
            savedTargetHum = newTargetHum;
            savedMinHum = newMinHum;
            savedMaxHum = newMaxHum;
            savedTurningInt = newTurningInt;
            savedSwingDurationSec = newSwingDurationSec;
            turningLockdownActive = newTurningLockdownActive;
            turningLockdownDaysRemaining = newTurningLockdownDaysRemaining;
            sessionMode = newSessionMode;
            sessionId = newSessionId;
            strncpy(sessionName, newSessionName, sizeof(sessionName) - 1);
            sessionName[sizeof(sessionName) - 1] = '\0';
            nextSessionId = newNextSessionId;
            strncpy(nextSessionName, newNextSessionName, sizeof(nextSessionName) - 1);
            nextSessionName[sizeof(nextSessionName) - 1] = '\0';

            if (sessionMode == MODE_RUNNING) {
                sessionStartedAt = sessionStartedAt == 0 ? millis() : sessionStartedAt;
                setHeater(true);
                Serial.println(F("[Mode] Switched to INCUBATING (session active)"));
            } else if (sessionMode == MODE_COMPLETED) {
                setHeater(false);
                setSwing(false);
                Serial.println(F("[Mode] Switched to COMPLETED (server session state)"));
            } else if (sessionMode == MODE_IDLE) {
                sessionStartedAt = 0;
                sessionEndsAt = 0;
                setHeater(false);
                setSwing(false);
                setHeaterFan(false);
                setExhaust(false);
                Serial.println(F("[Mode] Switched to IDLE (no active session)"));
            }

            if (settingsChanged) {
                saveParametersToEEPROM();
                Serial.println(F("[Server] Parameters fetched and saved!"));
            } else {
                Serial.println(F("[Server] Parameters unchanged"));
            }
            hasValidParams = true;
        } else {
            Serial.println(F("[Server] JSON parse error"));
        }
    } else {
        Serial.printf("[Server] POST failed: %d (%s)\n", httpCode, httpClient.errorToString(httpCode).c_str());
    }

    httpClient.end();
}

// ─────────────────────────────────────────────
//  EEPROM FUNCTIONS
// ─────────────────────────────────────────────
void saveParametersToEEPROM() {
    // Write floats (4 bytes each)
    EEPROM.put(EEPROM_TARGET_TEMP, savedTargetTemp);
    EEPROM.put(EEPROM_MIN_TEMP, savedMinTemp);
    EEPROM.put(EEPROM_MAX_TEMP, savedMaxTemp);
    EEPROM.put(EEPROM_TARGET_HUM, savedTargetHum);
    EEPROM.put(EEPROM_MIN_HUM, savedMinHum);
    EEPROM.put(EEPROM_MAX_HUM, savedMaxHum);
    
    // Write int
    EEPROM.put(EEPROM_TURNING_INT, savedTurningInt);
    EEPROM.put(EEPROM_SWING_SEC, savedSwingDurationSec);
    
    // Write session name
    for (int i = 0; i < 50; i++) {
        EEPROM.write(EEPROM_SESSION_NAME + i, sessionName[i]);
        if (sessionName[i] == '\0') break;
    }
    
    // Write session ID
    EEPROM.put(EEPROM_SESSION_ID, sessionId);

    // Persist mode so restart can continue safely if server is temporarily down
    EEPROM.write(EEPROM_SESSION_MODE, (uint8_t)sessionMode);
    
    // Mark as initialized
    EEPROM.write(EEPROM_INIT_FLAG, 0xFF);
    
    EEPROM.commit();
    Serial.println(F("[EEPROM] Parameters saved!"));
}

void loadParametersFromEEPROM() {
    if (EEPROM.read(EEPROM_INIT_FLAG) != 0xFF) {
        Serial.println(F("[EEPROM] Not initialized yet"));
        return;
    }
    
    EEPROM.get(EEPROM_TARGET_TEMP, savedTargetTemp);
    EEPROM.get(EEPROM_MIN_TEMP, savedMinTemp);
    EEPROM.get(EEPROM_MAX_TEMP, savedMaxTemp);
    EEPROM.get(EEPROM_TARGET_HUM, savedTargetHum);
    EEPROM.get(EEPROM_MIN_HUM, savedMinHum);
    EEPROM.get(EEPROM_MAX_HUM, savedMaxHum);
    EEPROM.get(EEPROM_TURNING_INT, savedTurningInt);
    EEPROM.get(EEPROM_SWING_SEC, savedSwingDurationSec);
    
    // Load session name
    char name[50] = {0};
    for (int i = 0; i < 50; i++) {
        name[i] = EEPROM.read(EEPROM_SESSION_NAME + i);
        if (name[i] == '\0') break;
    }
    strncpy(sessionName, name, 49);
    
    EEPROM.get(EEPROM_SESSION_ID, sessionId);

    uint8_t storedMode = EEPROM.read(EEPROM_SESSION_MODE);
    if (storedMode <= MODE_COMPLETED) {
        sessionMode = (SessionMode)storedMode;
    }

    if (savedSwingDurationSec < 5 || savedSwingDurationSec > 300) {
        savedSwingDurationSec = 30;
    }

    if (sessionMode == MODE_RUNNING && sessionId == 0 && sessionName[0] == '\0') {
        sessionMode = MODE_IDLE;
    }
}

void printSavedParameters() {
    Serial.printf("[Parameters] Target:%.1f°C (%.1f-%.1f)\n", savedTargetTemp, savedMinTemp, savedMaxTemp);
    Serial.printf("[Parameters] Humidity:%.1f%% (%.1f-%.1f)\n", savedTargetHum, savedMinHum, savedMaxHum);
            Serial.printf("[Parameters] Turning interval: %.2f hours\n", savedTurningInt);
    Serial.printf("[Parameters] Swing duration: %d sec\n", savedSwingDurationSec);
    Serial.printf("[Parameters] Session: %s (ID:%d)\n", sessionName, sessionId);
}

// ─────────────────────────────────────────────
//  READ TEMPERATURE  (DHT22)
// ─────────────────────────────────────────────
void readTemperature() {
    float t = dht.readTemperature();

    if (isnan(t)) {
        tempOK = false;
        Serial.println(F("[DHT22] Temperature read failed"));
        return;
    }

    currentTemp = t;
    tempOK      = true;
    Serial.printf("[DHT22] Temperature: %.2f°C\n", currentTemp);
}

// ─────────────────────────────────────────────
//  READ HUMIDITY  (DHT22)
// ─────────────────────────────────────────────
void readHumidity() {
    float h = dht.readHumidity();
    if (isnan(h)) {
        humOK = false;
        Serial.println(F("[DHT22] Humidity read failed"));
        return;
    }
    currentHumidity = h;
    humOK           = true;
    Serial.printf("[DHT22]  Humidity:    %.2f%%\n", currentHumidity);
}

void controlHeater() {
    if (currentTemp < savedMinTemp && !heaterGroupOn) {
        setHeater(true);
        Serial.printf("[Heater] ON  — %.2f°C < %.2f°C\n", currentTemp, savedMinTemp);
    } else if (currentTemp >= savedMaxTemp && heaterGroupOn) {
        setHeater(false);
        Serial.printf("[Heater] OFF — %.2f°C >= %.2f°C\n", currentTemp, savedMaxTemp);
    }
}

bool isTurningLockdownActive() {
    return turningLockdownActive;
}

// ─────────────────────────────────────────────
//  RELAY SETTERS
// ─────────────────────────────────────────────
void setHeater(bool on) {
    heaterGroupOn = on;
    heaterFanOn = on;
    heater1On = on;
    heater2On = on;
    writeHeaterFan(on);
    writeHeater1(on);
    writeHeater2(on);
}

void setHeaterFan(bool on) {
    heaterFanOn = on;
    writeHeaterFan(on);
}

void setSwing(bool on) {
    eggswingOn = on;
    if (on) swingStartedAt = millis();
    writeEggSwing(on);
}

void setExhaust(bool on) {
    exhaustOn = on;
    writeExhaust(on);
}

//testmode
