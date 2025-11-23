-- Migration: Add 'category' column to _plans table
-- Purpose: Categorize plans as New Device, Application, or Renew Device for filtering
-- Date: 2025-11-23

-- Add category column to _plans table
ALTER TABLE _plans
ADD COLUMN category VARCHAR(20) NULL
AFTER days;

-- Create index on category for faster filtering
CREATE INDEX idx_plans_category ON _plans(category);

-- Optional: Update existing plans with a default category (you can customize this)
-- UPDATE _plans SET category = 'new_device' WHERE category IS NULL;

-- Verify the change
SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'stalker_db'
    AND TABLE_NAME = '_plans'
    AND COLUMN_NAME = 'category';
