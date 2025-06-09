CREATE TABLE control_flags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    permission_granted BOOLEAN NOT NULL DEFAULT 0,
    reset_triggered BOOLEAN NOT NULL DEFAULT 0
);

INSERT INTO control_flags (permission_granted, reset_triggered) VALUES (0, 0);
