# Goal-Based Evaluation Platform - Quick Start Guide

## Installation (5 minutes)

### Step 1: Run Setup Script
```bash
cd /path/to/crashhockey
bash deployment/setup_evaluations.sh
```

This will:
- Create `uploads/eval_media/` directory
- Optionally create database tables

### Step 2: Add Navigation (Manual)
Add this menu item to your navigation:
```php
<a href="dashboard.php?page=evaluations_goals">Goal Evaluations</a>
```

### Step 3: Test Access
1. Login as coach → Should see "Create Evaluation" button
2. Login as athlete → Should see evaluations (if any exist)

---

## Quick Usage Guide

### For Coaches

**Create Evaluation (30 seconds)**
1. Click "Create Evaluation" button
2. Fill in title: e.g., "Skating Skills Assessment"
3. (Optional) Add description
4. Click "Save Evaluation"

**Add Steps (1 minute)**
1. Click on evaluation card
2. Click "Add Step"
3. Enter step title: e.g., "Forward Crossovers"
4. Check "Requires approval" if needed
5. Repeat for each skill

**Check Off Steps**
- Click checkbox → Step instantly completed ✅
- No approval needed for coach check-offs

**Review Approvals**
- Open evaluation with pending requests (⏳ icon)
- Click "Approve" or "Reject"
- Add optional note if rejecting

**Share Evaluation**
1. Open evaluation
2. Click "Generate Share Link"
3. Copy link
4. Share with parents/scouts/external viewers

---

### For Athletes

**View Your Evaluations**
- Navigate to evaluations page
- See all evaluations created for you
- Click card to view details

**Complete Steps**
1. Click checkbox on step
2. If needs approval → Shows "⏳ Pending Approval"
3. Wait for coach approval
4. If approved → ✅ Step complete!

**Add Progress Media**
1. Click "📎 Media" on step
2. Upload image or video
3. Add note about your progress
4. Submit

---

## Common Workflows

### Workflow 1: Skills Assessment
```
Coach: Create "Skating Skills" evaluation
Coach: Add steps for each skill (crossovers, transitions, etc.)
Athlete: Practices and checks off completed skills
Coach: Reviews video evidence and approves
```

### Workflow 2: Training Progress
```
Coach: Create "Off-Ice Training" evaluation
Coach: Add steps without approval required
Athlete: Completes workouts and checks boxes
Progress tracked automatically
```

### Workflow 3: Certification
```
Coach: Create "Level 3 Certification" evaluation
Coach: Add steps requiring approval
Athlete: Completes each requirement
Athlete: Uploads video evidence
Coach: Reviews and approves/rejects
Coach: Shares final evaluation with organization
```

---

## Keyboard Shortcuts

- `ESC` - Close any open modal
- `Tab` - Navigate through form fields
- `Enter` - Submit focused form

---

## Troubleshooting

### "Create Evaluation" button not showing
- **Problem**: You're logged in as athlete
- **Solution**: Only coaches can create evaluations

### Upload fails
- **Problem**: Upload directory doesn't exist or no permissions
- **Solution**: Run `mkdir -p uploads/eval_media && chmod 755 uploads/eval_media`

### Can't see evaluations
- **Problem**: None created yet or wrong athlete selected
- **Solution**: Coach: Use athlete dropdown. Athlete: Ask coach to create one.

### Share link doesn't work
- **Problem**: Need to create public_eval.php view
- **Solution**: This is a separate feature. For now, only logged-in users can view.

---

## Tips & Best Practices

### For Coaches
- ✅ Use clear, specific step titles
- ✅ Add descriptions with success criteria
- ✅ Use "Requires approval" for important milestones
- ✅ Provide feedback in rejection notes
- ✅ Share evaluations with parents to show progress

### For Athletes  
- ✅ Upload video evidence for approvals
- ✅ Add progress notes to show effort
- ✅ Don't rush - quality over quantity
- ✅ Review rejection feedback carefully
- ✅ Ask coach if requirements are unclear

---

## File Locations

```
views/evaluations_goals.php          # Main interface
process_eval_goals.php                # Backend processing
process_eval_goal_approval.php        # Approval workflow
uploads/eval_media/                   # Uploaded files
deployment/sql/goal_evaluations_schema.sql  # Database schema
```

---

## Support

**Documentation:**
- Full README: `EVALUATION_PLATFORM_README.md`
- Testing Guide: `EVALUATION_TESTING_CHECKLIST.md`
- Implementation Summary: `EVALUATION_IMPLEMENTATION_SUMMARY.md`

**Common Issues:**
1. Database tables missing → Run setup script
2. Permission errors → Check user role in database
3. Upload errors → Check directory permissions

**Getting Help:**
- Check error logs: `/var/log/apache2/error.log` or PHP error_log
- Review browser console for JavaScript errors
- Contact development team

---

## Database Quick Reference

```sql
-- View all evaluations
SELECT * FROM goal_evaluations;

-- View steps for evaluation #1
SELECT * FROM goal_eval_steps WHERE goal_eval_id = 1;

-- View pending approvals
SELECT * FROM goal_eval_approvals WHERE status = 'pending';

-- View media for step #1
SELECT * FROM goal_eval_progress WHERE goal_eval_step_id = 1;
```

---

## API Quick Reference

**Get evaluation:**
```javascript
fetch('process_eval_goals.php?action=get_evaluation&evaluation_id=1')
```

**Check step:**
```javascript
fetch('process_eval_goals.php', {
  method: 'POST',
  body: new URLSearchParams({
    action: 'check_step',
    step_id: 1,
    is_checked: 1,
    csrf_token: 'your-token'
  })
})
```

**Approve step:**
```javascript
fetch('process_eval_goal_approval.php', {
  method: 'POST',
  body: new URLSearchParams({
    action: 'approve_step',
    step_id: 1,
    csrf_token: 'your-token'
  })
})
```

---

**That's it! You're ready to use the Goal-Based Evaluation Platform.** 🎉

For detailed information, see `EVALUATION_PLATFORM_README.md`
