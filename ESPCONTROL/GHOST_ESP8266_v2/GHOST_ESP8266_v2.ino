/*
 * ============================================================
 *  GHOST Egg Incubator — ESP8266 Firmware  v2.0
 *
 *  Hardware:
 *    ESP8266 NodeMCU / Wemos D1 Mini
 *    DS18B20  — waterproof temperature sensor (replaces DHT)
 *    DHT22    — humidity-only sensor (D4)
 *    Relay 1  (D5) → 2× PTC Heater in parallel
 *                     (PTC has built-in fan — no separate fan pin)
 *    Relay 2  (D6) → 3× Egg Swing motor in parallel
 *
 *  Libraries required (install via Library Manager):
 *    - OneWire              by Paul Stoffregen
 *    - DallasTemperature    by Miles Burton
 *    - DHT sensor library   by Adafruit
 *    - Adafruit Unified Sensor by Adafruit
 *    - ArduinoJson          v6.x by Benoit Blanchon
 *    - ESP8266WiFi          (bundled with ESP8266 board package)
 *    - ESP8266HTTPClient    (bundled with ESP8266 board package)
 * ============================================================
 */

#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <DHT.h>
#include <ArduinoJson.h>

// ─────────────────────────────────────────────
//  ★  USER CONFIGURATION — EDIT THESE  ★
// ─────────────────────────────────────────────
const char* WIFI_SSID     = "YOUR_WIFI_SSID";
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";

// Your PC's local IP address where XAMPP is running
// Open cmd → ipconfig → look for IPv4 Address
const char* SERVER_HOST   = "http://10.113.169.46";
const char* API_PATH      = "/GHOST/ajax/hardware_api.php";

// Incubator ID — must match incubators.id in your database
const int   INCUBATOR_ID  = 1;

// Secret token — must match DEVICE_TOKEN in hardware_api.php
const char* DEVICE_TOKEN  = "ghost_hw_secret_2024";

// ─────────────────────────────────────────────
//  PIN DEFINITIONS
// ─────────────────────────────────────────────
#define DS18B20_PIN    D3   // DS18B20 data wire (temperature)
#define DHT_PIN        D4   // DHT22 data pin    (humidity only)
#define DHT_TYPE       DHT22

#define RELAY_HEATER   D5   // Relay 1 → 2× PTC heater (built-in fan)
#define RELAY_SWING    D6   // Relay 2 → 3× egg swing motor

// ─────────────────────────────────────────────
//  RELAY LOGIC
//  This hardware is wired as ACTIVE HIGH:
//    HIGH = relay energised (ON)
//    LOW  = relay off
// ─────────────────────────────────────────────
#define RELAY_ON   HIGH
#define RELAY_OFF  LOW

// ─────────────────────────────────────────────
//  TIMING  (milliseconds)
// ─────────────────────────────────────────────
#define SENSOR_INTERVAL      10000UL   // Read sensors every 10 s
#define LOG_INTERVAL         60000UL   // Log to server every 60 s
#define SETTINGS_INTERVAL    30000UL   // Fetch temp settings every 30 s
#define SCHEDULE_INTERVAL    30000UL   // Check turning schedules every 30 s
#define COMMAND_INTERVAL      5000UL   // Poll manual commands every 5 s
#define SWING_DURATION       30000UL   // Swing motors run 30 s per cycle
#define WIFI_RECONNECT_MS    10000UL   // Retry Wi-Fi every 10 s
#define STATUS_INTERVAL       4000UL   // Send live heartbeat every 4 s

// ─────────────────────────────────────────────
//  SENSOR OBJECTS
// ─────────────────────────────────────────────
OneWire           oneWire(DS18B20_PIN);
unsigned long lastStatusSend     = 0;
DallasTemperature ds18b20(&oneWire);
DHT               dht(DHT_PIN, DHT_TYPE);
bool wifiWasConnected           = false;
WiFiClient        wifiClient;

// ─────────────────────────────────────────────
    sendDeviceStatus(true);
//  LIVE SENSOR VALUES
// ─────────────────────────────────────────────
float currentTemp     = 0.0;
float currentHumidity = 0.0;
bool  tempOK          = false;
            wifiWasConnected = false;
            bool reconnected = connectWiFi();
            if (reconnected) {
                fetchSettings();
                fetchLiveSessionState();
                sendDeviceStatus(true);
                wifiWasConnected = true;
            }

// ─────────────────────────────────────────────
//  SETTINGS (fetched from server)
// ─────────────────────────────────────────────
float targetTemp      = 37.50;
float minTemp         = 37.00;

    if (!wifiWasConnected) {
        wifiWasConnected = true;
        fetchSettings();
        fetchLiveSessionState();
        sendDeviceStatus(true);
    }
float maxTemp         = 38.00;
float targetHumidity  = 55.00;
float minHumidity     = 50.00;
float maxHumidity     = 60.00;
int   turningInterval = 8;

    // ── Push lightweight heartbeat (every 4 s) ─
    if (now - lastStatusSend >= STATUS_INTERVAL) {
        lastStatusSend = now;
        sendDeviceStatus(false);
    }

// ─────────────────────────────────────────────
//  RELAY STATE
bool connectWiFi() {
bool heaterOn = false;
bool swingOn  = false;

// Manual override flags (set by dashboard commands)
bool heaterManualOverride = false;   // true = dashboard forced a state
bool swingManualOverride  = false;

        return true;
    } else {
//  TIMERS
        return false;
// ─────────────────────────────────────────────
unsigned long lastSensorRead    = 0;
unsigned long lastLog           = 0;
    String payload = "action=get_device_config";
unsigned long lastScheduleFetch = 0;
unsigned long lastCommandPoll   = 0;
unsigned long lastWifiRetry     = 0;
    String payload = "action=get_device_session";

// ─────────────────────────────────────────────
//  SETUP
// ─────────────────────────────────────────────
void setup() {

// ─────────────────────────────────────────────
//  PUSH LIGHTWEIGHT STATUS HEARTBEAT
// ─────────────────────────────────────────────
void sendDeviceStatus(bool force) {
    if (!force && !tempOK && !humOK) {
        return;
    }

    String payload = "action=device_status";
    payload += "&incubator_id=" + String(INCUBATOR_ID);
    if (tempOK) {
        payload += "&temperature=" + String(currentTemp, 2);
    }
    if (humOK) {
        payload += "&humidity=" + String(currentHumidity, 2);
    }
    payload += "&heater_on=" + String(heaterOn ? 1 : 0);
    payload += "&heater_1_status=" + String(heaterOn ? 1 : 0);
    payload += "&heater_2_status=" + String(heaterOn ? 1 : 0);
    payload += "&heater_fan_status=" + String(heaterOn ? 1 : 0);
    payload += "&exhaust_status=0";
    payload += "&swing_on=" + String(swingOn ? 1 : 0);
    payload += "&swing_status=" + String(swingOn ? 1 : 0);
    payload += "&wifi_connected=1";
    payload += "&current_mode=" + currentModeText();
    payload += "&running_ops=" + runningOpsText();
    payload += "&token=" + String(DEVICE_TOKEN);

    String resp = httpPost(payload);
    if (resp.isEmpty()) {
        Serial.println(F("[Heartbeat] No response from server"));
        return;
    }

    StaticJsonDocument<128> doc;
    auto err = deserializeJson(doc, resp);
    if (err) {
        Serial.printf("[Heartbeat] JSON parse error: %s\n", err.c_str());
        return;
    }
    if (doc["success"].as<bool>()) {
        Serial.printf("[Heartbeat] Temp:%.2f Hum:%.2f Heater:%s Swing:%s\n",
                      tempOK ? currentTemp : 0.0,
                      humOK ? currentHumidity : 0.0,
                      heaterOn ? "ON" : "OFF",
                      swingOn ? "ON" : "OFF");
    }
}
    Serial.begin(115200);
    delay(300);

    Serial.println(F("\n========================================"));
    Serial.println(F("  GHOST Incubator — ESP8266 v2.0"));

String runningOpsText() {
    if (heaterOn && swingOn) return "heating, turning";
    if (heaterOn) return "heating";
    if (swingOn) return "turning";
    return "idle";
}

String currentModeText() {
    if (sessionStatus == "running") return "incubating";
    if (sessionStatus == "completed") return "hatching";
    return "idle";
}
    Serial.println(F("  DS18B20 + DHT22 | PTC + Swing Relay"));
    String payload = "action=device_logs";
    Serial.println(F("========================================\n"));

    // Relay pins — default OFF before anything else
    pinMode(RELAY_HEATER, OUTPUT);
    pinMode(RELAY_SWING,  OUTPUT);
    digitalWrite(RELAY_HEATER, RELAY_OFF);
    digitalWrite(RELAY_SWING,  RELAY_OFF);
    payload += "&wifi_connected=1";
    payload += "&current_mode=" + currentModeText();
    payload += "&running_ops=" + runningOpsText();

    // Start sensors
    ds18b20.begin();
    dht.begin();

    connectWiFi();

    // First reads on boot
    readTemperature();
    readHumidity();
    fetchSettings();
}

// ─────────────────────────────────────────────
//  MAIN LOOP
// ─────────────────────────────────────────────
void loop() {
    unsigned long now = millis();

    // ── Wi-Fi watchdog ────────────────────────
    if (WiFi.status() != WL_CONNECTED) {
        if (now - lastWifiRetry >= WIFI_RECONNECT_MS) {
            lastWifiRetry = now;
            Serial.println(F("[WiFi] Lost — reconnecting..."));
            connectWiFi();
        }
        // Still run heater control even if offline
        if (tempOK) controlHeater();
        delay(100);
        return;
    }

    // ── Read sensors ──────────────────────────
    if (now - lastSensorRead >= SENSOR_INTERVAL) {
        lastSensorRead = now;
        readTemperature();
        readHumidity();
    }

    // ── Heater thermostat control ─────────────
    // Skip automatic control if dashboard has manually overridden
    if (tempOK && !heaterManualOverride) {
        controlHeater();
    }

    // ── Auto-stop swing after SWING_DURATION ──
    if (swingOn && !swingManualOverride) {
        if (now - swingStartedAt >= SWING_DURATION) {
            setSwing(false);
            Serial.println(F("[Swing] Auto-stop — cycle complete"));
        }
    }

    // ── Poll manual commands (every 5 s) ──────
    if (now - lastCommandPoll >= COMMAND_INTERVAL) {
        lastCommandPoll = now;
        fetchAndExecuteCommand();
    }

    // ── Fetch settings from server (every 30 s)
    if (now - lastSettingsFetch >= SETTINGS_INTERVAL) {
        lastSettingsFetch = now;
        fetchSettings();
    }

    // ── Fetch turning schedules (every 30 s) ──
    if (now - lastScheduleFetch >= SCHEDULE_INTERVAL) {
        lastScheduleFetch = now;
        fetchAndRunSchedules();
    }

    // ── Log sensor data to server (every 60 s) ─
    if (now - lastLog >= LOG_INTERVAL) {
        lastLog = now;
        logSensorData();
    }

    delay(100);
}

// ─────────────────────────────────────────────
//  WI-FI CONNECTION
// ─────────────────────────────────────────────
void connectWiFi() {
    Serial.printf("[WiFi] Connecting to %s", WIFI_SSID);
    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 24) {
        delay(500);
        Serial.print(".");
        attempts++;
    }
    if (WiFi.status() == WL_CONNECTED) {
        Serial.printf("\n[WiFi] Connected — IP: %s\n", WiFi.localIP().toString().c_str());
    } else {
        Serial.println(F("\n[WiFi] Failed — will retry"));
    }
}

// ─────────────────────────────────────────────
//  READ TEMPERATURE  (DS18B20)
// ─────────────────────────────────────────────
void readTemperature() {
    ds18b20.requestTemperatures();
    float t = ds18b20.getTempCByIndex(0);

    // DS18B20 returns -127 on error
    if (t <= -100.0 || t > 85.0) {
        tempOK = false;
        Serial.println(F("[DS18B20] Read failed — check wiring or power"));
        return;
    }

    currentTemp = t;
    tempOK      = true;
    Serial.printf("[DS18B20] Temperature: %.2f°C\n", currentTemp);
}

// ─────────────────────────────────────────────
//  READ HUMIDITY  (DHT22 — humidity only)
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

// ─────────────────────────────────────────────
//  HEATER THERMOSTAT CONTROL
//  PTC heaters have built-in fans — no separate fan relay needed.
//  Simple bang-bang thermostat:
//    temp < minTemp → Relay ON  (heat up)
//    temp >= minTemp → Relay OFF (target reached / overshoot protection)
// ─────────────────────────────────────────────
void controlHeater() {
    if (currentTemp < minTemp && !heaterOn) {
        setHeater(true);
        Serial.printf("[Heater] ON  — %.2f°C < %.2f°C\n", currentTemp, minTemp);
    } else if (currentTemp >= minTemp && heaterOn) {
        setHeater(false);
        Serial.printf("[Heater] OFF — %.2f°C >= %.2f°C\n", currentTemp, minTemp);
    }
}

// ─────────────────────────────────────────────
//  RELAY SETTERS
// ─────────────────────────────────────────────
void setHeater(bool on) {
    heaterOn = on;
    digitalWrite(RELAY_HEATER, on ? RELAY_ON : RELAY_OFF);
}

void setSwing(bool on) {
    swingOn = on;
    if (on) swingStartedAt = millis();
    digitalWrite(RELAY_SWING, on ? RELAY_ON : RELAY_OFF);
}

// ─────────────────────────────────────────────
//  HTTP POST HELPER
// ─────────────────────────────────────────────
String httpPost(const String& payload) {
    String url = String(SERVER_HOST) + API_PATH;
    HTTPClient http;
    http.begin(wifiClient, url);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    http.setTimeout(8000);
    int    code = http.POST(payload);
    String resp = "";
    if (code == HTTP_CODE_OK) {
        resp = http.getString();
    } else {
        Serial.printf("[HTTP] Error %d\n", code);
    }
    http.end();
    return resp;
}

// ─────────────────────────────────────────────
//  FETCH SETTINGS FROM SERVER
// ─────────────────────────────────────────────
void fetchSettings() {
    String payload = "action=get_device_config";
    payload += "&incubator_id=" + String(INCUBATOR_ID);
    payload += "&token=" + String(DEVICE_TOKEN);

    String resp = httpPost(payload);
    if (resp.isEmpty()) return;

    StaticJsonDocument<256> doc;
    if (deserializeJson(doc, resp) || !doc["success"].as<bool>()) return;

    targetTemp      = doc["target_temp"]      | targetTemp;
    minTemp         = doc["min_temp"]          | minTemp;
    maxTemp         = doc["max_temp"]          | maxTemp;
    targetHumidity  = doc["target_humidity"]   | targetHumidity;
    minHumidity     = doc["min_humidity"]      | minHumidity;
    maxHumidity     = doc["max_humidity"]      | maxHumidity;
    turningInterval = doc["turning_interval"]  | turningInterval;

    Serial.printf("[Settings] target=%.1f°C  min=%.1f  max=%.1f  swing=%dh\n",
                  targetTemp, minTemp, maxTemp, turningInterval);
}

// ─────────────────────────────────────────────
//  FETCH & EXECUTE PENDING MANUAL COMMAND
//  Dashboard sends heater_on / heater_off / swing_on / swing_off / all_off
//  via manual_command action → stored in hardware_commands table.
//  This polls get_pending_command every 5 s and executes it.
// ─────────────────────────────────────────────
void fetchAndExecuteCommand() {
    String payload = "action=get_pending_command";
    payload += "&incubator_id=" + String(INCUBATOR_ID);
    payload += "&token=" + String(DEVICE_TOKEN);

    String resp = httpPost(payload);
    if (resp.isEmpty()) return;

    StaticJsonDocument<128> doc;
    if (deserializeJson(doc, resp) || !doc["success"].as<bool>()) return;

    const char* cmd = doc["command"] | "";
    if (strlen(cmd) == 0) return;   // no pending command

    Serial.printf("[Command] Received: %s\n", cmd);

    if (strcmp(cmd, "heater_on") == 0) {
        heaterManualOverride = true;
        setHeater(true);
        Serial.println(F("[Command] Heater ON (manual override)"));

    } else if (strcmp(cmd, "heater_off") == 0) {
        heaterManualOverride = false;   // release override so thermostat resumes
        setHeater(false);
        Serial.println(F("[Command] Heater OFF — thermostat resumed"));

    } else if (strcmp(cmd, "swing_on") == 0) {
        swingManualOverride = false;    // allow auto-stop after SWING_DURATION
        setSwing(true);
        Serial.println(F("[Command] Swing ON (manual)"));

    } else if (strcmp(cmd, "swing_off") == 0) {
        swingManualOverride = false;
        setSwing(false);
        Serial.println(F("[Command] Swing OFF (manual)"));

    } else if (strcmp(cmd, "all_off") == 0) {
        heaterManualOverride = false;
        swingManualOverride  = false;
        setHeater(false);
        setSwing(false);
        Serial.println(F("[Command] Heater and swing OFF (manual)"));
    }
}

// ─────────────────────────────────────────────
//  FETCH TURNING SCHEDULES & RUN SWING
// ─────────────────────────────────────────────
void fetchAndRunSchedules() {
    String payload = "action=get_pending_schedules";
    payload += "&incubator_id=" + String(INCUBATOR_ID);
    payload += "&token=" + String(DEVICE_TOKEN);

    String resp = httpPost(payload);
    if (resp.isEmpty()) return;

    StaticJsonDocument<1024> doc;
    if (deserializeJson(doc, resp) || !doc["success"].as<bool>()) return;

    JsonArray schedules = doc["schedules"].as<JsonArray>();
    for (JsonObject s : schedules) {
        const char* actionType = s["action_type"] | "";
        int         schedId    = s["id"] | 0;

        if (strcmp(actionType, "turning") == 0) {
            Serial.printf("[Schedule] Turning #%d triggered\n", schedId);
            swingManualOverride = false;
            setSwing(true);
            markScheduleDone(schedId);
        }
    }
}

// ─────────────────────────────────────────────
//  MARK SCHEDULE DONE
// ─────────────────────────────────────────────
void markScheduleDone(int scheduleId) {
    String payload = "action=mark_done";
    payload += "&schedule_id=" + String(scheduleId);
    payload += "&token=" + String(DEVICE_TOKEN);
    httpPost(payload);
    Serial.printf("[Schedule] #%d marked done\n", scheduleId);
}

// ─────────────────────────────────────────────
//  LOG SENSOR DATA TO SERVER
//  Posts temperature, humidity, and relay states.
//  Server also updates hardware_state for live dashboard polling.
// ─────────────────────────────────────────────
void logSensorData() {
    if (!tempOK) {
        Serial.println(F("[Log] Skipped — no valid temperature reading"));
        return;
    }

    String payload = "action=device_logs";
    payload += "&incubator_id=" + String(INCUBATOR_ID);
    payload += "&temperature="  + String(currentTemp, 2);
    if (humOK) {
        payload += "&humidity=" + String(currentHumidity, 2);
    }
    payload += "&heater=" + String(heaterOn ? 1 : 0);
    payload += "&swing="  + String(swingOn  ? 1 : 0);
    payload += "&wifi_connected=1";
    payload += "&current_mode=" + currentModeText();
    payload += "&running_ops=" + runningOpsText();
    payload += "&token="  + String(DEVICE_TOKEN);

    String resp = httpPost(payload);

    StaticJsonDocument<64> doc;
    if (!deserializeJson(doc, resp) && doc["success"].as<bool>()) {
        Serial.printf("[Log] Sent → Temp:%.2f°C  Hum:%.2f%%  Heater:%s  Swing:%s\n",
                      currentTemp,
                      humOK ? currentHumidity : 0.0,
                      heaterOn ? "ON" : "OFF",
                      swingOn  ? "ON" : "OFF");
    } else {
        Serial.println(F("[Log] Server rejected or no response"));
    }
}
