# Crash Hockey Dashboard Views

This directory contains all 33 view files for the Crash Hockey dashboard application.

## View Files Created

### Main Menu Views (8 files)
1. **home.php** - Dashboard home with role-specific content
2. **stats.php** - Performance stats and goals tracker
3. **sessions_upcoming.php** - List of upcoming sessions
4. **sessions_booking.php** - Session booking with packages
5. **video_drill_review.php** - Player drill video reviews
6. **video_coach_reviews.php** - Coach review videos with upload
7. **health_workouts.php** - Strength and conditioning plans
8. **health_nutrition.php** - Nutrition plans

### Team Views (1 file)
9. **team_roster.php** - Team roster view for team coaches

### Coaches Corner Views (7 files)
10. **drills_library.php** - Drill library with search/filter
11. **drills_create.php** - Create drill with interactive tool
12. **drills_import.php** - Import drill from IHS
13. **practice_library.php** - Practice plan library
14. **practice_create.php** - Create practice plan
15. **coach_roster.php** - Athlete roster for coaches
16. **travel_mileage.php** - Mileage tracking

### Accounting & Reports Views (7 files)
17. **accounting_dashboard.php** - Accounting overview
18. **accounting_billing.php** - Billing history
19. **accounting_reports.php** - Report generator
20. **accounting_schedules.php** - Scheduled reports
21. **accounting_credits.php** - Credits and refunds
22. **accounting_expenses.php** - Expense tracking
23. **accounting_products.php** - Products (sessions, packages, discounts)

### HR Views (1 file)
24. **hr_termination.php** - Employee termination

### Administration Views (7 files)
25. **admin_users.php** - All users management
26. **admin_categories.php** - Category management (tabbed)
27. **admin_eval_framework.php** - Evaluation framework builder
28. **admin_notifications.php** - System notifications
29. **admin_audit_log.php** - Audit log viewer
30. **admin_cron_jobs.php** - Cron job management
31. **admin_system_tools.php** - System tools (tabbed: settings, theme, database)

### Supporting Views (2 files)
32. **profile.php** - User profile settings
33. **settings.php** - Global settings (admin)

## Design Consistency

All views follow these design standards:

- **Dark Theme**: Consistent use of CSS variables from style.css
- **Input Height**: All input boxes are 45px
- **Font**: Inter 14px for inputs and buttons
- **Button Style**: Height 45px, Inter 14px bold
- **Icons**: Font Awesome icons throughout
- **Role-Based Content**: Views adapt to user roles where applicable
- **Responsive**: Grid layouts that adapt to screen size

## CSS Variables Used

```css
--bg-main: #06080b
--bg-card: #0d1117
--neon: #ff4d00
--accent: #ff9d00
--text-white: #ffffff
--text-dim: #94a3b8
--border: #1e293b
```

## Notes

- All files contain placeholder content and sample data
- Forms include proper validation attributes
- Tables are responsive with horizontal scroll
- Modals and popups are included where appropriate
- All views use consistent component patterns

---
Created: January 2024
