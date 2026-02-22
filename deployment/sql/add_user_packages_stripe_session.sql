-- Migration: Add stripe_session_id to user_packages for payment tracking and idempotency
ALTER TABLE `user_packages`
ADD COLUMN IF NOT EXISTS `stripe_session_id` VARCHAR(255) DEFAULT NULL COMMENT 'Stripe checkout session ID for payment tracking',
ADD INDEX IF NOT EXISTS `idx_stripe_session` (`stripe_session_id`);
