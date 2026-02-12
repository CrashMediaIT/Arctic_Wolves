# Arctic Wolves REST API Documentation

## Overview

The Arctic Wolves REST API provides programmatic access to the Arctic Wolves hockey coaching management platform. It is designed for use by external applications including:

- **ACVideoReview** — Video review application for coaches and athletes
- **ACWolvesAPP** — Arctic Wolves mobile/web application

**Base URL:** `https://api.arcticwolves.ca`  
**API Version:** `v1`

## Authentication

All API requests (except `POST /v1/auth/login`) require an API key. You can authenticate using one of the following methods:

### Authorization Header (Recommended)

```
Authorization: Bearer YOUR_API_KEY
```

### X-API-Key Header

```
X-API-Key: YOUR_API_KEY
```

### Obtaining an API Key

**Option 1: Login via API**

```bash
curl -X POST https://api.arcticwolves.ca/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "your_password"}'
```

Response:
```json
{
  "success": true,
  "api_key": "your_generated_api_key",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "name": "John Smith",
    "role": "coach"
  },
  "expires_at": "2026-03-13T17:13:42Z"
}
```

**Option 2: Admin-Generated Key**

Administrators can generate API keys from the admin panel under System Tools > API Keys.

## Response Format

All responses are JSON with the following structure:

### Success Response

```json
{
  "success": true,
  "data": { ... }
}
```

### Paginated Response

```json
{
  "success": true,
  "data": [ ... ],
  "pagination": {
    "total": 50,
    "page": 1,
    "per_page": 20,
    "pages": 3
  }
}
```

### Error Response

```json
{
  "success": false,
  "error": "Description of the error"
}
```

## Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/v1/auth/login` | Authenticate and get API key |
| POST/GET | `/v1/auth/validate` | Validate an API key |
| POST | `/v1/auth/logout` | Deactivate an API key |

### Users

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/users/me` | Get current user profile |
| GET | `/v1/users` | List users (coach/admin only) |
| GET | `/v1/users/{id}` | Get user details |

### Athletes

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/athletes` | List athletes |
| GET | `/v1/athletes/{id}` | Get athlete details |
| POST | `/v1/athletes` | Create an athlete (coach/admin) |
| PUT | `/v1/athletes/{id}` | Update an athlete |

### Sessions

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/sessions` | List sessions |
| GET | `/v1/sessions/{id}` | Get session details |
| GET | `/v1/sessions/{id}/athletes` | Get session participants |

**Query Parameters for GET /v1/sessions:**
- `status` — Filter: `scheduled`, `completed`, `cancelled`
- `date_from` — Filter from date (YYYY-MM-DD)
- `date_to` — Filter to date (YYYY-MM-DD)
- `coach_id` — Filter by coach
- `team_id` — Filter by team

### Teams

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/teams` | List active teams |
| GET | `/v1/teams/{id}` | Get team details |
| GET | `/v1/teams/{id}/roster` | Get team roster |

### Bookings

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/bookings` | List bookings |
| GET | `/v1/bookings/{id}` | Get booking details |
| POST | `/v1/bookings` | Create a booking |

### Drills

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/drills` | List drills |
| GET | `/v1/drills/{id}` | Get drill details |

**Query Parameters for GET /v1/drills:**
- `category` — Filter by category
- `difficulty` — Filter by difficulty level
- `search` — Search in title and description

### Practice Plans

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/practice-plans` | List practice plans |
| GET | `/v1/practice-plans/{id}` | Get practice plan with drills |
| POST | `/v1/practice-plans` | Create a practice plan |
| PUT | `/v1/practice-plans/{id}` | Update a practice plan |
| DELETE | `/v1/practice-plans/{id}` | Delete a practice plan |

### Evaluations

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/evaluations` | List evaluations |
| GET | `/v1/evaluations/{id}` | Get evaluation details |
| POST | `/v1/evaluations` | Create an evaluation |

### Videos

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/videos` | List videos (filterable) |
| GET | `/v1/videos/{id}` | Get video details |
| POST | `/v1/videos/{id}/review` | Submit a video review |
| PUT | `/v1/videos/{id}` | Update video notes |
| DELETE | `/v1/videos/{id}` | Delete a video |

**Query Parameters for GET /v1/videos:**
- `page` — Page number (default: 1)
- `per_page` — Results per page (default: 20, max: 100)
- `athlete_id` — Filter by athlete
- `status` — Filter by status: `pending_review`, `reviewed`, `archived`
- `video_category` — Filter: `drill`, `game`
- `video_type` — Filter: `drill_review`, `coach_review`, `uploaded_by_athlete`

### Messages

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/messages` | List messages |
| GET | `/v1/messages/{id}` | Get message details |
| POST | `/v1/messages` | Send a message |
| PUT | `/v1/messages/{id}/read` | Mark message as read |

### Notifications

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/notifications` | List notifications |
| PUT | `/v1/notifications/{id}` | Mark notification as read |
| PUT | `/v1/notifications/read-all` | Mark all as read |

### Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/dashboard/stats` | Get dashboard statistics |
| GET | `/v1/dashboard/schedule` | Get upcoming schedule |

### Reports

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/reports` | List available/scheduled reports |
| POST | `/v1/reports/generate` | Generate a report |

### Finance

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/finance/overview` | Financial summary (admin) |
| GET | `/v1/finance/transactions` | List transactions (admin) |
| GET | `/v1/finance/billing` | List billing / payments |

### HR

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/hr/payroll` | List payroll records (admin) |
| GET | `/v1/hr/contracts` | List employee contracts (admin) |
| GET | `/v1/hr/time-tracking` | List staff shifts (admin) |

### Health

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/health/nutrition/{athleteId}` | Get nutrition plans |
| GET | `/v1/health/workouts/{athleteId}` | Get workout plans |

### Shop

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/shop/products` | List merchandise products |
| GET | `/v1/shop/categories` | List product categories |
| GET | `/v1/shop/cart` | Get cart contents |
| POST | `/v1/shop/cart` | Add item to cart |

### Admin

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/v1/admin/users` | List all users (admin) |
| GET | `/v1/admin/audit-logs` | List audit logs (admin) |
| GET | `/v1/admin/system-health` | System health status (admin) |
| GET | `/v1/admin/permissions` | List permissions (admin) |
| GET | `/v1/admin/settings` | Get system settings (admin) |
| PUT | `/v1/admin/settings` | Update system settings (admin) |

## Rate Limiting

API requests are subject to rate limiting. Include appropriate retry logic in your applications.

## CORS

The API supports Cross-Origin Resource Sharing (CORS) for all origins. Preflight OPTIONS requests are handled automatically.

## Deployment

### DNS Setup

Add an A record for `api.arcticwolves.ca` pointing to the server IP address.

### NGINX Configuration

The NGINX server block for `api.arcticwolves.ca` is included in `deployment/arctic_wolves.conf`. After DNS is configured:

1. Copy the configuration to your NGINX container
2. Enable SSL by uncommenting the HTTPS server block
3. Restart NGINX

### SSL/HTTPS

For production, enable the SSL server block in the NGINX configuration and ensure your SSL certificate covers `api.arcticwolves.ca` (use a wildcard cert for `*.arcticwolves.ca` or add the subdomain to your certificate).
