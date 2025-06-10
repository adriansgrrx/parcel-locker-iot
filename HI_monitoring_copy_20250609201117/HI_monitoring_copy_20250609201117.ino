#include <WiFi.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <ArduinoJson.h>

// Wi-Fi credentials
const char WIFI_SSID[] = "PSD Tenant";     // CHANGE IT
const char WIFI_PASSWORD[] = "PSD1ntern3t";         // CHANGE IT

// Server settings
String HOST_NAME = "http://10.10.1.80";       // Your PC/server IP
String PATH_NAME = "parcel-box/index.php";                  // Your PHP file name
String SERVER_URL = HOST_NAME + "/" + PATH_NAME;

// LCD setup
LiquidCrystal_I2C lcd(0x27, 16, 2);

// Ultrasonic sensor pins
const int trigPin1 = 5, echoPin1 = 18;
const int trigPin2 = 17, echoPin2 = 16;

// Button and actuator pins
const int alertButtonPin = 4;
const int submitButtonPin = 15;
const int solenoidPin = 26;
const int buzzerPin = 27;

// Threshold
const float PARCEL_THRESHOLD = 10.0;

// States
float distance1, distance2;
bool isParcelDetected1, isParcelDetected2;
bool isSecurityModeActivated1 = false;
bool isSecurityModeActivated2 = false;
bool isPermissionAllowed;
bool isResetPressed;
String status1, status2;

// ---------- Function Declarations ----------

float getDistance(int trigPin, int echoPin) {
    digitalWrite(trigPin, LOW); delayMicroseconds(2);
    digitalWrite(trigPin, HIGH); delayMicroseconds(10);
    digitalWrite(trigPin, LOW);
    long duration = pulseIn(echoPin, HIGH);
    return duration * 0.0343 / 2;
}

String determineStatus(bool isSecurityMode, bool isParcelDetected, bool isPermissionAllowed) {
    if (!isSecurityMode && !isParcelDetected) return "Empty";
    if (!isSecurityMode && isParcelDetected) return "Occupied";
    if (isSecurityMode && !isParcelDetected && !isPermissionAllowed) return "Theft";
    if (isSecurityMode && !isParcelDetected && isPermissionAllowed) return "Retrieved";
    if (isSecurityMode && isParcelDetected) return "Occupied";
    return "Error";
}

void sendToServer(String compId, float dist, bool detected, bool security, String status, String eventType, bool permission, bool reset) {
    if (WiFi.status() == WL_CONNECTED) {
        HTTPClient http;
        http.begin(SERVER_URL);
        http.addHeader("Content-Type", "application/x-www-form-urlencoded");

        String postData = "compartment_id=" + compId +
                          "&distance_cm=" + String(dist, 2) +
                          "&is_parcel_detected=" + String(detected ? "true" : "false") +
                          "&is_security_mode=" + String(security ? "true" : "false") +
                          "&status=" + status +
                          "&event_type=" + eventType +
                          "&permission_granted=" + String(permission ? "true" : "false") +
                          "&reset_triggered=" + String(reset ? "true" : "false");

        int httpCode = http.POST(postData);
        String response = http.getString();

        Serial.print("HTTP Response code: ");
        Serial.println(httpCode);
        Serial.println("Server response: " + response);
        http.end();
    } else {
        Serial.println("WiFi not connected.");
    }
}

void updateControlFlag(String flagType) {
    if (WiFi.status() == WL_CONNECTED) {
        HTTPClient http;
        http.begin(SERVER_URL);
        http.addHeader("Content-Type", "application/x-www-form-urlencoded");

        String action = (flagType == "submitted_delivery") ? "toggle_submitted_delivery" :
                        (flagType == "alert_user") ? "toggle_alert_user" : "";
        if (action == "") return; // Invalid flag type

        String postData = "action=" + action + "&value=on";

        int httpCode = http.POST(postData);
        String response = http.getString();

        Serial.print("Flag Update HTTP Code: ");
        Serial.println(httpCode);
        Serial.println("Flag Update Response: " + response);

        http.end();
    } else {
        Serial.println("WiFi not connected - cannot update control flag.");
    }
}



// ---------- Setup ----------

void setup() {
    Serial.begin(115200);

    // Init pins
    pinMode(trigPin1, OUTPUT); pinMode(echoPin1, INPUT);
    pinMode(trigPin2, OUTPUT); pinMode(echoPin2, INPUT);
    pinMode(alertButtonPin, INPUT_PULLUP);
    pinMode(submitButtonPin, INPUT_PULLUP);
    pinMode(solenoidPin, OUTPUT);
    pinMode(buzzerPin, OUTPUT);

    digitalWrite(solenoidPin, LOW); // Locked by default
    digitalWrite(buzzerPin, LOW);

    // Init LCD
    lcd.init(); lcd.backlight(); lcd.clear();
    lcd.setCursor(0, 0); lcd.print("System Starting...");
    delay(2000); lcd.clear();

    // Connect Wi-Fi
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    Serial.print("Connecting to WiFi");
    while (WiFi.status() != WL_CONNECTED) {
        delay(500); Serial.print(".");
    }
    Serial.println("\nConnected!");

    // Initial event log
    sendToServer("C1", 0.0, false, false, "Empty", "system_startup", false, false);
    sendToServer("C2", 0.0, false, false, "Empty", "system_startup", false, false);
}

// ---------- Loop ----------

void loop() {
    // Read sensors
    distance1 = getDistance(trigPin1, echoPin1);
    distance2 = getDistance(trigPin2, echoPin2);

    isParcelDetected1 = distance1 < PARCEL_THRESHOLD;
    isParcelDetected2 = distance2 < PARCEL_THRESHOLD;

    if (isParcelDetected1) isSecurityModeActivated1 = true;
    if (isParcelDetected2) isSecurityModeActivated2 = true;

    if (WiFi.status() == WL_CONNECTED) {
        HTTPClient http;
        http.begin(SERVER_URL + "?get_control_flags=1"); // Properly formatted URL
        int httpCode = http.GET();

        if (httpCode == 200) {
            String response = http.getString();
            Serial.println("Control Response: " + response);

            DynamicJsonDocument doc(1024);
            DeserializationError error = deserializeJson(doc, response);
            if (!error) {
                isPermissionAllowed = doc["permission_granted"];
                isResetPressed = doc["reset_triggered"];
            } else {
                Serial.println("Failed to parse control JSON.");
            }
        } else {
            Serial.printf("GET request failed, error: %s\n", http.errorToString(httpCode).c_str());
        }
        http.end();
    } else {
        Serial.println("WiFi not connected.");
    }


    // Reset logic
    if (isResetPressed || isPermissionAllowed) {
        isSecurityModeActivated1 = false;
        isSecurityModeActivated2 = false;
        Serial.println("Security reset triggered.");
    }

    // Handle button press to update flags
    if (digitalRead(submitButtonPin) == LOW) {
        Serial.println("Submit button pressed.");
        updateControlFlag("submitted_delivery");
        delay(500); // Debounce delay
    }

    if (digitalRead(alertButtonPin) == LOW) {
        Serial.println("Alert button pressed.");
        updateControlFlag("alert_user");
        delay(500); // Debounce delay
    }


    // Solenoid
    digitalWrite(solenoidPin, isPermissionAllowed ? HIGH : LOW);
    Serial.println(isPermissionAllowed ? "Solenoid: OPEN" : "Solenoid: LOCKED");

    // Status
    status1 = determineStatus(isSecurityModeActivated1, isParcelDetected1, isPermissionAllowed);
    status2 = determineStatus(isSecurityModeActivated2, isParcelDetected2, isPermissionAllowed);

    // Buzzer
    if (status1 == "Theft" || status2 == "Theft") {
        digitalWrite(buzzerPin, HIGH);
        Serial.println("Buzzer ON - Theft Detected");
    } else {
        digitalWrite(buzzerPin, LOW);
    }

    // Log to Server
    sendToServer("C1", distance1, isParcelDetected1, isSecurityModeActivated1, status1,
                (status1 == "Theft") ? "theft_alert" :
                (isParcelDetected1 ? "parcel_detected" : "parcel_removed"),
                isPermissionAllowed, isResetPressed);

    sendToServer("C2", distance2, isParcelDetected2, isSecurityModeActivated2, status2,
                (status2 == "Theft") ? "theft_alert" :
                (isParcelDetected2 ? "parcel_detected" : "parcel_removed"),
                isPermissionAllowed, isResetPressed);

    // LCD
    lcd.clear();
    lcd.setCursor(0, 0); lcd.print("C1: "); lcd.print(status1);
    lcd.setCursor(0, 1); lcd.print("C2: "); lcd.print(status2);

    delay(1000);
}
