<?php
/**
 * GHOST Incubator - Test Panel
 * Server-mediated relay control & sensor monitoring
 */
$INCUBATOR_ID = 1;
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GHOST Incubator - Test Panel</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 20px;
    }

    .container {
        max-width: 900px;
        margin: 0 auto;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        padding: 30px;
    }

    h1 {
        text-align: center;
        color: #333;
        margin-bottom: 10px;
        font-size: 2.5em;
    }

    .subtitle {
        text-align: center;
        color: #666;
        margin-bottom: 30px;
        font-size: 0.95em;
    }

    .status-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }

    .status-card {
        background: #f8f9fa;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
    }

    .status-card h3 {
        color: #666;
        font-size: 0.95em;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .status-value {
        font-size: 2.5em;
        font-weight: bold;
        color: #667eea;
    }

    .status-unit {
        font-size: 0.6em;
        color: #999;
        margin-left: 5px;
    }

    .relay-section {
        margin-bottom: 30px;
    }

    .relay-section h2 {
        color: #333;
        font-size: 1.3em;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
    }

    .relay-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .relay-card {
        background: #f8f9fa;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
    }

    .relay-name {
        font-size: 1.1em;
        font-weight: bold;
        color: #333;
        margin-bottom: 15px;
    }

    .relay-buttons {
        display: flex;
        gap: 10px;
    }

    button {
        flex: 1;
        padding: 12px 20px;
        border: none;
        border-radius: 6px;
        font-size: 0.95em;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .btn-on {
        background: #4CAF50;
        color: white;
    }

    .btn-on:hover {
        background: #45a049;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
    }

    .btn-on:active {
        transform: translateY(0);
    }

    .btn-off {
        background: #f44336;
        color: white;
    }

    .btn-off:hover {
        background: #da190b;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(244, 67, 54, 0.4);
    }

    .btn-off:active {
        transform: translateY(0);
    }

    .message {
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        display: none;
    }

    .message.show {
        display: block;
    }

    .message.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .message.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .connection-status {
        padding: 10px 15px;
        border-radius: 6px;
        text-align: center;
        margin-bottom: 20px;
        font-weight: bold;
    }

    .connection-status.connected {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .connection-status.disconnected {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .footer {
        text-align: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #e0e0e0;
        color: #666;
        font-size: 0.9em;
    }

    .health-status {
        background: white;
        border: 2px solid #667eea;
        border-radius: 8px;
        padding: 0;
        margin-bottom: 30px;
        overflow: hidden;
    }

    .health-status table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.95em;
    }

    .health-status thead {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .health-status th {
        padding: 15px;
        text-align: left;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .health-status td {
        padding: 12px 15px;
        border-bottom: 1px solid #e0e0e0;
        color: #333;
    }

    .health-status tbody tr:hover {
        background: #f8f9fa;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 600;
    }

    .status-online {
        background: #4CAF50;
        color: white;
    }

    .status-idle {
        background: #FFC107;
        color: #333;
    }

    .status-offline {
        background: #f44336;
        color: white;
    }

    .relay-on {
        color: #4CAF50;
        font-weight: 600;
    }

    .relay-off {
        color: #999;
        font-weight: 600;
    }
    </style>
</head>

<body>
    <div class="container">
        <h1>🐣 GHOST Test Panel</h1>
        <p class="subtitle">ESP8266 Relay Control & Sensor Testing</p>
        <p class="subtitle" style="margin-top:-20px; margin-bottom:25px; font-size:0.88em; color:#777;">
            DHT22 sensor: D2
        </p>

        <div class="status-grid">
            <div class="status-card">
                <h3>Temperature</h3>
                <div class="status-value">
                    <span id="tempValue">--</span>
                    <span class="status-unit">°C</span>
                </div>
            </div>
            <div class="status-card">
                <h3>Humidity</h3>
                <div class="status-value">
                    <span id="humValue">--</span>
                    <span class="status-unit">%</span>
                </div>
            </div>
        </div>

        <!-- Device Status Table Display -->
        <div class="health-status" id="healthStatus">
            <table>
                <thead>
                    <tr>
                        <th colspan="4">🔄 Device Status & Relay Monitor</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Status</strong></td>
                        <td colspan="3"><span class="status-badge status-offline">● OFFLINE</span></td>
                    </tr>
                    <tr>
                        <td><strong>WiFi SSID</strong></td>
                        <td colspan="3">--</td>
                    </tr>
                    <tr>
                        <td><strong>Device IP</strong></td>
                        <td colspan="3">--</td>
                    </tr>
                    <tr>
                        <td colspan="3" style="padding-top: 15px; padding-bottom: 5px;"><strong>Relay States</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Pin</strong></td>
                        <td><strong>Name</strong></td>
                        <td><strong>State</strong></td>
                    </tr>
                    <tr>
                        <td>D5</td>
                        <td>Heater 1 relay</td>
                        <td><span class="relay-off">OFF</span></td>
                    </tr>
                    <tr>
                        <td>D6</td>
                        <td>Heater 2 relay</td>
                        <td><span class="relay-off">OFF</span></td>
                    </tr>
                    <tr>
                        <td>D7</td>
                        <td>Heater fan relay</td>
                        <td><span class="relay-off">OFF</span></td>
                    </tr>
                    <tr>
                        <td>D1</td>
                        <td>Egg swing relay</td>
                        <td><span class="relay-off">OFF</span></td>
                    </tr>
                    <tr>
                        <td>D0</td>
                        <td>Exhaust relay</td>
                        <td><span class="relay-off">OFF</span></td>
                    </tr>
                    <tr>
                        <td><strong>Last Update</strong></td>
                        <td colspan="3">--</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="relay-section">
            <h2>🔌 Relay Control</h2>
            <div class="relay-grid">
                <!-- Heater Group (all heaters) -->
                <div class="relay-card">
                    <div class="relay-name">Heater (All)</div>
                    <div style="margin-bottom: 10px; color: #666; font-size: 0.9em;">
                        Status: <strong id="status-heater_group">✗ OFF</strong>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button id="btn-heater-on" type="button" class="btn-on" data-relay="heater" data-state="1">Turn
                            ON</button>
                        <button id="btn-heater-off" type="button" class="btn-off" data-relay="heater"
                            data-state="0">Turn OFF</button>
                    </div>
                </div>

                <!-- Heater Fan -->
                <div class="relay-card">
                    <div class="relay-name">Heater Fan (D7)</div>
                    <div style="margin-bottom: 10px; color: #666; font-size: 0.9em;">Status:
                        <strong id="status-heater_fan">✗ OFF</strong>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button id="btn-heater_fan-on" type="button" class="btn-on" data-relay="heater_fan"
                            data-state="1">ON</button>
                        <button id="btn-heater_fan-off" type="button" class="btn-off" data-relay="heater_fan"
                            data-state="0">OFF</button>
                    </div>
                </div>

                <!-- Heater 1 -->
                <div class="relay-card">
                    <div class="relay-name">Heater 1 (D5)</div>
                    <div style="margin-bottom: 10px; color: #666; font-size: 0.9em;">Status:
                        <strong id="status-heater_1">✗ OFF</strong>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button id="btn-heater_1-on" type="button" class="btn-on" data-relay="heater_1"
                            data-state="1">ON</button>
                        <button id="btn-heater_1-off" type="button" class="btn-off" data-relay="heater_1"
                            data-state="0">OFF</button>
                    </div>
                </div>

                <!-- Heater 2 -->
                <div class="relay-card">
                    <div class="relay-name">Heater 2 (D6)</div>
                    <div style="margin-bottom: 10px; color: #666; font-size: 0.9em;">Status:
                        <strong id="status-heater_2">✗ OFF</strong>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button id="btn-heater_2-on" type="button" class="btn-on" data-relay="heater_2"
                            data-state="1">ON</button>
                        <button id="btn-heater_2-off" type="button" class="btn-off" data-relay="heater_2"
                            data-state="0">OFF</button>
                    </div>
                </div>

                <!-- Egg Swing -->
                <div class="relay-card">
                    <div class="relay-name">Egg Swing (D1)</div>
                    <div style="margin-bottom: 10px; color: #666; font-size: 0.9em;">Status:
                        <strong id="status-eggswing">✗ OFF</strong>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button id="btn-eggswing-on" type="button" class="btn-on" data-relay="eggswing"
                            data-state="1">ON</button>
                        <button id="btn-eggswing-off" type="button" class="btn-off" data-relay="eggswing"
                            data-state="0">OFF</button>
                    </div>
                </div>

                <!-- Exhaust -->
                <div class="relay-card">
                    <div class="relay-name">Exhaust (D0)</div>
                    <div style="margin-bottom: 10px; color: #666; font-size: 0.9em;">Status:
                        <strong id="status-exhaust">✗ OFF</strong>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button id="btn-exhaust-on" type="button" class="btn-on" data-relay="exhaust"
                            data-state="1">ON</button>
                        <button id="btn-exhaust-off" type="button" class="btn-off" data-relay="exhaust"
                            data-state="0">OFF</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>GHOST Incubator Control System | Test Interface</p>
            <p>TEST MODE - Relay control & sensor monitoring</p>
        </div>
        <script>
        const API_URL = 'ajax/hardware_api.php';
        const INCUBATOR_ID = <?php echo $INCUBATOR_ID; ?>;

        function showMessage(success, text) {
            let msg = document.getElementById('ajaxMessage');
            if (!msg) {
                msg = document.createElement('div');
                msg.id = 'ajaxMessage';
                msg.className = 'message';
                const container = document.querySelector('.container');
                container.insertBefore(msg, container.children[1] || container.firstChild);
            }
            msg.textContent = text;
            msg.className = 'message show ' + (success ? 'success' : 'error');
            setTimeout(() => {
                msg.className = msg.className.replace('show', '').trim();
            }, 4000);
        }

        function sendSingleRelayCommand(relayName, state) {
            const params = new URLSearchParams({
                action: 'test_mode_set_relay',
                incubator_id: INCUBATOR_ID,
                relay: relayName,
                state: state ? '1' : '0'
            });

            console.log('[RELAY CMD] Sending single:', relayName, '=', state ? 'ON' : 'OFF');

            return fetch(API_URL, {
                    method: 'POST',
                    body: params,
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                })
                .then(r => r.json())
                .then(j => {
                    console.log('[RELAY CMD] Response:', relayName, j);
                    if (j.success) {
                        showMessage(true, 'OK: ' + relayName.replace(/_/g, ' ').toUpperCase() + ' → ' + (state ?
                            'ON' : 'OFF'));
                    } else {
                        showMessage(false, 'Error: ' + (j.message || 'Unknown error'));
                    }
                    return j;
                })
                .catch(e => {
                    console.error('[RELAY CMD] Fetch error:', e);
                    showMessage(false, 'Command failed: ' + e.message);
                    throw e;
                });
        }

        function sendRelayCommand(relayName, state) {
            // If the group heater is toggled, mirror all heater-related relays
            if (relayName === 'heater') {
                sendSingleRelayCommand('heater', state)
                    .then(j => {
                        if (j && j.success) {
                            // Keep per-relay status rows in sync with group command
                            return Promise.all([
                                sendSingleRelayCommand('heater_fan', state).catch(() => {}),
                                sendSingleRelayCommand('heater_1', state).catch(() => {}),
                                sendSingleRelayCommand('heater_2', state).catch(() => {})
                            ]);
                        }
                    })
                    .then(() => {
                        // Wait 500ms for ESP to process, then poll
                        setTimeout(() => fetchStatus(), 500);
                    })
                    .catch(() => fetchStatus());
                return;
            }

            // Default: send single relay command
            sendSingleRelayCommand(relayName, state)
                .then(() => {
                    // Wait 500ms for ESP to process, then poll
                    setTimeout(() => fetchStatus(), 500);
                })
                .catch(() => fetchStatus());
        }

        // Attach listeners to buttons using data attributes
        document.querySelectorAll('.btn-on, .btn-off').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const relayName = btn.getAttribute('data-relay');
                const state = parseInt(btn.getAttribute('data-state'));
                sendRelayCommand(relayName, state === 1);
            });
        });

        function fetchStatus() {
            const url = API_URL + '?action=test_mode_get_dht&incubator_id=' + INCUBATOR_ID;

            fetch(url)
                .then(r => r.json())
                .then(d => {
                    console.log('[DHT]', d);

                    // Update temperature and humidity
                    const tempEl = document.getElementById('tempValue');
                    const humEl = document.getElementById('humValue');
                    if (tempEl) tempEl.textContent = (d.temp !== undefined && d.temp !== null) ? Number(d.temp)
                        .toFixed(1) : '--';
                    if (humEl) humEl.textContent = (d.humidity !== undefined && d.humidity !== null) ? Number(d
                        .humidity).toFixed(1) : '--';
                })
                .catch(e => {
                    console.error('[DHT] Fetch failed:', e);
                });

            // Fetch relay status from database
            fetchRelayStatus();
        }

        function fetchRelayStatus() {
            const statusMap = {
                'heater': 'status-heater_group',
                'heater_fan': 'status-heater_fan',
                'heater_1': 'status-heater_1',
                'heater_2': 'status-heater_2',
                'eggswing': 'status-eggswing',
                'exhaust': 'status-exhaust'
            };

            // Map for button IDs to show/hide
            const buttonMap = {
                'heater': ['btn-heater-on', 'btn-heater-off'],
                'heater_fan': ['btn-heater_fan-on', 'btn-heater_fan-off'],
                'heater_1': ['btn-heater_1-on', 'btn-heater_1-off'],
                'heater_2': ['btn-heater_2-on', 'btn-heater_2-off'],
                'eggswing': ['btn-eggswing-on', 'btn-eggswing-off'],
                'exhaust': ['btn-exhaust-on', 'btn-exhaust-off']
            };

            const url = API_URL + '?action=test_mode_get_relay_status&incubator_id=' + INCUBATOR_ID;
            fetch(url)
                .then(r => r.json())
                .then(j => {
                    if (!j.success || !j.states) {
                        return;
                    }

                    Object.keys(statusMap).forEach(relay => {
                        const el = document.getElementById(statusMap[relay]);
                        if (!el) return;

                        const isOn = !!j.states[relay];
                        el.textContent = isOn ? '✓ ON' : '✗ OFF';
                        el.style.color = isOn ? '#2e7d32' : '#333';
                        el.dataset.updated = '1';

                        // Show/hide ON/OFF buttons for heater, heater_1, heater_2
                        if (buttonMap[relay]) {
                            const btnOn = document.getElementById(buttonMap[relay][0]);
                            const btnOff = document.getElementById(buttonMap[relay][1]);
                            if (btnOn && btnOff) {
                                if (isOn) {
                                    btnOn.style.display = 'none';
                                    btnOff.style.display = '';
                                } else {
                                    btnOn.style.display = '';
                                    btnOff.style.display = 'none';
                                }
                            }
                        }
                    });
                })
                .catch(e => {
                    console.error('[RELAY STATUS] Fetch failed:', e);
                });
        }

        // Poll status every 2 seconds
        setInterval(fetchStatus, 2000);
        
        // Poll health status every 3 seconds
        setInterval(fetchHealthStatus, 3000);
        
        fetchStatus();
        fetchHealthStatus();

        function fetchHealthStatus() {
            // Include device token so API authorizes dashboard requests
            const url = API_URL + '?action=get_health_status&incubator_id=' + INCUBATOR_ID + '&token=ghost_hw_secret_2024';
            console.log('[fetchHealthStatus] Calling:', url);

            fetch(url)
                .then(r => {
                    console.log('[fetchHealthStatus] Response status:', r.status);
                    return r.json();
                })
                .then(data => {
                    console.log('[fetchHealthStatus] Parsed JSON:', data);
                    if (!data.success) {
                        console.log('[fetchHealthStatus] API returned success=false:', data);
                        updateHealthUI(null);
                        return;
                    }

                    console.log('[HEALTH]', data);
                    updateHealthUI(data);
                })
                .catch(e => {
                    console.error('[HEALTH] Fetch failed:', e);
                    updateHealthUI(null);
                });
        }

        function updateHealthUI(health) {
            const healthElement = document.getElementById('healthStatus');
            
            // DEBUG: Log the exact data structure
            console.log('[updateHealthUI] Received health object:', health);
            console.log('[updateHealthUI] health.wifi_ssid:', health?.wifi_ssid);
            console.log('[updateHealthUI] health.device_ip:', health?.device_ip);
            console.log('[updateHealthUI] health.status_indicator:', health?.status_indicator);
            console.log('[updateHealthUI] health.relays:', health?.relays);
            
            if (!health || !health.relays) {
                console.log('[updateHealthUI] No health data or relays, showing offline');
                // Show offline state with placeholder table
                healthElement.innerHTML = `
                    <table>
                        <thead>
                            <tr>
                                <th colspan="4">🔄 Device Status & Relay Monitor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Status</strong></td>
                                <td colspan="2"><span class="status-badge status-offline">● OFFLINE</span></td>
                            </tr>
                            <tr>
                                <td><strong>WiFi SSID</strong></td>
                                <td colspan="2">--</td>
                            </tr>
                            <tr>
                                <td><strong>Device IP</strong></td>
                                <td colspan="2">--</td>
                            </tr>
                            <tr>
                                <td colspan="3" style="padding-top: 15px; padding-bottom: 5px;"><strong>Relay States</strong></td>
                            </tr>
                            <tr>
                                <td><strong>Pin</strong></td>
                                <td><strong>Name</strong></td>
                                <td><strong>State</strong></td>
                            </tr>
                            <tr><td>D5</td><td>Heater 1 relay</td><td><span class="relay-off">OFF</span></td></tr>
                            <tr><td>D6</td><td>Heater 2 relay</td><td><span class="relay-off">OFF</span></td></tr>
                            <tr><td>D7</td><td>Heater fan relay</td><td><span class="relay-off">OFF</span></td></tr>
                            <tr><td>D1</td><td>Egg swing relay</td><td><span class="relay-off">OFF</span></td></tr>
                            <tr><td>D0</td><td>Exhaust relay</td><td><span class="relay-off">OFF</span></td></tr>
                            <tr>
                                <td><strong>Last Update</strong></td>
                                <td colspan="2">--</td>
                            </tr>
                        </tbody>
                    </table>
                `;
                return;
            }

            // Calculate last seen
            let lastSeenText = '--';
            if (health.last_seen) {
                const lastSeenDate = new Date(health.last_seen);
                const now = new Date();
                const diffMs = now - lastSeenDate;
                const diffSecs = Math.floor(diffMs / 1000);
                
                if (diffSecs < 60) {
                    lastSeenText = `${diffSecs}s ago`;
                } else if (diffSecs < 3600) {
                    lastSeenText = `${Math.floor(diffSecs / 60)}m ago`;
                } else {
                    lastSeenText = `${Math.floor(diffSecs / 3600)}h ago`;
                }
            }

            // Status indicator with badge
            const statusClass = health.status_indicator === 'online' ? 'status-online' :
                               health.status_indicator === 'idle' ? 'status-idle' : 'status-offline';
            const statusText = health.status_indicator === 'online' ? '● ONLINE' :
                              health.status_indicator === 'idle' ? '⊙ IDLE' : '● OFFLINE';

            // WiFi info - Get from health response or fallback to dashes
            const wifiSSID = health.wifi_ssid || (health.last_seen ? 'Lia' : '--');
            const deviceIP = health.device_ip || '--';

            // Relay rows
            const relayOrder = [
                {key: 'heater_1', pin: 'D5', name: 'Heater 1 relay'},
                {key: 'heater_2', pin: 'D6', name: 'Heater 2 relay'},
                {key: 'heater_fan', pin: 'D7', name: 'Heater fan relay'},
                {key: 'eggswing', pin: 'D1', name: 'Egg swing relay'},
                {key: 'exhaust', pin: 'D0', name: 'Exhaust relay'}
            ];

            let relayRows = '';
            relayOrder.forEach(r => {
                const relay = health.relays[r.key];
                if (relay) {
                    const state = relay.state ? 'ON' : 'OFF';
                    const stateClass = relay.state ? 'relay-on' : 'relay-off';
                    relayRows += `<tr><td><strong>${r.pin}</strong></td><td>${r.name}</td><td><span class="${stateClass}">${state}</span></td></tr>`;
                } else {
                    // Show OFF for relays with no data
                    relayRows += `<tr><td><strong>${r.pin}</strong></td><td>${r.name}</td><td><span class="relay-off">OFF</span></td></tr>`;
                }
            });

            // Build table HTML
            const tableHTML = `
                <table>
                    <thead>
                        <tr>
                            <th colspan="3">🔄 Device Status & Relay Monitor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td colspan="2"><span class="status-badge ${statusClass}">${statusText}</span></td>
                        </tr>
                        <tr>
                            <td><strong>WiFi SSID</strong></td>
                            <td colspan="2">${wifiSSID}</td>
                        </tr>
                        <tr>
                            <td><strong>Device IP</strong></td>
                            <td colspan="2">${deviceIP}</td>
                        </tr>
                        <tr>
                            <td colspan="3" style="padding-top: 15px; padding-bottom: 5px;"><strong>Relay States</strong></td>
                        </tr>
                        <tr>
                            <td><strong>Pin</strong></td>
                            <td><strong>Name</strong></td>
                            <td><strong>State</strong></td>
                        </tr>
                        ${relayRows}
                        <tr>
                            <td><strong>Last Update</strong></td>
                            <td colspan="2">${lastSeenText}</td>
                        </tr>
                    </tbody>
                </table>
            `;

            healthElement.innerHTML = tableHTML;
        }
        </script>
    </div>
</body>

</html>