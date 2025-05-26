-- Complete DTU Portal Database Schema

-- Create roles table first
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `role_level` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default roles
INSERT INTO `roles` (`role_name`, `role_level`) VALUES
('Administrator', 100),
('HOD', 75),
('Warden', 75),
('Teacher', 50),
('Student', 10);

-- Department table to avoid redundancy
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create user table
CREATE TABLE IF NOT EXISTS `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `email` varchar(128) NOT NULL,
  `password` varchar(256) NOT NULL,
  `role_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `roll_number` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `image` varchar(128) NOT NULL DEFAULT 'default.jpg',
  `last_login` datetime DEFAULT NULL,
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `fk_user_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin123)
INSERT INTO `user` (`name`, `email`, `password`, `role_id`, `status`, `date_created`) VALUES
('Administrator', 'admin@dtu.ac.in', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 1, 'active', CURRENT_TIMESTAMP);

-- Create complaint categories table
CREATE TABLE `complaint_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(50) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  `department_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_category_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create complaint subcategories table
CREATE TABLE `complaint_subcategories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_subcat` (`category_id`, `name`),
  CONSTRAINT `fk_subcat_category` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create complaint status types table
CREATE TABLE `complaint_status_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status_name` varchar(20) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `status_name` (`status_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create priority levels table
CREATE TABLE `priority_levels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level_name` varchar(20) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `level_name` (`level_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create complaints table
CREATE TABLE `complaints` (
  `id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `sub_category_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status_id` int(11) NOT NULL,
  `priority_id` int(11) NOT NULL,
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_updated` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `assigned_to` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `category_id` (`category_id`),
  KEY `sub_category_id` (`sub_category_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `status_id` (`status_id`),
  KEY `priority_id` (`priority_id`),
  KEY `idx_complaint_date` (`date_created`),
  KEY `idx_complaint_status_date` (`status_id`, `date_created`),
  KEY `idx_complaint_department` (`category_id`, `status_id`),
  CONSTRAINT `fk_complaint_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_complaint_category` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`id`),
  CONSTRAINT `fk_complaint_subcategory` FOREIGN KEY (`sub_category_id`) REFERENCES `complaint_subcategories` (`id`),
  CONSTRAINT `fk_complaint_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `user` (`id`),
  CONSTRAINT `fk_complaint_status` FOREIGN KEY (`status_id`) REFERENCES `complaint_status_types` (`id`),
  CONSTRAINT `fk_complaint_priority` FOREIGN KEY (`priority_id`) REFERENCES `priority_levels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create complaint details table (for large text fields)
CREATE TABLE `complaint_details` (
  `complaint_id` varchar(64) NOT NULL,
  `resolution_comments` text,
  `internal_notes` text,
  PRIMARY KEY (`complaint_id`),
  CONSTRAINT `fk_details_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create complaint attachments table
CREATE TABLE `complaint_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` varchar(64) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `complaint_id` (`complaint_id`),
  CONSTRAINT `fk_attachment_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create complaint history table
CREATE TABLE `complaint_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `complaint_id` varchar(64) NOT NULL,
  `status_id` int(11) NOT NULL,
  `comments` text,
  `updated_by` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `complaint_id` (`complaint_id`),
  KEY `status_id` (`status_id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `fk_history_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`),
  CONSTRAINT `fk_history_status` FOREIGN KEY (`status_id`) REFERENCES `complaint_status_types` (`id`),
  CONSTRAINT `fk_history_user` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert initial data for status types
INSERT INTO `complaint_status_types` (`status_name`, `description`) VALUES
('pending', 'Complaint is newly created'),
('in_progress', 'Complaint is being processed'),
('resolved', 'Complaint has been resolved'),
('rejected', 'Complaint has been rejected');

-- Insert initial data for priority levels
INSERT INTO `priority_levels` (`level_name`, `description`) VALUES
('low', 'Non-urgent issues'),
('medium', 'Standard priority issues'),
('high', 'Urgent issues requiring immediate attention');

-- Insert departments
INSERT INTO `departments` (`name`, `code`) VALUES
('Computer Engineering', 'COE'),
('Electronics & Communication', 'ECE'),
('Information Technology', 'IT'),
('Mechanical Engineering', 'ME'),
('Civil Engineering', 'CE'),
('Library', 'LIB'),
('Hostel Administration', 'HST'),
('Student Affairs', 'SA'),
('Accounts', 'ACC');

-- Insert DTU-specific complaint categories for each department

-- Hostel complaints
INSERT INTO `complaint_categories` (`category_name`, `description`, `department_id`) VALUES
('Hostel Infrastructure', 'Issues related to hostel building and facilities', (SELECT id FROM departments WHERE code = 'HST')),
('Mess Services', 'Complaints about food quality and mess facilities', (SELECT id FROM departments WHERE code = 'HST')),
('Room Issues', 'Problems with hostel rooms', (SELECT id FROM departments WHERE code = 'HST')),
('Hostel Staff', 'Complaints regarding hostel staff behavior', (SELECT id FROM departments WHERE code = 'HST'));

-- Library complaints
INSERT INTO `complaint_categories` (`category_name`, `description`, `department_id`) VALUES
('Library Resources', 'Issues with books and study materials', (SELECT id FROM departments WHERE code = 'LIB')),
('Library Infrastructure', 'Problems with library facilities', (SELECT id FROM departments WHERE code = 'LIB')),
('Digital Resources', 'Issues with e-library and online resources', (SELECT id FROM departments WHERE code = 'LIB')),
('Library Services', 'Complaints about library staff and services', (SELECT id FROM departments WHERE code = 'LIB'));

-- Academic complaints for each engineering department
INSERT INTO `complaint_categories` (`category_name`, `description`, `department_id`) VALUES
-- Computer Engineering
('COE Academic', 'Academic issues in Computer Engineering', (SELECT id FROM departments WHERE code = 'COE')),
('COE Labs', 'Issues with computer labs and equipment', (SELECT id FROM departments WHERE code = 'COE')),
('COE Faculty', 'Concerns regarding COE faculty', (SELECT id FROM departments WHERE code = 'COE')),

-- Electronics & Communication
('ECE Academic', 'Academic issues in Electronics Engineering', (SELECT id FROM departments WHERE code = 'ECE')),
('ECE Labs', 'Issues with electronics labs and equipment', (SELECT id FROM departments WHERE code = 'ECE')),
('ECE Faculty', 'Concerns regarding ECE faculty', (SELECT id FROM departments WHERE code = 'ECE')),

-- Information Technology
('IT Academic', 'Academic issues in Information Technology', (SELECT id FROM departments WHERE code = 'IT')),
('IT Labs', 'Issues with IT labs and equipment', (SELECT id FROM departments WHERE code = 'IT')),
('IT Faculty', 'Concerns regarding IT faculty', (SELECT id FROM departments WHERE code = 'IT')),

-- Mechanical Engineering
('ME Academic', 'Academic issues in Mechanical Engineering', (SELECT id FROM departments WHERE code = 'ME')),
('ME Labs', 'Issues with mechanical labs and equipment', (SELECT id FROM departments WHERE code = 'ME')),
('ME Faculty', 'Concerns regarding ME faculty', (SELECT id FROM departments WHERE code = 'ME')),

-- Civil Engineering
('CE Academic', 'Academic issues in Civil Engineering', (SELECT id FROM departments WHERE code = 'CE')),
('CE Labs', 'Issues with civil engineering labs and equipment', (SELECT id FROM departments WHERE code = 'CE')),
('CE Faculty', 'Concerns regarding CE faculty', (SELECT id FROM departments WHERE code = 'CE'));

-- Student Affairs complaints
INSERT INTO `complaint_categories` (`category_name`, `description`, `department_id`) VALUES
('Ragging', 'Complaints related to ragging incidents', (SELECT id FROM departments WHERE code = 'SA')),
('Student Harassment', 'Complaints about harassment or bullying', (SELECT id FROM departments WHERE code = 'SA')),
('Student Activities', 'Issues with clubs and student activities', (SELECT id FROM departments WHERE code = 'SA')),
('Campus Security', 'Concerns about campus safety and security', (SELECT id FROM departments WHERE code = 'SA'));

-- Add sample users for different roles
INSERT INTO `user` (`name`, `email`, `roll_number`, `department_id`, `image`, `password`, `role_id`, `status`, `date_created`) VALUES
-- Students
('Rahul Kumar', 'rahul.kumar@dtu.ac.in', '2K21/CO/123', (SELECT id FROM departments WHERE code = 'COE'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 3, 'active', UNIX_TIMESTAMP()),
('Priya Singh', 'priya.singh@dtu.ac.in', '2K21/IT/234', (SELECT id FROM departments WHERE code = 'IT'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 3, 'active', UNIX_TIMESTAMP()),
('Michael Thomas', 'michael.thomas@dtu.ac.in', '2K21/ECE/345', (SELECT id FROM departments WHERE code = 'ECE'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 3, 'active', UNIX_TIMESTAMP()),
('Sarah Wilson', 'sarah.wilson@dtu.ac.in', '2K21/ME/456', (SELECT id FROM departments WHERE code = 'ME'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 3, 'active', UNIX_TIMESTAMP()),

-- Additional Students (Different Years and Departments)
('Ankit Sharma', 'ankit.sharma@dtu.ac.in', '2K20/CO/112', (SELECT id FROM departments WHERE code = 'COE'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 3, 'active', UNIX_TIMESTAMP()),
('Neha Gupta', 'neha.gupta@dtu.ac.in', '2K22/IT/156', (SELECT id FROM departments WHERE code = 'IT'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 3, 'active', UNIX_TIMESTAMP()),
('Arjun Patel', 'arjun.patel@dtu.ac.in', '2K20/ECE/189', (SELECT id FROM departments WHERE code = 'ECE'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 3, 'active', UNIX_TIMESTAMP()),
('Riya Verma', 'riya.verma@dtu.ac.in', '2K22/CE/145', (SELECT id FROM departments WHERE code = 'CE'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 3, 'active', UNIX_TIMESTAMP()),
('Aditya Kumar', 'aditya.kumar@dtu.ac.in', '2K21/ME/167', (SELECT id FROM departments WHERE code = 'ME'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 3, 'active', UNIX_TIMESTAMP()),
('Ishaan Mehta', 'ishaan.mehta@dtu.ac.in', '2K20/IT/178', (SELECT id FROM departments WHERE code = 'IT'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 3, 'active', UNIX_TIMESTAMP()),
('Zara Khan', 'zara.khan@dtu.ac.in', '2K22/CO/134', (SELECT id FROM departments WHERE code = 'COE'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 3, 'active', UNIX_TIMESTAMP()),
('Rohan Malhotra', 'rohan.malhotra@dtu.ac.in', '2K21/ECE/198', (SELECT id FROM departments WHERE code = 'ECE'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 3, 'active', UNIX_TIMESTAMP()),
('Shreya Reddy', 'shreya.reddy@dtu.ac.in', '2K20/CE/167', (SELECT id FROM departments WHERE code = 'CE'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 3, 'active', UNIX_TIMESTAMP()),
('Dev Kapoor', 'dev.kapoor@dtu.ac.in', '2K22/ME/123', (SELECT id FROM departments WHERE code = 'ME'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 3, 'active', UNIX_TIMESTAMP()),

-- Staff (Teachers)
('Dr. Ramesh Chandra', 'ramesh.chandra@dtu.ac.in', NULL, (SELECT id FROM departments WHERE code = 'COE'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 2, 'active', UNIX_TIMESTAMP()),
('Dr. Sarita Agarwal', 'sarita.agarwal@dtu.ac.in', NULL, (SELECT id FROM departments WHERE code = 'IT'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 2, 'active', UNIX_TIMESTAMP()),

-- HODs
('Prof. Rajeev Malhotra', 'hod.coe@dtu.ac.in', NULL, (SELECT id FROM departments WHERE code = 'COE'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 2, 'active', UNIX_TIMESTAMP()),
('Prof. Deepika Mehta', 'hod.ece@dtu.ac.in', NULL, (SELECT id FROM departments WHERE code = 'ECE'), 'default.jpg', '$2y$12$HiGsHI4BPvjy8mw1u71hQO/.YyFqG2HoJ2hUE5Vtd341RDrh7rLw.', 2, 'active', UNIX_TIMESTAMP());

-- Add sample complaints
INSERT INTO `complaints` (`id`, `user_id`, `category_id`, `sub_category_id`, `title`, `description`, `status_id`, `priority_id`, `assigned_to`) VALUES
-- Student Complaints
('COMP001', 
 (SELECT id FROM user WHERE email = 'rahul.kumar@dtu.ac.in'), 
 (SELECT id FROM complaint_categories WHERE category_name = 'COE Labs'), 
 NULL,
 'Computer Lab PCs Not Working',
 'In Lab 3, computers numbered 12, 14, and 15 are not booting up properly. This is affecting our practical sessions.',
 (SELECT id FROM complaint_status_types WHERE status_name = 'pending'),
 (SELECT id FROM priority_levels WHERE level_name = 'high'),
 (SELECT id FROM user WHERE email = 'hod.coe@dtu.ac.in')),

('COMP002',
 (SELECT id FROM user WHERE email = 'sarah.wilson@dtu.ac.in'),
 (SELECT id FROM complaint_categories WHERE category_name = 'Hostel Infrastructure'),
 NULL,
 'Water Supply Issue in Girls Hostel',
 'Block C of girls hostel is facing irregular water supply for the past 3 days.',
 (SELECT id FROM complaint_status_types WHERE status_name = 'in_progress'),
 (SELECT id FROM priority_levels WHERE level_name = 'high'),
 (SELECT id FROM user WHERE email = 'admin@dtu.ac.in')),

-- Teacher Complaints
('COMP003',
 (SELECT id FROM user WHERE email = 'ramesh.chandra@dtu.ac.in'),
 (SELECT id FROM complaint_categories WHERE category_name = 'COE Academic'),
 NULL,
 'Projector Not Working in Room 304',
 'The projector in Room 304 is not functioning properly, affecting the delivery of lectures.',
 (SELECT id FROM complaint_status_types WHERE status_name = 'in_progress'),
 (SELECT id FROM priority_levels WHERE level_name = 'medium'),
 (SELECT id FROM user WHERE email = 'admin@dtu.ac.in')),

-- HOD Complaints
('COMP004',
 (SELECT id FROM user WHERE email = 'hod.ece@dtu.ac.in'),
 (SELECT id FROM complaint_categories WHERE category_name = 'ECE Labs'),
 NULL,
 'Requirement for New Lab Equipment',
 'The Digital Signal Processing lab requires new oscilloscopes and signal generators for better practical training.',
 (SELECT id FROM complaint_status_types WHERE status_name = 'pending'),
 (SELECT id FROM priority_levels WHERE level_name = 'medium'),
 (SELECT id FROM user WHERE email = 'admin@dtu.ac.in'));

-- Add complaint details
INSERT INTO `complaint_details` (`complaint_id`, `resolution_comments`, `internal_notes`) VALUES
('COMP001', NULL, 'IT team has been notified for immediate inspection'),
('COMP002', 'Maintenance team is working on fixing the water pump', 'Requires coordination with Delhi Jal Board'),
('COMP003', NULL, 'New projector requisition might be needed'),
('COMP004', NULL, 'Budget approval pending from finance department');

-- Add complaint history
INSERT INTO `complaint_history` (`complaint_id`, `status_id`, `comments`, `updated_by`) VALUES
('COMP001', (SELECT id FROM complaint_status_types WHERE status_name = 'pending'), 'Complaint registered and assigned to HOD', 1),
('COMP002', (SELECT id FROM complaint_status_types WHERE status_name = 'in_progress'), 'Maintenance team has been deployed', 1),
('COMP003', (SELECT id FROM complaint_status_types WHERE status_name = 'in_progress'), 'Technical team inspection scheduled', 1),
('COMP004', (SELECT id FROM complaint_status_types WHERE status_name = 'pending'), 'Under review by administration', 1); 