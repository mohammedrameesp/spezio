# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Spezio Apartments is a full-stack booking system for apartment rentals with Razorpay payment integration.

**Tech Stack:** PHP 7.4+ (PDO), MySQL, Vanilla JavaScript, HTML5/CSS3, Apache (XAMPP)

## Development Environment

- **Local URL:** http://localhost:8080/spezio
- **Database:** MySQL on port 3307 (non-standard XAMPP port), database name: `spezio_booking`
- **Admin Panel:** /admin (default login: admin / admin123)

## Database Commands

```bash
# Connect to MySQL (XAMPP)
C:\xampp\mysql\bin\mysql.exe -u root -P 3307

# Run schema (fresh install)
mysql -u root -P 3307 spezio_booking < sql/schema.sql
```

## Architecture

### API Layer (`/api/`)
- `config.php` - All configuration constants and environment settings
- `db.php` - Singleton database connection, helper functions (`getDB()`, `dbQuery()`, `dbFetchOne()`, `dbFetchAll()`, `dbInsert()`, `dbUpdate()`)
- `functions.php` - Core business logic (pricing, availability, bookings, coupons)
- `security.php` - CSRF tokens, rate limiting, security headers

### Key API Endpoints
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `create-order.php` | POST | Creates booking + Razorpay order |
| `verify-payment.php` | POST | Verifies Razorpay payment signature |
| `check-availability.php` | POST | Checks room availability for dates |
| `calculate-price.php` | POST | Calculates pricing with discounts |
| `validate-coupon.php` | POST | Validates coupon codes |
| `get-rooms.php` | GET | Lists active rooms |
| `webhook.php` | POST | Razorpay webhook handler |

### Admin Panel (`/admin/`)
- `includes/auth.php` - Session-based authentication with role-based access
- Pages: dashboard, bookings, rooms, pricing, coupons, calendar, blocked-dates, reports, settings

### Frontend
- `booking.html` + `js/booking.js` - Multi-step booking form with Razorpay checkout
- `css/booking.css` - Uses CSS variables mapped from `css/style.css`

## Key Patterns

### Database Operations
All SQL uses prepared statements via PDO. Transactions available via `dbBeginTransaction()`, `dbCommit()`, `dbRollback()`.

### Pricing System
Duration-based discounts stored in `duration_discounts` table. Calculation order:
1. Base room rate → 2. Duration discount → 3. Extra bed charges → 4. Coupon discount

### Security
- CSRF tokens required on all POST requests (1-hour expiration)
- Rate limiting per endpoint (file-based storage in `/logs/`)
- Razorpay signature verification using HMAC-SHA256

### Booking Workflow
Room selection → Date selection → Guest details → Coupon (optional) → Razorpay payment → Webhook confirmation → Email notification

## Database Tables

- `rooms` - Properties with tiered pricing
- `bookings` - Guest bookings with payment tracking
- `coupons` - Promotional codes (percentage or fixed)
- `blocked_dates` - Maintenance/unavailable dates
- `duration_discounts` - Bulk booking discounts
- `admin_users` - Admin authentication (bcrypt passwords)
- `settings` - Key-value system configuration
- `activity_log` - Admin action audit trail

## Configuration

All config in `/api/config.php`:
- `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET` - Payment credentials
- `MIN_BOOKING_DAYS`, `MAX_BOOKING_DAYS`, `ADVANCE_BOOKING_DAYS` - Booking constraints
- `APP_ENV` - Auto-detected (development/production) based on hostname
