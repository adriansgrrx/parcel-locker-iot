#include <WiFi.h>
#include <Firebase_ESP_Client.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// Firebase credentials
#define WIFI_SSID "Adrian"
#define WIFI_PASSWORD "eds1234567"
#define API_KEY "AIzaSyBWJ_fJ_1xfnyL5bQ5rrDUJkowKhmHQ_M4"
#define DATABASE_URL "https://parcel-box-iot-default-rtdb.asia-southeast1.firebasedatabase.app/"
#define USER_EMAIL "i.am.adriansgrrx@gmail.com"
#define USER_PASSWORD "parcel-box-iot"

// Firebase objects
FirebaseData fbdo;
FirebaseAuth auth;
FirebaseConfig config;

// LCD setup
LiquidCrystal_I2C lcd(0x27, 16, 2);

// Pins
const int trigPin1 = 5, echoPin1 = 18;
const int trigPin2 = 17, echoPin2 = 16;
const int buttonPin = 4, resetButtonPin = 15;
const int solenoidPin = 26, buzzerPin = 27;

// Constants
const float PARCEL_THRESHOLD = 10.0;

// Variables
float distance1, distance2;
bool isParcelDetected1, isParcelDetected2;
bool isSecurityModeActivated1 = false;
bool isSecurityModeActivated2 = false;
bool isPermissionAllowed;
bool isResetPressed;
String status1, status2;

float getDistance(int trigPin, int echoPin) {
    digitalWrite(trigPin, LOW);
    delayMicroseconds(2);
    digitalWrite(trigPin, HIGH);
    delayMicroseconds(10);
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

void setup() {
    Serial.begin(115200);
    
    pinMode(trigPin1, OUTPUT); pinMode(echoPin1, INPUT);
    pinMode(trigPin2, OUTPUT); pinMode(echoPin2, INPUT);
    pinMode(buttonPin, INPUT_PULLUP);
    pinMode(resetButtonPin, INPUT_PULLUP);
    pinMode(solenoidPin, OUTPUT); digitalWrite(solenoidPin, LOW);
    pinMode(buzzerPin, OUTPUT); digitalWrite(buzzerPin, LOW);

    lcd.init(); lcd.backlight(); lcd.clear();
    lcd.setCursor(0, 0); lcd.print("System Starting...");
    delay(2000); lcd.clear();

    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    while (WiFi.status() != WL_CONNECTED) { delay(300); Serial.print("."); }
    Serial.println("\nConnected to WiFi");

    config.api_key = API_KEY;
    auth.user.email = USER_EMAIL;
    auth.user.password = USER_PASSWORD;
    config.database_url = DATABASE_URL;
    Firebase.reconnectNetwork(true);
    fbdo.setBSSLBufferSize(4096, 1024);
    fbdo.setResponseSize(2048);
    Firebase.begin(&config, &auth);
    Firebase.setDoubleDigits(2);
    config.timeout.serverResponse = 10000;
}

void logEvent(String type, String cid, String status, bool permission, bool reset, float dist) {
    String path = "/events_log";
    FirebaseJson log;
    log.set("event_type", type);
    log.set("compartment_id", cid);
    log.set("status", status);
    log.set("permission_granted", permission);
    log.set("reset_triggered", reset);
    log.set("distance_cm", dist);
    log.set("timestamp/.sv", "timestamp");
    Firebase.RTDB.pushJSON(&fbdo, path.c_str(), &log);
}

void updateSystemStatus(bool permission, bool reset, bool buzzerOn) {
    FirebaseJson json;
    json.set("solenoid_state", permission ? "OPEN" : "LOCKED");
    json.set("buzzer_state", buzzerOn ? "ON" : "OFF");
    json.set("last_permission", permission);
    json.set("last_reset", reset);
    json.set("updated_at/.sv", "timestamp");
    Firebase.RTDB.setJSON(&fbdo, "/system_status", &json);
}

void updateCompartment(String id, float dist, bool parcel, bool security, String status) {
    String path = "/compartments/" + id;
    FirebaseJson json;
    json.set("compartment_id", id);
    json.set("distance_cm", dist);
    json.set("is_parcel_detected", parcel);
    json.set("is_security_mode", security);
    json.set("status", status);
    json.set("timestamp/.sv", "timestamp");
    Firebase.RTDB.setJSON(&fbdo, path.c_str(), &json);
}

void loop() {
    distance1 = getDistance(trigPin1, echoPin1);
    distance2 = getDistance(trigPin2, echoPin2);

    isParcelDetected1 = distance1 < PARCEL_THRESHOLD;
    isParcelDetected2 = distance2 < PARCEL_THRESHOLD;

    if (isParcelDetected1) isSecurityModeActivated1 = true;
    if (isParcelDetected2) isSecurityModeActivated2 = true;

    isPermissionAllowed = digitalRead(buttonPin) == LOW;
    isResetPressed = digitalRead(resetButtonPin) == LOW;

    if (isResetPressed || isPermissionAllowed) {
        isSecurityModeActivated1 = false;
        isSecurityModeActivated2 = false;
    }

    digitalWrite(solenoidPin, isPermissionAllowed ? HIGH : LOW);

    status1 = determineStatus(isSecurityModeActivated1, isParcelDetected1, isPermissionAllowed);
    status2 = determineStatus(isSecurityModeActivated2, isParcelDetected2, isPermissionAllowed);

    bool buzzerOn = (status1 == "Theft" || status2 == "Theft");
    digitalWrite(buzzerPin, buzzerOn ? HIGH : LOW);

    updateCompartment("C1", distance1, isParcelDetected1, isSecurityModeActivated1, status1);
    updateCompartment("C2", distance2, isParcelDetected2, isSecurityModeActivated2, status2);

    updateSystemStatus(isPermissionAllowed, isResetPressed, buzzerOn);

    if (isParcelDetected1 || isParcelDetected2 || isResetPressed) {
        logEvent("parcel_detected", isParcelDetected1 ? "C1" : "C2", isParcelDetected1 ? status1 : status2, isPermissionAllowed, isResetPressed, isParcelDetected1 ? distance1 : distance2);
    }

    lcd.clear();
    lcd.setCursor(0, 0); lcd.print("C1: "); lcd.print(status1);
    lcd.setCursor(0, 1); lcd.print("C2: "); lcd.print(status2);

    delay(1000);
}
