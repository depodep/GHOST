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
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
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
const char* SERVER_IP     = "10.133.7.46";
const int   SERVER_PORT   = 80;               
const char* SERVER_BASE_PATH = "/GHOST";       
const int   INCUBATOR_ID  = 1;                 
// ─────────────────────────────────────────────
// Optional local AP for debugging
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
const unsigned long BOOT_ALL_MODULES_MS  = 5000;

// Compile-time option: allow resuming INCUBATING from EEPROM if server unreachable on boot
#ifndef RESUME_ON_BOOT_IF_OFFLINE
#define RESUME_ON_BOOT_IF_OFFLINE 1  // 1 = resume from EEPROM if offline; 0 = force IDLE until server confirms
#endif

// ─────────────────────────────────────────────
//  LCD I2C DISPLAY
// ─────────────────────────────────────────────
#define LCD_SDA D3
#define LCD_SCL D4
#define LCD_I2C_ADDR 0x27
#define LCD_COLS 16
#define LCD_ROWS 2
#define LCD_SCROLL_INTERVAL_MS 250UL
#define LCD_PHASE_INTERVAL_MS 2500UL

LiquidCrystal_I2C lcd(LCD_I2C_ADDR, LCD_COLS, LCD_ROWS);
bool lcdReady = false;
unsigned long lcdTransientUntil = 0;
String lcdTransientLine1;
String lcdTransientLine2;
unsigned long lcdLastRunningPhaseTick = 0;
uint8_t lcdRunningPhase = 0;
// LCD display functions moved below session-state declarations

// ─────────────────────────────────────────────
//  EEPROM ADDRESSES FOR LOCAL STORAGE
// ─────────────────────────────────────────────
// Max EEPROM on ESP8266: 4096 bytes
#define EEPROM_SIZE         4096
#define EEPROM_TARGET_TEMP  0     
#define EEPROM_MIN_TEMP     4   
#define EEPROM_MAX_TEMP     8       
#define EEPROM_TARGET_HUM   12     
#define EEPROM_MIN_HUM      16    
#define EEPROM_MAX_HUM      20     
#define EEPROM_TURNING_INT  24 
#define EEPROM_SESSION_NAME 28  
#define EEPROM_SESSION_ID   78      
#define EEPROM_INIT_FLAG    82  
#define EEPROM_SWING_SEC    83  
#define EEPROM_SESSION_MODE 87     

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
unsigned long lastSwingExecutedAt = 0;
unsigned long lastStatusPrint   = 0;
unsigned long lastHeartbeatSent = 0;
unsigned long lastExhaustCycleAt = 0;
unsigned long exhaustCycleStartedAt = 0;
bool exhaustCycleActive = false;
unsigned long lastIdleTestCommand = 0;
bool bootNeedsServerConfirm = false;

// --- LCD display functions (moved here so session-state globals exist) ---
String lcdPad(const String& text) {
    String value = text;
    if (value.length() > LCD_COLS) {
        return value.substring(0, LCD_COLS);
    }
    while (value.length() < LCD_COLS) {
        value += ' ';
    }
    return value;
}

String lcdScroll(const String& text, unsigned long nowMs) {
    String padded = text + "    ";
    if (padded.length() <= LCD_COLS) {
        return lcdPad(padded);
    }

    unsigned long step = nowMs / LCD_SCROLL_INTERVAL_MS;
    unsigned long offset = step % padded.length();
    String window = padded.substring(offset) + padded.substring(0, offset);
    return window.substring(0, LCD_COLS);
}

void lcdWriteRow(uint8_t row, const String& text) {
    if (!lcdReady) {
        return;
    }
    lcd.setCursor(0, row);
    lcd.print(lcdPad(text));
}

String lcdCompactDuration(unsigned long totalSeconds) {
    if (totalSeconds >= 86400UL) {
        unsigned long days = totalSeconds / 86400UL;
        unsigned long hours = (totalSeconds % 86400UL) / 3600UL;
        char buffer[20];
        snprintf(buffer, sizeof(buffer), "%lud %luh", days, hours);
        return String(buffer);
    }
    if (totalSeconds >= 3600UL) {
        unsigned long hours = totalSeconds / 3600UL;
        unsigned long minutes = (totalSeconds % 3600UL) / 60UL;
        char buffer[20];
        snprintf(buffer, sizeof(buffer), "%luh %lum", hours, minutes);
        return String(buffer);
    }
    if (totalSeconds >= 60UL) {
        unsigned long minutes = totalSeconds / 60UL;
        unsigned long seconds = totalSeconds % 60UL;
        char buffer[20];
        snprintf(buffer, sizeof(buffer), "%lum %lus", minutes, seconds);
        return String(buffer);
    }
    char buffer[20];
    snprintf(buffer, sizeof(buffer), "%lus", totalSeconds);
    return String(buffer);
}

String lcdDateTimeLabel() {
    if (!systemTimeLooksValid()) {
        return String("Uptime ") + String(millis() / 1000UL) + String("s");
    }

    time_t nowEpoch = time(nullptr);
    struct tm timeInfo;
    localtime_r(&nowEpoch, &timeInfo);
    char buffer[24];
    snprintf(buffer, sizeof(buffer), "%02d/%02d %02d:%02d", timeInfo.tm_mday, timeInfo.tm_mon + 1, timeInfo.tm_hour, timeInfo.tm_min);
    return String(buffer);
}

String lcdSessionDurationLabel() {
    if (sessionMode != MODE_RUNNING || sessionEndsAt == 0) {
        return String("21d");
    }

    unsigned long nowMs = millis();
    unsigned long remainingMs = (sessionEndsAt > nowMs) ? (sessionEndsAt - nowMs) : 0;
    return lcdCompactDuration(remainingMs / 1000UL);
}

String lcdNextSwingLabel() {
    if (savedTurningInt <= 0.0f || isTurningLockdownActive()) {
        return String("paused");
    }

    unsigned long intervalMs = (unsigned long)(savedTurningInt * 3600.0f * 1000.0f);
    if (intervalMs == 0) {
        return String("paused");
    }

    unsigned long nowMs = millis();
    if (lastSwingScheduledAt == 0) {
        return lcdCompactDuration(intervalMs / 1000UL);
    }

    unsigned long elapsedMs = nowMs - lastSwingScheduledAt;
    unsigned long remainingMs = (elapsedMs >= intervalMs) ? 0 : (intervalMs - elapsedMs);
    return lcdCompactDuration(remainingMs / 1000UL);
}

void lcdShowTransient(const String& line1, const String& line2, unsigned long durationMs) {
    if (!lcdReady) {
        return;
    }

    lcdTransientLine1 = line1;
    lcdTransientLine2 = line2;
    lcdTransientUntil = millis() + durationMs;
    lcdWriteRow(0, lcdScroll(lcdTransientLine1, millis()));
    lcdWriteRow(1, lcdScroll(lcdTransientLine2, millis()));
}

void lcdRenderBootScreen(uint8_t remainingSeconds) {
    lcdWriteRow(0, lcdScroll(String("Booting... Turning on all relays"), millis()));
    lcdWriteRow(1, lcdScroll(String("Relay ON ") + String(remainingSeconds) + String(".."), millis()));
}

void lcdRenderWifiScreen(bool connected, int attempts) {
    lcdWriteRow(0, lcdScroll(String("Connecting WiFi"), millis()));
    if (connected) {
        lcdWriteRow(1, lcdScroll(String("IP: ") + WiFi.localIP().toString(), millis()));
    } else {
        lcdWriteRow(1, lcdScroll(String("Try ") + String(attempts) + String("/20"), millis()));
    }
}

void lcdRenderReadyScreen() {
    lcdWriteRow(0, lcdScroll(String("System ready"), millis()));
    lcdWriteRow(1, lcdScroll(String("Starting control loop"), millis()));
}

void lcdRenderIdleScreen() {
    // Idle: exact layout requested by user
    lcdWriteRow(0, lcdPad(String("Incubator Idle")));
    lcdWriteRow(1, lcdPad(String("T:") + String(savedTargetTemp, 2) + String(" C:") + String(currentTemp, 2)));
}

void lcdRenderRunningScreen() {
    String tempLabel = String(currentTemp, 2);
    String humLabel = String(currentHumidity, 2);
    String nextSwingLabel = lcdNextSwingLabel();

    switch (lcdRunningPhase) {
        case 0: {
            // Phase 1: Current | Day N  and current readings
            unsigned long nowMs = millis();
            unsigned long currentDay = 1;
            unsigned long totalDays = 21UL;
            if (sessionEndsAt > sessionStartedAt && sessionStartedAt > 0) {
                totalDays = (sessionEndsAt - sessionStartedAt) / 86400000UL;
                if (totalDays == 0) totalDays = 1;
            }
            if (sessionStartedAt > 0) {
                currentDay = (nowMs - sessionStartedAt) / 86400000UL + 1;
                if (currentDay < 1) currentDay = 1;
                if (currentDay > totalDays) currentDay = totalDays;
            }
            String row0 = String("Current | Day ") + String(currentDay);
            lcdWriteRow(0, lcdPad(row0));
            lcdWriteRow(1, lcdPad(String("T:") + tempLabel + String(" H:") + humLabel));
            break;
        }
        case 1: {
            // Phase 2: Target | Rday: N  and target readings
            unsigned long totalDays = 21UL;
            unsigned long remainingDays = 21UL;
            if (sessionEndsAt > sessionStartedAt && sessionStartedAt > 0) {
                totalDays = (sessionEndsAt - sessionStartedAt) / 86400000UL;
                if (totalDays == 0) totalDays = 1;
                unsigned long nowMs = millis();
                if (nowMs < sessionEndsAt) {
                    remainingDays = (sessionEndsAt > nowMs) ? ((sessionEndsAt - nowMs + 86399999UL) / 86400000UL) : 0;
                } else {
                    remainingDays = 0;
                }
            }
            String row0 = String("Target | Rday:") + String(remainingDays);
            lcdWriteRow(0, lcdPad(row0));
            lcdWriteRow(1, lcdPad(String("T:") + String(savedTargetTemp, 2) + String(" H:") + String(savedTargetHum, 2)));
            break;
        }
        case 2: {
            // Phase 3: Date range and next swing start->end (dates if time valid)
            if (systemTimeLooksValid() && sessionStartedAt > 0 && sessionEndsAt > 0) {
                time_t nowEpoch = time(nullptr);
                time_t startEpoch = nowEpoch - (time_t)((millis() - sessionStartedAt) / 1000UL);
                time_t endEpoch = startEpoch + (time_t)((sessionEndsAt - sessionStartedAt) / 1000UL);

                struct tm startTm;
                localtime_r(&startEpoch, &startTm);
                char startBuf[12];
                snprintf(startBuf, sizeof(startBuf), "%02d/%02d", startTm.tm_mday, startTm.tm_mon + 1);

                struct tm endTm;
                localtime_r(&endEpoch, &endTm);
                char endBuf[12];
                snprintf(endBuf, sizeof(endBuf), "%02d/%02d", endTm.tm_mday, endTm.tm_mon + 1);

                String row0 = String("D: ") + String(startBuf) + String(" - ") + String(endBuf);
                lcdWriteRow(0, lcdPad(row0));

                // Next swing start/end dates when possible
                if (systemTimeLooksValid() && savedTurningInt > 0.0f) {
                    unsigned long intervalMs = (unsigned long)(savedTurningInt * 3600.0f * 1000.0f);
                    unsigned long nowMs = millis();
                    unsigned long nextStartOffsetMs = 0;
                    if (lastSwingScheduledAt == 0) {
                         nextStartOffsetMs = 0;
                    } else {
                        unsigned long candidate = lastSwingScheduledAt + intervalMs;
                        if (candidate > nowMs) nextStartOffsetMs = candidate - nowMs;
                        else nextStartOffsetMs = 0;
                    }
                    time_t nextStartEpoch = time(nullptr) + (time_t)(nextStartOffsetMs / 1000UL);
                    time_t nextEndEpoch = nextStartEpoch + (time_t)savedSwingDurationSec;
                    struct tm nsTm; localtime_r(&nextStartEpoch, &nsTm);
                    struct tm neTm; localtime_r(&nextEndEpoch, &neTm);
                    char nsBuf[12]; snprintf(nsBuf, sizeof(nsBuf), "%02d/%02d", nsTm.tm_mday, nsTm.tm_mon + 1);
                    char neBuf[12]; snprintf(neBuf, sizeof(neBuf), "%02d/%02d", neTm.tm_mday, neTm.tm_mon + 1);
                    String row1 = String("nxt:") + String(nsBuf) + String("->") + String(neBuf);
                    lcdWriteRow(1, lcdPad(row1));
                } else {
                    // Fallback: show relative next swing label
                    String row1 = String("nxtSwing :") + nextSwingLabel;
                    lcdWriteRow(1, lcdPad(row1));
                }
            } else {
                // Time not valid: fallback to session duration + next swing relative
                lcdWriteRow(0, lcdPad(String("D: ") + lcdSessionDurationLabel()));
                String row1 = String("nxtSwing :") + nextSwingLabel;
                lcdWriteRow(1, lcdPad(row1));
            }
            break;
        }
    }
}

void lcdRenderCompletedScreen() {
    lcdWriteRow(0, lcdScroll(String("Session complete"), millis()));
    lcdWriteRow(1, lcdScroll(String("Waiting for server"), millis()));
}

void updateLcdDisplay() {
    if (!lcdReady) {
        return;
    }

    unsigned long nowMs = millis();
    if (lcdTransientUntil > nowMs) {
        lcdWriteRow(0, lcdScroll(lcdTransientLine1, nowMs));
        lcdWriteRow(1, lcdScroll(lcdTransientLine2, nowMs));
        return;
    }

    if (WiFi.status() != WL_CONNECTED) {
        lcdWriteRow(0, lcdScroll(String("WiFi disconnected"), nowMs));
        lcdWriteRow(1, lcdScroll(String("Retrying connection"), nowMs));
        return;
    }

    if (sessionMode == MODE_RUNNING) {
        if (nowMs - lcdLastRunningPhaseTick >= LCD_PHASE_INTERVAL_MS) {
            lcdLastRunningPhaseTick = nowMs;
            lcdRunningPhase = (lcdRunningPhase + 1) % 3; // three phases
        }
        lcdRenderRunningScreen();
        return;
    }

    if (sessionMode == MODE_COMPLETED) {
        lcdRenderCompletedScreen();
        return;
    }

    lcdRenderIdleScreen();
}

void lcdInit() {
    Wire.begin(LCD_SDA, LCD_SCL);
    lcd.init();
    lcd.backlight();
    lcd.clear();
    lcdReady = true;
    lcdShowTransient(String("Booting..."), String("Starting display"), 1000UL);
}

// --- end LCD block ---

const char* getCurrentModeLabel() {
    if (TEST_MODE) return "TEST_MODE";
    if (sessionMode == MODE_RUNNING) return "INCUBATING";
    if (sessionMode == MODE_COMPLETED) return "COMPLETED";
    return "IDLE";
}

void printRelayStates(const char* tag) {
    Serial.print("["); Serial.print(tag); Serial.print("] Relays: heater=");
    Serial.print(heaterGroupOn ? "ON" : "OFF");
    Serial.print(" fan="); Serial.print(heaterFanOn ? "ON" : "OFF");
    Serial.print(" h1="); Serial.print(heater1On ? "ON" : "OFF");
    Serial.print(" h2="); Serial.print(heater2On ? "ON" : "OFF");
    Serial.print(" swing="); Serial.print(eggswingOn ? "ON" : "OFF");
    Serial.print(" exhaust="); Serial.println(exhaustOn ? "ON" : "OFF");
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

    unsigned long bootStart = millis();
    while (millis() - bootStart < BOOT_ALL_MODULES_MS) {
        unsigned long elapsed = millis() - bootStart;
        unsigned long remainingMs = (elapsed >= BOOT_ALL_MODULES_MS) ? 0 : (BOOT_ALL_MODULES_MS - elapsed);
        uint8_t remainingSeconds = (remainingMs + 999UL) / 1000UL;
        lcdRenderBootScreen(remainingSeconds == 0 ? 1 : remainingSeconds);
        delay(100);
        yield();
    }

    setHeater(false);
    setSwing(false);
    setExhaust(false);
    printRelayStates("BOOT_ALL_OFF");
    Serial.println(F("[Boot] Startup module sequence complete"));
}

void clearSessionState(bool clearEeprom) {
    sessionMode = MODE_IDLE;
    sessionId = 0;
    sessionName[0] = '\0';
    sessionStartedAt = 0;
    sessionEndsAt = 0;
    lastSwingScheduledAt = 0;
    lastSwingExecutedAt = 0;
    turningLockdownActive = false;
    turningLockdownDaysRemaining = -1;
    heaterManualOverride = false;
    swingManualOverride = false;

    heaterGroupOn = false;
    heaterFanOn = false;
    heater1On = false;
    heater2On = false;
    eggswingOn = false;
    exhaustOn = false;
    writeHeaterFan(false);
    writeHeater1(false);
    writeHeater2(false);
    writeEggSwing(false);
    writeExhaust(false);
    exhaustCycleActive = false;

    if (clearEeprom) {
        for (int i = 0; i < 50; i++) {
            EEPROM.write(EEPROM_SESSION_NAME + i, 0);
        }
        EEPROM.put(EEPROM_SESSION_ID, 0);
        EEPROM.write(EEPROM_SESSION_MODE, (uint8_t)MODE_IDLE);
        EEPROM.commit();
    }

    bootNeedsServerConfirm = true;
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

    SessionMode prevMode = sessionMode;
    sessionMode = MODE_RUNNING;
    sessionId = nextSessionId;
    strncpy(sessionName, nextSessionName, sizeof(sessionName) - 1);
    sessionName[sizeof(sessionName) - 1] = '\0';
    sessionStartedAt = millis();
    const unsigned long sessionDurationMs = 21UL * 24UL * 3600UL * 1000UL;
    sessionEndsAt = sessionStartedAt + sessionDurationMs;
    lastSwingScheduledAt = 0;
    setHeater(true);
    bootNeedsServerConfirm = true;
    Serial.printf("[Session] Offline scheduled start triggered at %ld for #%d %s\n",
        (long)nowEpoch, sessionId, sessionName);

    if (prevMode != sessionMode) {
        Serial.println(F("[Mode] Switched to INCUBATING (offline scheduled start)"));
    }
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

    lcdInit();
    
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

    // Respect EEPROM session mode — allow resuming INCUBATING from EEPROM
    // if the server is unreachable at boot. You can disable this behavior by
    // setting `RESUME_ON_BOOT_IF_OFFLINE` to 0 at compile time.
#if !RESUME_ON_BOOT_IF_OFFLINE
    if (sessionMode != MODE_IDLE) {
        Serial.println(F("[Boot] RESUME_ON_BOOT_IF_OFFLINE disabled — forcing MODE_IDLE until server confirms running"));
        sessionMode = MODE_IDLE;
    }
#endif
    bootNeedsServerConfirm = true;
    
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
    bool wifiConnected = startWiFiAP();
    if (wifiConnected) {
        // Only sync/fetch initial parameters if we were not already intentionally left in IDLE
        if (sessionMode != MODE_IDLE) {
            syncTimeFromNtp();
            // removed "Loading settings" transient per request
            bool initialConfigLoaded = fetchParametersFromServer();
            if (initialConfigLoaded) {
                bootNeedsServerConfirm = false;
                lcdShowTransient(String("System ready"), String("Starting control loop"), 5000UL);
            }
        } else {
            // If already in IDLE, skip server fetch to avoid changing mode on boot
            Serial.println(F("[WiFi] Connected, starting in IDLE (no server fetch)"));
        }
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
    updateLcdDisplay();
    
    unsigned long now = millis();

    // If we booted with a saved RUNNING state, or we need server confirmation,
    // attempt to confirm with the server as soon as WiFi is available. If the
    // fetch fails, continue operating with EEPROM parameters (resume offline).
    if (bootNeedsServerConfirm && WiFi.status() == WL_CONNECTED) {
        lastSettingsFetch = now;
        Serial.println(F("[Boot] Confirming session status from server..."));
        bool ok = fetchParametersFromServer();
        if (ok) {
            bootNeedsServerConfirm = false;
            lcdShowTransient(String("System ready"), String("Starting control loop"), 3000UL);
        } else {
            Serial.println(F("[Boot] Server confirm failed — continuing with local EEPROM state"));
        }
    }

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

        // boot confirmation handled globally before mode branches

        if (!ntpTimeReady && WiFi.status() == WL_CONNECTED && now - lastSettingsFetch >= SETTINGS_INTERVAL) {
            syncTimeFromNtp();
        }
        
        // Poll for relay test commands (from accounts.php)
        static unsigned long lastIdleRelayPoll = 0;
        if (now - lastIdleRelayPoll >= COMMAND_INTERVAL) {
            lastIdleRelayPoll = now;
            pollServerForTestCommandsIdle();
        }
        
        // Auto-shutdown logic: only turn off relays if not in test mode
        // (test mode commands from accounts.php should be able to control relays)
        if (heaterGroupOn && !hasActiveTestCommand()) setHeater(false);
        if (heaterFanOn && !hasActiveTestCommand()) setHeaterFan(false);
        if (exhaustOn && !hasActiveTestCommand()) setExhaust(false);
        exhaustCycleActive = false;
        if (eggswingOn && !hasActiveTestCommand()) setSwing(false);
        
        if (now - lastSettingsFetch >= IDLE_SETTINGS_INTERVAL) {
            lastSettingsFetch = now;
            Serial.println(F("[IDLE] Fetching parameters from server..."));
            fetchParametersFromServer();
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

        // Exhaust: cycle for air mixing while heating, continuous when temp meets or exceeds max temp
        if (tempOK) {
            const bool overheat = currentTemp >= savedMaxTemp;
            const bool isHeating = currentTemp < savedTargetTemp;
            Serial.printf("[Exhaust] Temp=%.1f | Target=%.1f | Heating=%d | Overheat=%d | exhaustOn=%d\n", currentTemp, savedTargetTemp, isHeating, overheat, exhaustOn);
            
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

        if (now - lastIdleTestCommand >= COMMAND_INTERVAL) {
            lastIdleTestCommand = now;
            pollServerForPendingCommand();
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
        if (now - lastIdleTestCommand >= COMMAND_INTERVAL) {
            lastIdleTestCommand = now;
            pollServerForPendingCommand();
        }
        if (heaterGroupOn) setHeater(false);
        if (heaterFanOn) setHeaterFan(false);
        if (exhaustOn) setExhaust(false);
        exhaustCycleActive = false;
        if (now - lastStatusPrint >= STATUS_INTERVAL) {
            lastStatusPrint = now;
            printPollingStatus("COMPLETED_POLL");
        }

        if (now - lastSettingsFetch >= SETTINGS_INTERVAL) {
            lastSettingsFetch = now;
            fetchParametersFromServer();
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
bool startWiFiAP() {
    // Connect to home network (STA mode)
    WiFi.mode(WIFI_STA);
    Serial.printf("[WiFi] Connecting to: %s\n", WIFI_SSID);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
        lcdRenderWifiScreen(false, attempts + 1);
        delay(500);
        Serial.print(".");
        attempts++;
    }
    
    if (WiFi.status() == WL_CONNECTED) {
        lcdRenderWifiScreen(true, attempts);
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
        lcdShowTransient(String("WiFi connect failed"), String("Check SSID/PASS"), 4000UL);
         
    }
    
     if (ENABLE_AP_MODE) {
        WiFi.softAP(AP_SSID, AP_PASSWORD);
        Serial.printf("[AP] Also running AP mode: %s\n", AP_SSID);
    }

    return WiFi.status() == WL_CONNECTED;
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

    testServer.on("/api/test/force_idle", HTTP_POST, []() {
        clearSessionState(true);
        Serial.println(F("[TEST] Force idle: session cleared and EEPROM reset"));
        testServer.send(200, "application/json", "{\"success\":true,\"mode\":\"IDLE\"}");
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
        }
    } else {
        Serial.printf("[TEST CMD] Poll failed: HTTP %d\n", httpCode);
    }
    
    httpClient.end();
}

// ─────────────────────────────────────────────
//  IDLE MODE: Check if test command is active
// ─────────────────────────────────────────────
bool hasActiveTestCommand() {
    unsigned long now = millis();
    // Allow relay commands to persist for 5 seconds after being set
    // (prevents immediate shutdown of relays in IDLE mode)
    return (now - lastIdleTestCommand) < 5000UL;
}

// ─────────────────────────────────────────────
//  IDLE MODE: Poll server for relay commands
// ─────────────────────────────────────────────
void pollServerForTestCommandsIdle() {
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
            lastIdleTestCommand = millis();  // Mark that we received a command

            if (relay == "heater") {
                setHeater(state);
                Serial.printf("[IDLE_CMD] Heater %s\n", state ? "ON" : "OFF");
            } else if (relay == "heater_fan") {
                heaterFanOn = state;
                writeHeaterFan(state);
                Serial.printf("[IDLE_CMD] Heater fan %s\n", state ? "ON" : "OFF");
            } else if (relay == "heater_1") {
                heater1On = state;
                writeHeater1(state);
                Serial.printf("[IDLE_CMD] Heater 1 %s\n", state ? "ON" : "OFF");
            } else if (relay == "heater_2") {
                heater2On = state;
                writeHeater2(state);
                Serial.printf("[IDLE_CMD] Heater 2 %s\n", state ? "ON" : "OFF");
            } else if (relay == "eggswing") {
                setSwing(state);
                Serial.printf("[IDLE_CMD] Egg swing %s\n", state ? "ON" : "OFF");
            } else if (relay == "exhaust") {
                setExhaust(state);
                Serial.printf("[IDLE_CMD] Exhaust %s\n", state ? "ON" : "OFF");
            }

            printRelayStates("IDLE_CMD");
            postDeviceHeartbeat();
        }
    } else if (httpCode > 0) {
        // No pending command or error - that's OK in IDLE mode
        Serial.printf("[IDLE_POLL] HTTP %d (no pending command)\n", httpCode);
    } else {
        Serial.printf("[IDLE_POLL] Poll failed: %s\n", httpClient.errorToString(httpCode).c_str());
    }
    
    httpClient.end();
}

// Poll server for pending manual command (normal operation)
void pollServerForPendingCommand() {
    String url = "http://";
    url += SERVER_IP;
    url += ":";
    url += SERVER_PORT;
    url += SERVER_BASE_PATH;
    url += "/ajax/hardware_api.php";

    String postData = "action=get_pending_command&incubator_id=" + String(INCUBATOR_ID);

    httpClient.begin(wifiClient, url);
    httpClient.addHeader("Content-Type", "application/x-www-form-urlencoded");
    int httpCode = httpClient.POST(postData);

    if (httpCode == HTTP_CODE_OK) {
        String payload = httpClient.getString();
        StaticJsonDocument<128> doc;
        auto err = deserializeJson(doc, payload);
        if (!err && doc.containsKey("command") ) {
            String cmd = doc["command"] | "";
            if (cmd.length() > 0) {
                Serial.printf("[CMD] Pending command received: %s\n", cmd.c_str());
                if (cmd == "all_off") {
                    setHeater(false);
                    setHeaterFan(false);
                    setExhaust(false);
                    setSwing(false);
                    heaterManualOverride = false;
                    swingManualOverride = false;
                    sessionMode = MODE_COMPLETED;
                    sessionStartedAt = 0;
                    sessionEndsAt = 0;
                    Serial.println(F("[CMD] all_off executed"));
                } else if (cmd == "heater_on") {
                    setHeater(true);
                } else if (cmd == "heater_off") {
                    setHeater(false);
                } else if (cmd == "swing_on") {
                    setSwing(true);
                } else if (cmd == "swing_off") {
                    setSwing(false);
                }
            }
        } else if (!err && doc.containsKey("message")) {
            Serial.printf("[CMD] Server message: %s\n", ((const char*)doc["message"]));
        }
    } else {
        Serial.printf("[CMD] Poll pending command failed: HTTP %d\n", httpCode);
    }

    httpClient.end();
}
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
    // Send last swing execution time (when it actually ran)
    postData += "&last_swing_executed_at=" + String(lastSwingExecutedAt);

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
        if (WiFi.status() == WL_CONNECTED) {
            lcdShowTransient(String("Server connect failed"), String("Using offline mode"), 3000UL);
        } else {
            lcdShowTransient(String("WiFi disconnected"), String("Cannot reach server"), 3000UL);
        }
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
    postData += "&last_swing_executed_at=" + String(lastSwingExecutedAt);
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
        if (WiFi.status() == WL_CONNECTED) {
            lcdShowTransient(String("Server connect failed"), String("Heartbeat retrying"), 3000UL);
        } else {
            lcdShowTransient(String("WiFi disconnected"), String("Heartbeat offline"), 3000UL);
        }
    }
    httpClient.end();
    lastHeartbeatSent = millis();
}

// ─────────────────────────────────────────────
//  FETCH PARAMETERS FROM SERVER
// ─────────────────────────────────────────────
bool fetchParametersFromServer() {
    // POST to server hardware API to request settings
    String url = "http://";
    url += SERVER_IP;
    url += ":";
    url += SERVER_PORT;
    url += SERVER_BASE_PATH;
    url += "/ajax/hardware_api.php";

    Serial.printf("[Server] POST %s\n", url.c_str());
    if (WiFi.status() != WL_CONNECTED) {
        lcdShowTransient(String("WiFi disconnected"), String("Cannot reach server"), 3000UL);
        return false;
    }


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

            // Accept explicit session start/end from server when provided.
            time_t serverSessionStartEpoch = 0;
            time_t serverSessionEndEpoch = 0;
            int serverSessionDurationDays = 0;
            if (doc.containsKey("session_start_epoch") && !doc["session_start_epoch"].isNull()) {
                serverSessionStartEpoch = (time_t)doc["session_start_epoch"].as<long>();
            }
            if (doc.containsKey("session_end_epoch") && !doc["session_end_epoch"].isNull()) {
                serverSessionEndEpoch = (time_t)doc["session_end_epoch"].as<long>();
            } else if (doc.containsKey("session_duration_days") && !doc["session_duration_days"].isNull()) {
                serverSessionDurationDays = doc["session_duration_days"] | 0;
            }

            // If server provided an absolute start/end and device time is valid,
            // convert those epochs into the device's millis()-based session timestamps
            // so existing code can continue to use sessionStartedAt/sessionEndsAt.
            if (serverSessionStartEpoch != 0 && systemTimeLooksValid()) {
                time_t nowEpoch = time(nullptr);
                long deltaSec = (long)(nowEpoch - serverSessionStartEpoch);
                if (deltaSec >= 0) {
                    sessionStartedAt = millis() - (unsigned long)deltaSec * 1000UL;
                } else {
                    // session start in future
                    sessionStartedAt = millis() + (unsigned long)(-deltaSec) * 1000UL;
                }

                if (serverSessionEndEpoch != 0) {
                    long durSec = (long)(serverSessionEndEpoch - serverSessionStartEpoch);
                    if (durSec < 0) durSec = 0;
                    sessionEndsAt = sessionStartedAt + (unsigned long)durSec * 1000UL;
                } else if (serverSessionDurationDays > 0) {
                    sessionEndsAt = sessionStartedAt + (unsigned long)serverSessionDurationDays * 24UL * 3600UL * 1000UL;
                } else {
                    // fallback to default 21 days
                    sessionEndsAt = sessionStartedAt + 21UL * 24UL * 3600UL * 1000UL;
                }
                Serial.printf("[Server] Applied session_start_epoch=%ld session_end_epoch=%ld (converted to millis)", (long)serverSessionStartEpoch, (long)serverSessionEndEpoch);
                Serial.println();
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
            
            if (hasSessionStatus && strcmp(sessionStatus, "scheduled") == 0) {
                Serial.println(F("[Session] Batch is scheduled (waiting for start time)"));
            }

            if (hasSessionStatus && strcmp(sessionStatus, "running") == 0) {
                if (newSessionMode != MODE_RUNNING) {
                    newSessionMode = MODE_RUNNING;
                    settingsChanged = true;
                }
            } else if (hasSessionStatus && strcmp(sessionStatus, "scheduled") == 0) {
                // Session is scheduled — stay in IDLE until start time arrives
                // Server will auto-promote to "running" when time is reached
                if (newSessionMode != MODE_IDLE) {
                    newSessionMode = MODE_IDLE;
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
            SessionMode prevSessionMode = sessionMode;
            sessionMode = newSessionMode;
            sessionId = newSessionId;
            strncpy(sessionName, newSessionName, sizeof(sessionName) - 1);
            sessionName[sizeof(sessionName) - 1] = '\0';
            nextSessionId = newNextSessionId;
            strncpy(nextSessionName, newNextSessionName, sizeof(nextSessionName) - 1);
            nextSessionName[sizeof(nextSessionName) - 1] = '\0';

            // Only run transition actions when the session mode actually changed
            if (sessionMode == MODE_RUNNING && prevSessionMode != MODE_RUNNING) {
                unsigned long nowMs = millis();
                sessionStartedAt = sessionStartedAt == 0 ? nowMs : sessionStartedAt;
                // Calculate session end time: 21 days (default incubation period)
                // If you need dynamic duration from server, add it to the device config response
                const unsigned long sessionDurationMs = 21UL * 24UL * 3600UL * 1000UL;  // 21 days in milliseconds
                sessionEndsAt = sessionStartedAt + sessionDurationMs;
                
                // Reset swing schedule so first turn starts immediately
                // Set it to the past so the interval check is met on the next loop
                if (savedTurningInt > 0.0f) {
                    const unsigned long intervalMs = (unsigned long)(savedTurningInt * 3600.0f * 1000.0f);
                    lastSwingScheduledAt = nowMs - intervalMs;
                } else {
                    lastSwingScheduledAt = 0;
                }
                
                setHeater(true);
                Serial.printf("[Mode] Switched to INCUBATING (session active, ends in ~21 days)\n");
            } else if (sessionMode == MODE_COMPLETED && prevSessionMode != MODE_COMPLETED) {
                setHeater(false);
                setSwing(false);
                Serial.println(F("[Mode] Switched to COMPLETED (server session state)"));
            } else if (sessionMode == MODE_IDLE && prevSessionMode != MODE_IDLE) {
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
            Serial.printf("[Server] Raw payload: %s\n", payload.c_str());
            Serial.printf("[Server] JSON error: %s\n", err.c_str());
            lcdShowTransient(String("Server response"), String("parse error"), 3000UL);
            httpClient.end();
            return false;
        }
    } else {
        Serial.printf("[Server] POST failed: %d (%s)\n", httpCode, httpClient.errorToString(httpCode).c_str());
        lcdShowTransient(String("Server connect failed"), String("Using offline mode"), 3000UL);
        httpClient.end();
        return false;
    }

    httpClient.end();
    return true;
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
    // Only update GPIO if state is actually changing
    bool stateChanged = (heaterGroupOn != on);
    heaterGroupOn = on;
    heaterFanOn = on;
    heater1On = on;
    heater2On = on;
    if (stateChanged) {
        writeHeaterFan(on);
        writeHeater1(on);
        writeHeater2(on);
    }
}

void setHeaterFan(bool on) {
    // Only update GPIO if state is actually changing
    if (heaterFanOn != on) {
        heaterFanOn = on;
        writeHeaterFan(on);
    }
}

void setSwing(bool on) {
    // Only update GPIO if state is actually changing
    if (eggswingOn != on) {
        eggswingOn = on;
        if (on) {
            swingStartedAt = millis();
            lastSwingExecutedAt = swingStartedAt;  // Record when swing actually turned ON
        }
        writeEggSwing(on);
    }
}

void setExhaust(bool on) {
    if (exhaustOn != on) {
        exhaustOn = on;
        writeExhaust(on);
    }
}

//testmode
