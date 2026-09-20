<?php
require_once __DIR__ . '/config.php';

echo "Running migration: requests table...\n";

$sql = "CREATE TABLE IF NOT EXISTS `requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `employee_id` INT(11) NOT NULL,
  `type` ENUM('skill', 'certification', 'training') NOT NULL,
  `target_id` INT(11) DEFAULT NULL,
  `title` VARCHAR(150) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT(11) DEFAULT NULL,
  `review_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "[SUCCESS] 'requests' table created or already exists.\n";
} else {
    echo "[ERROR] Failed to create 'requests' table: " . $conn->error . "\n";
}
