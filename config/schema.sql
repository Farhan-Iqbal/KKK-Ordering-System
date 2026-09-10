-- KKK OS V1 Database Schema
-- Primary Source of Truth

CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` VARCHAR(50) NOT NULL UNIQUE, -- e.g. KKK-2026-001
  `package_type` ENUM('1-package', '2-package') NOT NULL,
  `side_type` ENUM('lelaki', 'perempuan', 'both') NOT NULL,
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_email` VARCHAR(150) NOT NULL,
  `customer_phone` VARCHAR(30) NOT NULL,
  `deposit_status` ENUM('pending', 'paid') DEFAULT 'paid',
  `order_status` ENUM(
    'BOOKING_PENDING',
    'BOOKED_DEPOSIT_PAID',
    'DETAILS_INCOMPLETE',
    'DETAILS_CONFIRMED',
    'READY_FOR_DESIGN',
    'DESIGN_IN_PROGRESS',
    'DESIGN_READY',
    'CORRECTION_REQUESTED',
    'DESIGN_APPROVED',
    'BALANCE_PENDING',
    'PAID',
    'PRINTING',
    'PACKING',
    'READY_FOR_PICKUP',
    'SHIPPED',
    'COMPLETED',
    'CANCELLED',
    'ARCHIVED'
  ) DEFAULT 'DETAILS_INCOMPLETE',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_details` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` VARCHAR(50) NOT NULL,
  
  -- Design Preferences
  `design_code_groom` VARCHAR(50) NULL,
  `design_code_bride` VARCHAR(50) NULL,
  `card_title_groom` VARCHAR(150) NULL,
  `card_title_bride` VARCHAR(150) NULL,

  -- Couple Information
  `groom_name` VARCHAR(150) NULL,
  `groom_abbrev` VARCHAR(50) NULL,
  `bride_name` VARCHAR(150) NULL,
  `bride_abbrev` VARCHAR(50) NULL,
  `second_couple_notes` TEXT NULL, -- Retained for optional manual designer reference

  -- Groom Side Parents & Event
  `groom_father` VARCHAR(150) NULL,
  `groom_mother` VARCHAR(150) NULL,
  `groom_event_date` DATE NULL,
  `groom_hijri_date` VARCHAR(100) NULL,
  `groom_event_time` VARCHAR(100) NULL,
  `groom_venue` TEXT NULL,
  `groom_address` TEXT NULL,
  `groom_maps_url` TEXT NULL,
  `groom_contacts` JSON NULL, -- Array of contact persons (Max 3)

  -- Bride Side Parents & Event
  `bride_father` VARCHAR(150) NULL,
  `bride_mother` VARCHAR(150) NULL,
  `bride_event_date` DATE NULL,
  `bride_hijri_date` VARCHAR(100) NULL,
  `bride_event_time` VARCHAR(100) NULL,
  `bride_venue` TEXT NULL,
  `bride_address` TEXT NULL,
  `bride_maps_url` TEXT NULL,
  `bride_contacts` JSON NULL, -- Array of contact persons (Max 3)

  -- Fulfillment
  `fulfillment_method` ENUM('courier', 'pickup') NOT NULL DEFAULT 'courier',
  `shipping_recipient` VARCHAR(150) NULL,
  `shipping_phone` VARCHAR(30) NULL,
  `shipping_address` TEXT NULL,

  `is_confirmed_by_customer` TINYINT(1) DEFAULT 0,
  `confirmed_at` DATETIME NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `photoshop_merge_jobs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `job_id` VARCHAR(60) NOT NULL UNIQUE, -- ORDERID (1-pkg) or ORDERID-L / ORDERID-P (2-pkg)
  `order_id` VARCHAR(50) NOT NULL,
  `side` ENUM('LELAKI', 'PEREMPUAN') NOT NULL,
  `design_code` VARCHAR(50) NULL,
  `card_title` VARCHAR(150) NULL,
  `namapengantin1` VARCHAR(150) NULL,
  `namapengantin2` VARCHAR(150) NULL,
  `namabapa` VARCHAR(150) NULL,
  `namaibu` VARCHAR(150) NULL,
  `tarikh` VARCHAR(100) NULL,
  `tarikh_hijri` VARCHAR(100) NULL,
  `masa` VARCHAR(100) NULL,
  `atucara` TEXT NULL,
  `venue` TEXT NULL,
  `alamat` TEXT NULL,
  `maps_url` TEXT NULL,
  `contact_persons` TEXT NULL,
  `status` ENUM('pending', 'synced_to_sheet', 'merged') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;