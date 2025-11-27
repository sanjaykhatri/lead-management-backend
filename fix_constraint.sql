-- Fix the provider_performance_metrics unique constraint name
-- Run this SQL directly in your MySQL database if migrations fail

-- First, drop the old constraint (if it exists)
ALTER TABLE `provider_performance_metrics` 
DROP INDEX IF EXISTS `provider_performance_metrics_service_provider_id_metric_date_unique`;

-- Add the new constraint with shorter name
ALTER TABLE `provider_performance_metrics` 
ADD UNIQUE KEY `provider_metrics_provider_date_unique` (`service_provider_id`, `metric_date`);

