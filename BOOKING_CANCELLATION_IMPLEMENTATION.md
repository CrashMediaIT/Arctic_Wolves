# Booking Cancellation CRUD Implementation

## Overview
This document describes the implementation of the booking cancellation (DELETE) operation, completing the CRUD functionality for the bookings system.

## Changes Made

### 1. Backend - process_booking.php
Added a new `cancel_booking` action handler that:

**Features:**
- Validates booking ID and ownership
- Checks if the booking is already cancelled
- Verifies the session hasn't already occurred (past date check)
- Implements 24-hour cancellation policy
  - Sessions within 24 hours: Can cancel but marked as potentially non-refundable
  - Sessions beyond 24 hours: Can cancel with full refund eligibility
- Updates booking status to 'cancelled'
- Logs the cancellation for audit trail
- Returns JSON response with success/error status

**Security:**
- CSRF token validation
- User authentication check
- Ownership validation (user can only cancel their own bookings)
- Input sanitization

**Code Location:** Lines 42-128 in `process_booking.php`

### 2. Frontend - views/sessions_upcoming.php

#### SQL Query Updates (Lines 23-65)
- Added `b.id as booking_id` to SELECT clause
- Added `b.status as booking_status` to track cancellation state
- Added filter `AND b.status != 'cancelled'` to exclude cancelled bookings from display

#### HTML Updates (Line 380)
- Added `data-booking-id` attribute to cancel buttons
- Updated condition to only show cancel button for:
  - Future sessions (not history)
  - Sessions more than 24 hours away
  - Active bookings (not already cancelled)

#### JavaScript Updates (Lines 1167-1263)
Replaced demo-only handler with full cancellation functionality:
- Validates booking ID exists
- Shows confirmation dialog with cancellation policy warning
- Makes AJAX POST request to `process_booking.php`
- Handles success:
  - Shows success toast notification
  - Smoothly removes cancelled session from UI
  - Reloads page if no sessions remain
- Handles errors:
  - Shows error toast notification
  - Re-enables cancel button
  - Preserves user's ability to retry

## API Endpoint

### POST /process_booking.php

**Action:** `cancel_booking`

**Parameters:**
- `action` (string): "cancel_booking" or "cancel"
- `booking_id` (int): ID of the booking to cancel
- `csrf_token` (string): CSRF protection token

**Response:** JSON
```json
{
  "success": true|false,
  "message": "Booking cancelled successfully...",
  "refund_eligible": true|false,
  "booking_id": 123
}
```

**Success Response:**
```json
{
  "success": true,
  "message": "Booking cancelled successfully. You may request a refund if payment was made.",
  "refund_eligible": true,
  "booking_id": 123
}
```

**Error Responses:**
```json
{
  "success": false,
  "message": "Invalid booking ID"
}
```

```json
{
  "success": false,
  "message": "Booking not found or access denied"
}
```

```json
{
  "success": false,
  "message": "Booking is already cancelled"
}
```

```json
{
  "success": false,
  "message": "Cannot cancel past sessions"
}
```

## Cancellation Policy

### 24-Hour Policy
- **More than 24 hours before session:** Full refund eligibility
- **Less than 24 hours before session:** Cancellation allowed but may not be eligible for refund
- **Past sessions:** Cannot be cancelled

### Refund Process
After cancellation, users can:
1. Navigate to the refunds page
2. Request a refund for the cancelled booking
3. Admin reviews and processes the refund through the existing refund system

## Database Schema

### Bookings Table
The implementation uses existing schema fields:
```sql
CREATE TABLE bookings (
  id INT PRIMARY KEY,
  session_id INT,
  user_id INT,
  status ENUM('confirmed', 'cancelled', 'waitlisted'),
  payment_status ENUM('pending', 'paid', 'refunded', 'cancelled'),
  amount DECIMAL(10,2),
  notes TEXT,
  booking_date TIMESTAMP,
  ...
)
```

**Status Values:**
- `confirmed` - Active booking
- `cancelled` - User cancelled (via this implementation)
- `waitlisted` - On waitlist

**Payment Status:**
- Remains `paid` if payment was made
- Set to `cancelled` if payment was still pending

## Testing

### Manual Testing Checklist
- [ ] Cancel a booking more than 24 hours before session
  - Should succeed with full refund eligibility message
  - Booking should disappear from upcoming sessions
  - Should be marked as cancelled in database

- [ ] Attempt to cancel a booking less than 24 hours before session
  - Should succeed but show warning about refund eligibility
  - Booking should still be cancelled

- [ ] Attempt to cancel a past session
  - Should fail with "Cannot cancel past sessions" error
  - Booking should remain active

- [ ] Attempt to cancel an already cancelled booking
  - Should fail with "already cancelled" error

- [ ] Attempt to cancel someone else's booking
  - Should fail with "access denied" error

### Automated Testing
Test file created: `tests/booking-cancellation.spec.js`

Run with:
```bash
npm test -- booking-cancellation.spec.js
```

## Security Considerations

1. **Authentication:** User must be logged in
2. **Authorization:** User can only cancel their own bookings
3. **CSRF Protection:** All POST requests require valid CSRF token
4. **Input Validation:** Booking ID sanitized with `intval()`
5. **SQL Injection Prevention:** Uses prepared statements
6. **Audit Trail:** Cancellations logged via `logSecurityEvent()`

## Integration with Existing Systems

### Refunds System
The booking cancellation integrates with the existing refund system (`process_refunds.php`):
- Cancelled bookings maintain their payment status
- Users can request refunds through the refunds page
- Admins can approve/reject refund requests based on cancellation policy

### Audit Logs
Cancellations are logged to the security audit log if the `logSecurityEvent()` function is available:
```php
logSecurityEvent($pdo, 'booking_cancelled', 
    "User cancelled booking ID: $booking_id for session: {$booking['session_title']}", 
    $user_id
);
```

## Future Enhancements

Potential improvements:
1. **Configurable Cancellation Window:** Make the 24-hour policy configurable in system settings
2. **Email Notifications:** Send email to user and coach when booking is cancelled
3. **Cancellation Reasons:** Allow users to provide a reason for cancellation
4. **Automatic Refunds:** Automatically process refunds for eligible cancellations
5. **Waitlist Management:** Automatically promote waitlisted users when booking is cancelled
6. **Coach Notifications:** Notify coach immediately when their session booking is cancelled

## Related Files

- `process_booking.php` - Backend cancellation logic
- `views/sessions_upcoming.php` - Frontend UI and JavaScript
- `process_refunds.php` - Handles refund requests for cancelled bookings
- `database_schema.sql` - Database structure (bookings table)
- `tests/booking-cancellation.spec.js` - Automated tests

## Version History

- **v1.0** (2026-01-28): Initial implementation
  - Added cancel_booking action
  - Implemented 24-hour policy
  - Added UI with confirmation dialog
  - Integrated with existing refund system
