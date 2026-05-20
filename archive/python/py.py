import time
import random
import json
from datetime import datetime

try:
    import requests
except ImportError:
    print("Error: 'requests' module not found. Install it with: pip install requests")
    exit(1)

# ============================================================
# ESP8266 GHOST INCUBATOR EMULATOR
# ============================================================
# This script emulates your ESP8266 firmware behavior.
# It:
#   1. Fetches configuration/settings from server
#   2. Sends sensor data to server
#   3. Simulates relay/device states
#   4. Runs continuously like a real ESP8266
# ============================================================

# -----------------------------
# SERVER CONFIGURATION
# -----------------------------
SERVER_IP = "127.0.0.1"
SERVER_PORT = 80
SERVER_BASE_PATH = "/GHOST"
INCUBATOR_ID = 1
DEVICE_TOKEN = "ghost_hw_secret_2024"

BASE_URL = f"http://{SERVER_IP}:{SERVER_PORT}{SERVER_BASE_PATH}"
API_URL = f"{BASE_URL}/ajax/hardware_api.php"

# -----------------------------
# SIMULATED SENSOR VALUES
# -----------------------------
current_temperature = 37.5
current_humidity = 58.0

# -----------------------------
# DEVICE STATES
# -----------------------------
relay_states = {
    "heater_1": False,
    "heater_2": False,
    "heater_fan": False,
    "egg_swing": False,
    "exhaust": False
}


def update_swing_state(session_running):
    if not session_running:
        relay_states["egg_swing"] = False
        return 0

    # Simulate an egg swing pulse every 30 seconds while the session is running.
    swing_pulse = int(time.time() / 30) % 2 == 0
    relay_states["egg_swing"] = swing_pulse
    return int(swing_pulse)


def post_device_heartbeat(sensor_data, config=None):
    url = API_URL
    session_running = bool(config and config.get("session_status") == "running")
    active_session_name = None
    if config:
        active_session_name = config.get("session_name") or config.get("active_session_name")

    payload = {
        "action": "device_heartbeat",
        "token": DEVICE_TOKEN,
        "incubator_id": INCUBATOR_ID,
        "temperature": sensor_data["temperature"],
        "humidity": sensor_data["humidity"],
        "wifi_connected": 1,
        "heater": int(sensor_data["heater_1"] or sensor_data["heater_2"] or sensor_data["heater_fan"]),
        "heater_1": sensor_data["heater_1"],
        "heater_2": sensor_data["heater_2"],
        "heater_fan": sensor_data["heater_fan"],
        "swing": sensor_data["egg_swing"],
        "exhaust": sensor_data["exhaust"],
        "running_ops": "turning" if sensor_data["egg_swing"] and session_running else "idle",
        "current_mode": "incubating" if session_running else "idle",
    }

    if active_session_name:
        payload["active_session_name"] = active_session_name

    try:
        print(f"\n[HEARTBEAT] Sending heartbeat to:\n{url}")
        response = requests.post(url, data=payload, timeout=10)
        print(f"[HEARTBEAT] HTTP Status: {response.status_code}")
        print(f"[HEARTBEAT] Response: {response.text}")
    except Exception as e:
        print(f"[HEARTBEAT ERROR] {e}")

# -----------------------------
# FETCH SERVER SETTINGS
# -----------------------------
def fetch_server_settings():
    url = API_URL
    payload = {
        "action": "get_settings",
        "incubator_id": INCUBATOR_ID,
        "token": DEVICE_TOKEN
    }

    try:
        print(f"\n[FETCH] Requesting settings from:\n{url}")

        response = requests.post(url, data=payload, timeout=10)

        print(f"[FETCH] HTTP Status: {response.status_code}")

        if response.status_code == 200:
            data = response.json()

            print("[FETCH] Server Response:")
            print(json.dumps(data, indent=4))

            return data

        else:
            print("[FETCH] Failed to fetch settings")
            return None

    except Exception as e:
        print(f"[FETCH ERROR] {e}")
        return None

# -----------------------------
# GENERATE SENSOR VALUES
# -----------------------------
def generate_sensor_data(settings=None, session_running=False):
    global current_temperature
    global current_humidity

    target_temp = 37.5
    min_temp = 37.0
    max_temp = 38.0
    target_hum = 60.0
    min_hum = 50.0
    max_hum = 60.0

    if settings:
        target_temp = float(settings.get("target_temp", settings.get("target_temperature", 37.5)))
        min_temp = float(settings.get("min_temp", target_temp - 0.5))
        max_temp = float(settings.get("max_temp", target_temp + 0.5))
        target_hum = float(settings.get("target_humidity", 60.0))
        min_hum = float(settings.get("min_humidity", 50.0))
        max_hum = float(settings.get("max_humidity", 60.0))

    # Enforce a sane control band if server values are swapped.
    if min_temp > max_temp:
        min_temp, max_temp = max_temp, min_temp

    if session_running:

        previous_heater_state = relay_states["heater_1"] or relay_states["heater_2"]
        if current_temperature < min_temp:
            heater_on = True
        elif current_temperature >= target_temp:
            heater_on = False
        else:
            heater_on = previous_heater_state

        # Drive relay states from control model (not random).
        relay_states["heater_1"] = heater_on
        relay_states["heater_2"] = heater_on and current_temperature < (target_temp - 0.2)
        relay_states["heater_fan"] = heater_on
        relay_states["exhaust"] = current_humidity > max_hum
    else:
        # Idle mode: keep actuators OFF but continue sending heartbeat and sensors.
        relay_states["heater_1"] = False
        relay_states["heater_2"] = False
        relay_states["heater_fan"] = False
        relay_states["exhaust"] = False

    # Thermal simulation influenced by heater state.
    if relay_states["heater_fan"]:
        current_temperature += random.uniform(0.04, 0.14)
    else:
        # Cool down slowly toward ambient when heater is off.
        ambient_temp = 30.0
        cooling_bias = (current_temperature - ambient_temp) * 0.01
        current_temperature -= random.uniform(0.02, 0.07) + max(0.0, cooling_bias)

    # Humidity simulation with basic exhaust effect.
    current_humidity += random.uniform(-0.8, 0.8)
    if relay_states["exhaust"]:
        current_humidity -= random.uniform(0.3, 0.9)
    elif current_humidity < min_hum:
        current_humidity += random.uniform(0.2, 0.6)

    # Keep humidity loosely around target.
    if current_humidity > target_hum + 2:
        current_humidity -= random.uniform(0.1, 0.3)
    elif current_humidity < target_hum - 2:
        current_humidity += random.uniform(0.1, 0.3)

    # Clamp values
    current_temperature = round(max(34.0, min(40.0, current_temperature)), 2)
    current_humidity = round(max(40.0, min(80.0, current_humidity)), 2)

    # Swing relay follows session state simulation.
    relay_states["egg_swing"] = bool(update_swing_state(session_running))

    return {
        "temperature": current_temperature,
        "humidity": current_humidity,
        "heater_1": int(relay_states["heater_1"]),
        "heater_2": int(relay_states["heater_2"]),
        "heater_fan": int(relay_states["heater_fan"]),
        "egg_swing": int(relay_states["egg_swing"]),
        "exhaust": int(relay_states["exhaust"])
    }

# -----------------------------
# SEND SENSOR DATA TO SERVER
# -----------------------------
def send_sensor_data(sensor_data):
    url = API_URL

    payload = {
        "action": "device_logs",
        "token": DEVICE_TOKEN,
        "incubator_id": INCUBATOR_ID,
        "temperature": sensor_data["temperature"],
        "humidity": sensor_data["humidity"],
        "heater_1": sensor_data["heater_1"],
        "heater_2": sensor_data["heater_2"],
        "heater_fan": sensor_data["heater_fan"],
        "egg_swing": sensor_data["egg_swing"],
        "exhaust": sensor_data["exhaust"],
        "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    }

    try:
        print(f"\n[POST] Sending data to:\n{url}")
        print("[POST] Payload:")
        print(json.dumps(payload, indent=4))

        response = requests.post(url, data=payload, timeout=10)

        print(f"[POST] HTTP Status: {response.status_code}")
        print(f"[POST] Response: {response.text}")

    except Exception as e:
        print(f"[POST ERROR] {e}")

# -----------------------------
# FETCH CONTROL COMMANDS
# -----------------------------
def fetch_control_commands():
    url = API_URL
    payload = {
        "action": "get_device_config",
        "incubator_id": INCUBATOR_ID,
        "token": DEVICE_TOKEN
    }

    try:
        print(f"\n[CONTROL] Fetching commands from:\n{url}")

        response = requests.post(url, data=payload, timeout=10)

        if response.status_code == 200:
            data = response.json()

            print("[CONTROL] Command Response:")
            print(json.dumps(data, indent=4))

            # Keep the emulator aligned with the current session state reported by the server.
            relay_states["egg_swing"] = bool(data.get("swing_on", data.get("egg_swing", 0)))
            relay_states["exhaust"] = bool(data.get("exhaust", 0))

            session_name = data.get("session_name") or data.get("active_session_name") or ""
            session_status = data.get("session_status") or "idle"
            if session_name:
                print(f"[CONTROL] Active session: {session_name} ({session_status})")

            return data

        else:
            print("[CONTROL] Failed to fetch commands")
            return None

    except Exception as e:
        print(f"[CONTROL ERROR] {e}")
        return None

# -----------------------------
# MAIN LOOP
# -----------------------------
def main():
    print("=" * 60)
    print("ESP8266 GHOST INCUBATOR EMULATOR")
    print("=" * 60)

    loop_counter = 0
    try:
        while True:
            print("\n\n---------------- LOOP START ----------------")

            # Fetch settings every 5 loops
            settings = None

            if loop_counter % 5 == 0:
                settings = fetch_server_settings()

            # Fetch control commands
            config = fetch_control_commands()
            session_running = bool(config and config.get("session_status") == "running")

            # Generate fake sensor readings
            sensor_data = generate_sensor_data(settings, session_running=session_running)

            # Keep the device online on the server side with a heartbeat before logging data.
            post_device_heartbeat(sensor_data, config)

            print("\n[SENSOR DATA]")
            print(json.dumps(sensor_data, indent=4))

            # Send to server
            send_sensor_data(sensor_data)

            print("---------------- LOOP END ----------------")

            loop_counter += 1

            # Delay like ESP8266 loop
            time.sleep(5)
    except KeyboardInterrupt:
        print("\n\n[STOPPED] Emulator stopped by user.")

# -----------------------------
# ENTRY POINT
# -----------------------------
if __name__ == "__main__":
    main()

# ============================================================
# REQUIRED INSTALLATION
# ============================================================
# pip install requests
#
# ============================================================
# SAMPLE SERVER ENDPOINTS
# ============================================================
# GET  /GHOST/get_incubator_settings.php?id=1
# GET  /GHOST/get_controls.php?id=1
# POST /GHOST/save_sensor_data.php
# ============================================================
