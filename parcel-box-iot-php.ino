#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// WiFi Configuration
const char WIFI_SSID[] = "Adrian";     // CHANGE IT
const char WIFI_PASSWORD[] = "eds1234567";          // CHANGE IT
String HOST_NAME = "http://172.20.10.2";        // Your server IP
String PATH_NAME = "parcel-box/index.php";     // API endpoint

// LCD setup
LiquidCrystal_I2C lcd(0x27, 16, 2);

// Ultrasonic sensor pins for Compartment 1
const int trigPin1 = 5;
const int echoPin1 = 18;

// Ultrasonic sensor pins for Compartment 2
const int trigPin2 = 17;
const int echoPin2 = 16;

// Button pins
const int buttonPin = 4;         // Permission button (for retrieval)
const int resetButtonPin = 15;   // Reset security mode

// Solenoid control pin
const int solenoidPin = 26;      // Solenoid output control

// Buzzer pin
const int buzzerPin = 27;        // 🔔 Connect buzzer to GPIO 27

// Distance threshold (in cm)
const float PARCEL_THRESHOLD = 10.0;

// Variables
float distance1, distance2;
bool isParcelDetected1, isParcelDetected2;
bool isSecurityModeActivated1 = false;
bool isSecurityModeActivated2 = false;
bool isPermissionAllowed;
String status1, status2;

// WiFi and timing variables
unsigned long lastDataSend = 0;
const unsigned long DATA_SEND_INTERVAL = 5000; // Send data every 5 seconds
bool wifiConnected = false;

// Function to get distance from ultrasonic sensor
float getDistance(int trigPin, int echoPin) {
    digitalWrite(trigPin, LOW);
    delayMicroseconds(2);
    digitalWrite(trigPin, HIGH);
    delayMicroseconds(10);
    digitalWrite(trigPin, LOW);
    long duration = pulseIn(echoPin, HIGH);
    return duration * 0.0343 / 2;
}

// Logic table for status
String determineStatus(bool isSecurityMode, bool isParcelDetected, bool isPermissionAllowed) {
    if (!isSecurityMode && !isParcelDetected) return "Empty";
    if (!isSecurityMode && isParcelDetected) return "Occupied";
    if (isSecurityMode && !isParcelDetected && !isPermissionAllowed) return "Theft";
    if (isSecurityMode && !isParcelDetected && isPermissionAllowed) return "Retrieved";
    if (isSecurityMode && isParcelDetected) return "Occupied";
    return "Error";
}

// WiFi connection function
void connectToWiFi() {
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    Serial.print("Connecting to WiFi");
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
        delay(500);
        Serial.print(".");
        attempts++;
    }
    
    if (WiFi.status() == WL_CONNECTED) {
        wifiConnected = true;
        Serial.println();
        Serial.println("WiFi connected successfully!");
        Serial.print("IP address: ");
        Serial.println(WiFi.localIP());
        
        // Display on LCD
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("WiFi Connected");
        lcd.setCursor(0, 1);
        lcd.print(WiFi.localIP());
        delay(2000);
    } else {
        wifiConnected = false;
        Serial.println();
        Serial.println("WiFi connection failed!");
        
        // Display on LCD
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("WiFi Failed");
        delay(2000);
    }
}

// Function to send data to server
void sendDataToServer() {
    if (!wifiConnected || WiFi.status() != WL_CONNECTED) {
        Serial.println("WiFi not connected, skipping data send");
        return;
    }
    
    HTTPClient http;
    http.begin(HOST_NAME + PATH_NAME);
    http.addHeader("Content-Type", "application/json");
    
    // Create JSON payload
    DynamicJsonDocument doc(1024);
    
    // Compartment 1 data
    JsonObject c1 = doc.createNestedObject("c1");
    c1["distance_cm"] = distance1;
    c1["is_parcel_detected"] = isParcelDetected1;
    c1["is_security_mode"] = isSecurityModeActivated1;
    c1["status"] = status1;
    
    // Add event type if status changed significantly
    if (status1 == "Theft") {
        c1["event_type"] = "theft_detected";
    } else if (status1 == "Occupied" && isSecurityModeActivated1) {
        c1["event_type"] = "parcel_deposited";
    } else if (status1 == "Retrieved") {
        c1["event_type"] = "parcel_retrieved";
    }
    
    // Compartment 2 data
    JsonObject c2 = doc.createNestedObject("c2");
    c2["distance_cm"] = distance2;
    c2["is_parcel_detected"] = isParcelDetected2;
    c2["is_security_mode"] = isSecurityModeActivated2;
    c2["status"] = status2;
    
    // Add event type if status changed significantly
    if (status2 == "Theft") {
        c2["event_type"] = "theft_detected";
    } else if (status2 == "Occupied" && isSecurityModeActivated2) {
        c2["event_type"] = "parcel_deposited";
    } else if (status2 == "Retrieved") {
        c2["event_type"] = "parcel_retrieved";
    }
    
    // System status
    JsonObject system = doc.createNestedObject("system_status");
    system["solenoid_state"] = isPermissionAllowed ? "UNLOCKED" : "LOCKED";
    system["buzzer_state"] = (status1 == "Theft" || status2 == "Theft") ? "ON" : "OFF";
    system["last_permission"] = isPermissionAllowed;
    system["last_reset"] = digitalRead(resetButtonPin) == LOW;
    
    // Convert to string
    String jsonString;
    serializeJson(doc, jsonString);
    
    Serial.println("Sending data to server:");
    Serial.println(jsonString);
    
    // Send POST request
    int httpResponseCode = http.POST(jsonString);
    
    if (httpResponseCode > 0) {
        String response = http.getString();
        Serial.println("Server response code: " + String(httpResponseCode));
        Serial.println("Server response: " + response);
        
        // Parse response to check for remote commands
        DynamicJsonDocument responseDoc(512);
        if (deserializeJson(responseDoc, response) == DeserializationError::Ok) {
            if (responseDoc["success"] == true) {
                Serial.println("Data sent successfully!");
            }
        }
    } else {
        Serial.println("Error sending data: " + String(httpResponseCode));
    }
    
    http.end();
}

// Function to check for remote commands
void checkRemoteCommands() {
    if (!wifiConnected || WiFi.status() != WL_CONNECTED) {
        return;
    }
    
    HTTPClient http;
    http.begin(HOST_NAME + "/api/get_status");
    
    int httpResponseCode = http.GET();
    
    if (httpResponseCode == 200) {
        String response = http.getString();
        
        DynamicJsonDocument doc(2048);
        if (deserializeJson(doc, response) == DeserializationError::Ok) {
            
            // Check for remote permission
            if (doc["system_status"]["last_permission"] == true) {
                isPermissionAllowed = true;
                Serial.println("Remote permission granted!");
            }
            
            // Check for remote reset
            if (doc["system_status"]["last_reset"] == true) {
                isSecurityModeActivated1 = false;
                isSecurityModeActivated2 = false;
                Serial.println("Remote reset triggered!");
            }
        }
    }
    
    http.end();
}

void setup() {
    Serial.begin(115200);

    pinMode(trigPin1, OUTPUT); pinMode(echoPin1, INPUT);
    pinMode(trigPin2, OUTPUT); pinMode(echoPin2, INPUT);

    pinMode(buttonPin, INPUT_PULLUP);
    pinMode(resetButtonPin, INPUT_PULLUP);
    pinMode(solenoidPin, OUTPUT);
    pinMode(buzzerPin, OUTPUT);
    digitalWrite(solenoidPin, LOW);
    digitalWrite(buzzerPin, LOW);

    lcd.init();
    lcd.backlight();
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("System Starting...");
    delay(2000);
    
    // Connect to WiFi
    connectToWiFi();
    
    lcd.clear();
}

void loop() {
    // Check WiFi connection
    if (WiFi.status() != WL_CONNECTED && wifiConnected) {
        wifiConnected = false;
        Serial.println("WiFi connection lost!");
    } else if (WiFi.status() == WL_CONNECTED && !wifiConnected) {
        wifiConnected = true;
        Serial.println("WiFi connection restored!");
    }
    
    // Get sensor readings
    distance1 = getDistance(trigPin1, echoPin1);
    distance2 = getDistance(trigPin2, echoPin2);

    // Determine parcel presence
    isParcelDetected1 = distance1 < PARCEL_THRESHOLD;
    isParcelDetected2 = distance2 < PARCEL_THRESHOLD;

    // Latch security mode ON if parcel is ever detected
    if (isParcelDetected1) isSecurityModeActivated1 = true;
    if (isParcelDetected2) isSecurityModeActivated2 = true;

    // Read buttons
    bool localPermission = digitalRead(buttonPin) == LOW;
    bool isResetPressed = digitalRead(resetButtonPin) == LOW;
    
    // Combine local and remote permissions
    if (localPermission) {
        isPermissionAllowed = true;
    }

    // Manual reset via reset button
    if (isResetPressed) {
        isSecurityModeActivated1 = false;
        isSecurityModeActivated2 = false;
        isPermissionAllowed = false;
        Serial.println(">> Reset Button Pressed: Security Modes Reset!");
    }

    // Auto reset when permission is granted
    if (isPermissionAllowed) {
        isSecurityModeActivated1 = false;
        isSecurityModeActivated2 = false;
        Serial.println(">> Auto Reset: Security Modes Reset via Permission!");
        
        // Reset permission flag after use
        delay(1000); // Keep unlocked for 1 second
        isPermissionAllowed = false;
    }

    // Solenoid control
    digitalWrite(solenoidPin, isPermissionAllowed ? HIGH : LOW);

    // Get compartment statuses
    status1 = determineStatus(isSecurityModeActivated1, isParcelDetected1, isPermissionAllowed);
    status2 = determineStatus(isSecurityModeActivated2, isParcelDetected2, isPermissionAllowed);

    // 🔔 Trigger buzzer if theft is detected
    if (status1 == "Theft" || status2 == "Theft") {
        digitalWrite(buzzerPin, HIGH);
        Serial.println("⚠️ Buzzer: ON (Theft Detected)");
    } else {
        digitalWrite(buzzerPin, LOW);
    }

    // Send data to server periodically
    if (millis() - lastDataSend >= DATA_SEND_INTERVAL) {
        sendDataToServer();
        checkRemoteCommands();
        lastDataSend = millis();
    }

    // SERIAL DEBUG LOG
    Serial.println("===== DEBUG LOG =====");
    Serial.print("WiFi Status: ");
    Serial.println(wifiConnected ? "Connected" : "Disconnected");

    Serial.print("C1 - Distance: ");
    Serial.print(distance1);
    Serial.print(" cm | Parcel: ");
    Serial.print(isParcelDetected1);
    Serial.print(" | Security: ");
    Serial.print(isSecurityModeActivated1);
    Serial.print(" | Status: ");
    Serial.println(status1);

    Serial.print("C2 - Distance: ");
    Serial.print(distance2);
    Serial.print(" cm | Parcel: ");
    Serial.print(isParcelDetected2);
    Serial.print(" | Security: ");
    Serial.print(isSecurityModeActivated2);
    Serial.print(" | Status: ");
    Serial.println(status2);

    Serial.print("Permission Allowed: ");
    Serial.println(isPermissionAllowed ? "YES" : "NO");

    Serial.print("Reset Button Pressed: ");
    Serial.println(isResetPressed ? "YES" : "NO");

    Serial.println("======================\n");

    // LCD Display
    lcd.clear();
    lcd.setCursor(0, 0); 
    lcd.print("C1:"); lcd.print(status1.substring(0, 6));
    if (wifiConnected) {
        lcd.print(" WiFi");
    } else {
        lcd.print(" NoWiFi");
    }
    lcd.setCursor(0, 1); 
    lcd.print("C2:"); lcd.print(status2.substring(0, 6));

    delay(1000);
}