
-- Create database
CREATE DATABASE IF NOT EXISTS skill_compass;
USE skill_compass;

-- Table structure for table `users`
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'hr', 'manager', 'employee') NOT NULL,
  `department` VARCHAR(50) DEFAULT NULL,
  `job_title` VARCHAR(100) DEFAULT NULL,
  `hire_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `skills`
CREATE TABLE IF NOT EXISTS `skills` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `category` VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `employee_skills`
CREATE TABLE IF NOT EXISTS `employee_skills` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `employee_id` INT(11) NOT NULL,
  `skill_id` INT(11) NOT NULL,
  `proficiency` INT(11) DEFAULT 0,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_skill` (`employee_id`, `skill_id`),
  FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `trainings`
CREATE TABLE IF NOT EXISTS `trainings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `employee_id` INT(11) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE,
  `status` ENUM('Planned', 'In Progress', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Planned',
  `related_skill_id` INT(11),
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`related_skill_id`) REFERENCES `skills` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `certifications`
CREATE TABLE IF NOT EXISTS `certifications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `employee_id` INT(11) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `issuing_body` VARCHAR(100),
  `issue_date` DATE,
  `expiry_date` DATE,
  `certificate_url` VARCHAR(255),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user
-- Default password is 'admin123' (hashed)
INSERT INTO `users` (`username`, `password`, `first_name`, `last_name`, `email`, `role`)
VALUES ('admin', '$2y$10$8Y4vKYAMUQ1k9QyvHpJ7SeEA76M6rDZu3XF4oFYwH9jb.Fz5kyYfi', 'System', 'Administrator', 'admin@example.com', 'admin');

-- Insert HR user
-- Default password is 'hr123' (hashed)
INSERT INTO `users` (`username`, `password`, `first_name`, `last_name`, `email`, `role`, `department`, `job_title`)
VALUES ('hrmanager', '$2y$10$CfT2oaUkNZ4QZ58KkrU2L.lNIX3MB7.a6Qitf53EZiTZEBQPPF1Na', 'HR', 'Manager', 'hr@example.com', 'hr', 'Human Resources', 'HR Manager');

-- Insert sample employees
INSERT INTO `users` (`username`, `password`, `first_name`, `last_name`, `email`, `role`, `department`, `job_title`, `hire_date`)
VALUES 
('jdoe', '$2y$10$XyH2ySGTVP3vQXGlqYg6UOGIu9AiyDjPRSHlJKxS03hFE8g9Xjko.', 'John', 'Doe', 'john.doe@example.com', 'employee', 'Engineering', 'Software Engineer', '2021-06-15'),
('jsmith', '$2y$10$XyH2ySGTVP3vQXGlqYg6UOGIu9AiyDjPRSHlJKxS03hFE8g9Xjko.', 'Jane', 'Smith', 'jane.smith@example.com', 'employee', 'Marketing', 'Marketing Specialist', '2022-03-10'),
('mwilson', '$2y$10$XyH2ySGTVP3vQXGlqYg6UOGIu9AiyDjPRSHlJKxS03hFE8g9Xjko.', 'Mike', 'Wilson', 'mike.wilson@example.com', 'employee', 'Engineering', 'DevOps Engineer', '2020-11-22'),
('lbrown', '$2y$10$XyH2ySGTVP3vQXGlqYg6UOGIu9AiyDjPRSHlJKxS03hFE8g9Xjko.', 'Lisa', 'Brown', 'lisa.brown@example.com', 'employee', 'Product', 'Product Manager', '2021-09-05');

-- Insert sample manager
INSERT INTO `users` (`username`, `password`, `first_name`, `last_name`, `email`, `role`, `department`, `job_title`, `hire_date`)
VALUES ('rjohnson', '$2y$10$XyH2ySGTVP3vQXGlqYg6UOGIu9AiyDjPRSHlJKxS03hFE8g9Xjko.', 'Robert', 'Johnson', 'robert.johnson@example.com', 'manager', 'Engineering', 'Engineering Manager', '2019-07-15');

-- Insert sample skills
INSERT INTO `skills` (`name`, `description`, `category`)
VALUES 
('JavaScript', 'Programming language for web development', 'Programming'),
('Python', 'General purpose programming language', 'Programming'),
('MySQL', 'Relational database management system', 'Database'),
('Project Management', 'Planning, organizing, and overseeing projects', 'Management'),
('Digital Marketing', 'Marketing of products/services using digital channels', 'Marketing'),
('UI/UX Design', 'Creating user-centered designs for digital products', 'Design'),
('AWS', 'Amazon Web Services cloud computing platform', 'Cloud'),
('Docker', 'Platform for developing, shipping, and running applications', 'DevOps'),
('Kubernetes', 'Container orchestration system', 'DevOps'),
('Content Writing', 'Creating content for web pages, blogs, etc.', 'Marketing');

-- Assign skills to employees
INSERT INTO `employee_skills` (`employee_id`, `skill_id`, `proficiency`)
VALUES 
-- John Doe (Software Engineer)
(3, 1, 90), -- JavaScript
(3, 2, 75), -- Python
(3, 3, 80), -- MySQL
(3, 7, 65), -- AWS

-- Jane Smith (Marketing Specialist)
(4, 5, 95), -- Digital Marketing
(4, 10, 90), -- Content Writing
(4, 6, 70), -- UI/UX Design

-- Mike Wilson (DevOps Engineer)
(5, 7, 90), -- AWS
(5, 8, 95), -- Docker
(5, 9, 85), -- Kubernetes
(5, 2, 70), -- Python

-- Lisa Brown (Product Manager)
(6, 4, 95), -- Project Management
(6, 6, 85), -- UI/UX Design
(6, 5, 75); -- Digital Marketing

-- Insert sample certifications
INSERT INTO `certifications` (`employee_id`, `name`, `description`, `issuing_body`, `issue_date`, `expiry_date`)
VALUES 
(3, 'AWS Certified Developer', 'Associate level certification for AWS development', 'Amazon Web Services', '2022-06-15', '2025-06-15'),
(5, 'Certified Kubernetes Administrator', 'Expert level certification for Kubernetes', 'Cloud Native Computing Foundation', '2022-03-10', '2025-03-10'),
(4, 'Google Analytics Certification', 'Certification for web analytics', 'Google', '2022-08-22', '2024-08-22'),
(6, 'Project Management Professional (PMP)', 'Global standard for project management', 'Project Management Institute', '2021-11-05', '2024-11-05');

-- Insert sample trainings
INSERT INTO `trainings` (`title`, `description`, `employee_id`, `start_date`, `end_date`, `status`, `related_skill_id`, `created_by`)
VALUES 
('Advanced JavaScript', 'Deep dive into advanced JavaScript concepts', 3, '2023-05-15', '2023-05-20', 'Completed', 1, 2),
('AWS Cloud Practitioner', 'Fundamentals of AWS cloud computing', 3, '2023-07-10', '2023-07-15', 'Completed', 7, 2),
('Content Marketing Strategy', 'Developing effective content marketing strategies', 4, '2023-06-05', '2023-06-10', 'Completed', 10, 2),
('Docker and Kubernetes Workshop', 'Hands-on workshop for container orchestration', 5, '2023-09-20', '2023-09-25', 'Completed', 9, 2),
('Project Management Essentials', 'Core principles of effective project management', 6, '2023-08-15', '2023-08-20', 'Completed', 4, 2),
('React Framework Fundamentals', 'Introduction to React.js development', 3, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'In Progress', 1, 2),
('Advanced Cloud Architecture', 'Designing robust cloud solutions', 5, DATE_ADD(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'Planned', 7, 2);

-- Done!
