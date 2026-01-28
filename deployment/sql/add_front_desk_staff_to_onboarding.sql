-- Migration: Add front_desk_staff to employee_onboarding role ENUM
-- This migration adds the 'front_desk_staff' option to the role column
-- in the employee_onboarding table to support onboarding front desk staff

-- Modify the role ENUM to include front_desk_staff
ALTER TABLE `employee_onboarding` 
MODIFY COLUMN `role` ENUM('coach', 'health_coach', 'admin', 'team_coach', 'front_desk_staff') NOT NULL;
