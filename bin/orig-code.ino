#include <Wire.h>
#include <LiquidCrystal_I2C.h>

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

void setup() {
    Serial.begin(115200);

    pinMode(trigPin1, OUTPUT); pinMode(echoPin1, INPUT);
    pinMode(trigPin2, OUTPUT); pinMode(echoPin2, INPUT);

    pinMode(buttonPin, INPUT_PULLUP);
    pinMode(resetButtonPin, INPUT_PULLUP);
    pinMode(solenoidPin, OUTPUT);
    pinMode(buzzerPin, OUTPUT);                 // 🔔 Buzzer pin setup
    digitalWrite(solenoidPin, LOW);             // Default to locked
    digitalWrite(buzzerPin, LOW);               // Ensure buzzer is off at start

    lcd.init();
    lcd.backlight();
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("System Starting...");
    delay(2000);
    lcd.clear();
}

void loop() {
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
    isPermissionAllowed = digitalRead(buttonPin) == LOW;
    bool isResetPressed = digitalRead(resetButtonPin) == LOW;

    // Manual reset via reset button
    if (isResetPressed) {
        isSecurityModeActivated1 = false;
        isSecurityModeActivated2 = false;
        Serial.println(">> Reset Button Pressed: Security Modes Reset!");
    }

    // Auto reset when permission is granted
    if (isPermissionAllowed) {
        isSecurityModeActivated1 = false;
        isSecurityModeActivated2 = false;
        Serial.println(">> Auto Reset: Security Modes Reset via Permission Button!");
    }

    // Solenoid control
    digitalWrite(solenoidPin, isPermissionAllowed ? HIGH : LOW);
    Serial.println(isPermissionAllowed ? "Solenoid: OPEN (Permission Granted)" : "Solenoid: LOCKED (Permission Denied)");

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

    // SERIAL DEBUG LOG
    Serial.println("===== DEBUG LOG =====");

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
    lcd.setCursor(0, 0); lcd.print("C1: "); lcd.print(status1);
    lcd.setCursor(0, 1); lcd.print("C2: "); lcd.print(status2);

    delay(1000);
}