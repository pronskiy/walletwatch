## Feature: Spending Alerts

### Overview
Users need proactive notifications when their spending approaches or exceeds budget thresholds.

### Requirements
1. Alert thresholds: configurable per budget (default: 80%, 100%)
2. Notification channels: email (Mailable) + in-app (database notifications)
3. Alert frequency: max once per day per budget per threshold
4. Processing: queue job that runs daily, checks all active budgets
5. User preferences: allow disabling alerts per budget or globally

### Technical Notes
- Use Laravel's notification system with multiple channels
- Queue the processing job (ShouldQueue)
- Store alert history to prevent duplicate notifications
- Consider timezone handling for daily budget calculations

### Acceptance Criteria
- User gets email when spending hits 80% of monthly budget
- User gets in-app notification at 100%
- No duplicate alerts within 24 hours
- User can disable alerts per budget
- Processing handles 1000+ budgets efficiently