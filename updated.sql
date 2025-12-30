
-- Update the owners table to ensure correct lowercase ENUM values and add missing columns
ALTER TABLE owners 
  MODIFY COLUMN status ENUM('pending', 'active', 'disabled') DEFAULT 'pending',
  MODIFY COLUMN role ENUM('owner', 'admin') DEFAULT 'owner';

-- Add missing columns if not present (run each separately if needed)
ALTER TABLE owners ADD COLUMN shopname VARCHAR(255) NOT NULL AFTER fullname;
ALTER TABLE owners ADD COLUMN location VARCHAR(255) NOT NULL AFTER shopname;
ALTER TABLE owners ADD COLUMN last_activity DATETIME NULL DEFAULT NULL AFTER status;
ALTER TABLE owners ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER last_activity;

-- Optional: Fix any invalid status or role values in the table
UPDATE owners SET status = 'pending' WHERE status NOT IN ('pending', 'active', 'disabled');
UPDATE owners SET role = 'owner' WHERE role NOT IN ('owner', 'admin');
ALTER TABLE owners
  ADD COLUMN shopname VARCHAR(255) NOT NULL AFTER fullname,
  ADD COLUMN location VARCHAR(255) NOT NULL AFTER shopname,
  MODIFY COLUMN email VARCHAR(255) UNIQUE NOT NULL,
  MODIFY COLUMN hashedpassword VARCHAR(255) NOT NULL,
  MODIFY COLUMN role ENUM('owner') DEFAULT 'owner',
  MODIFY COLUMN status ENUM('active', 'disabled', 'pending') DEFAULT 'pending',
  ADD COLUMN last_activity DATETIME NULL DEFAULT NULL AFTER status,
  ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER last_activity;

  ALTER TABLE owners MODIFY COLUMN status ENUM('active', 'disabled', 'pending', 'approved') DEFAULT 'pending';


    

ALTER TABLE owners 
ADD COLUMN phone_number int(11) NOT NULL AFTER fullname,
ADD COLUMN business_permit VARCHAR(255) DEFAULT NULL,
ADD COLUMN valid_id VARCHAR(255) DEFAULT NULL,
ADD COLUMN barangay_clearance VARCHAR(255) DEFAULT NULL;
