# Lead Management System - Phase 1 MVP

A full-stack application with Laravel backend and Next.js frontend for managing leads, service providers, and Stripe subscriptions.

## Project Structure

```
localhost/
├── backend/          # Laravel API
│   ├── app/
│   │   ├── Http/Controllers/Api/     # API controllers
│   │   ├── Models/                    # Eloquent models
│   │   └── Http/Middleware/           # Custom middleware (CSRF)
│   ├── database/migrations/           # Database migrations
│   ├── routes/api.php                 # API routes
│   └── config/                        # Configuration files
└── frontend/         # Next.js frontend
    ├── app/                           # Next.js app directory
    │   ├── admin/                     # Admin pages
    │   └── lead/[location]/           # Lead capture form
    └── lib/                           # Utilities (API client)
```

## Tech Stack

### Backend
- **Laravel 12** - PHP framework
- **Laravel Sanctum** - API token authentication
- **MySQL** - Database
- **Stripe PHP SDK** - Payment processing

### Frontend
- **Next.js 16** - React framework with App Router
- **TypeScript** - Type safety
- **Tailwind CSS** - Styling
- **Axios** - HTTP client

### Key Features
- Token-based API authentication (Bearer tokens)
- CSRF protection disabled for API routes (token-based auth)
- MySQL database with proper foreign key relationships
- Stripe webhook integration for subscription management

## Features

### 1. Tablet-Friendly Lead Capture
- Single-page web form with fields: name, phone, email, zip code, project type, timing, notes
- Basic validation (required fields, proper email/phone format)
- Submit to backend via API
- Confirmation/success screen
- Location-based via URL parameter (e.g., `/lead/location-slug`)

### 2. Admin Dashboard
- Secure login (single admin account)
- Lead list table with filters (by date and location)
- Lead detail view (full info)
- Ability to add/edit service providers
- Assign one or more providers to each location
- Ability to manually reassign a lead
- Simple status dropdown for each lead (New / Contacted / Closed)

### 3. Stripe Subscription Billing
- Service providers must have active Stripe subscription to be eligible for leads
- Stripe Checkout or Billing Portal link to sign up/pay
- Store Stripe customer/subscription status in database
- Webhooks required to handle status changes (active, canceled, past_due)
- In Admin dashboard, show subscription status for each provider
- Business rule: Only active subscribers are eligible to receive leads

### 4. Provider Portal
- **Provider Signup**: New providers can create accounts with email and password
- **Provider Login**: Secure authentication using Laravel Sanctum
- **Subscription Management**: Providers can subscribe to plans directly from their portal
- **Lead Access Control**: Only providers with active subscriptions can access assigned leads
- **Status Updates**: Providers can update lead status (new, contacted, closed)
- **Subscription Status**: Real-time subscription status checking with automatic access blocking for inactive accounts

## Setup Instructions

### Backend (Laravel)

1. Navigate to the backend directory:
```bash
cd backend
```

2. Install dependencies:
```bash
composer install
```

3. Copy environment file:
```bash
cp .env.example .env
```

4. Generate application key:
```bash
php artisan key:generate
```

5. Configure database in `.env` (already set to MySQL):
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lead_management
DB_USERNAME=root
DB_PASSWORD=root
```

   **Important:** Before running migrations:
   - Create the MySQL database:
     ```bash
     mysql -u root -p
     CREATE DATABASE lead_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     EXIT;
     ```
   - Update `DB_USERNAME` and `DB_PASSWORD` in `.env` with your MySQL credentials
   - **Note:** If using MAMP, the default password is usually `root`

6. Configure Stripe in `.env`:
```env
STRIPE_KEY=your_stripe_publishable_key
STRIPE_SECRET=your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=your_stripe_webhook_secret
STRIPE_PRICE_ID=your_stripe_price_id
```

7. Configure frontend URL in `.env`:
```env
FRONTEND_URL=http://localhost:3000
```

8. Run migrations:
```bash
php artisan migrate
```

   **Note:** Migration order is important. The migrations are ordered as:
   - Service providers (must be created first)
   - Locations
   - Location-service provider pivot table
   - Leads (references service providers and locations)
   - Stripe subscriptions (references service providers)

9. Run the migration to add password field to service_providers:
```bash
php artisan migrate
```
   This will add the `password` column to the `service_providers` table if not already present.

10. Create an admin user:
```bash
php artisan tinker
```
Then:
```php
$user = new App\Models\User();
$user->name = 'Admin';
$user->email = 'admin@example.com';
$user->password = Hash::make('password');
$user->save();
```

   **Default Admin Credentials:**
   - Email: `admin@example.com`
   - Password: `password`
   
   **Important:** Change these credentials in production!

11. (Optional) Create a test provider with password:
```php
$provider = new App\Models\ServiceProvider();
$provider->name = 'Test Provider';
$provider->email = 'provider@example.com';
$provider->password = Hash::make('password123');
$provider->save();
```
   Or use the provider signup endpoint: `POST /api/provider/signup`

12. Start the server:
```bash
php artisan serve
```

The API will be available at `http://localhost:8000`

### Frontend (Next.js)

1. Navigate to the frontend directory:
```bash
cd frontend
```

2. Install dependencies:
```bash
npm install
```

3. Create `.env.local` file:
```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

4. Start the development server:
```bash
npm run dev
```

The frontend will be available at `http://localhost:3000`

## API Endpoints

### Public Endpoints
- `POST /api/leads` - Submit a lead
- `GET /api/locations` - Get list of locations

### Provider Endpoints

#### Authentication (Public)
- `POST /api/provider/signup` - Provider signup (creates new provider account)
  - Body: `{ name, email, phone?, address?, password }`
  - Returns: `{ provider, token, message }`
- `POST /api/provider/login` - Provider login
  - Body: `{ email, password }`
  - Returns: `{ provider, token, has_active_subscription, subscription_status }`

#### Provider Protected Routes (Requires Authentication)
- `GET /api/provider/user` - Get current provider info
- `POST /api/provider/logout` - Logout

#### Subscription Management
- `GET /api/provider/subscription/status` - Get subscription status
  - Returns: `{ has_active_subscription, subscription }`
- `POST /api/provider/subscription/checkout` - Create Stripe checkout session
  - Returns: `{ checkout_url }` - Redirect provider to Stripe checkout
- `GET /api/provider/subscription/billing-portal` - Get Stripe billing portal URL
  - Returns: `{ portal_url }` - Redirect provider to manage subscription

#### Leads (Requires Active Subscription)
- `GET /api/provider/leads` - List assigned leads (with filters: status, date_from, date_to)
  - **Access Control**: Returns 403 if subscription is not active
  - Returns: `{ data: [...], message? }` or error with `has_active_subscription: false`
- `GET /api/provider/leads/{id}` - Get lead details
  - **Access Control**: Returns 403 if subscription is not active or lead not assigned to provider
- `PUT /api/provider/leads/{id}` - Update lead status
  - Body: `{ status: 'new' | 'contacted' | 'closed' }`
  - **Access Control**: Returns 403 if subscription is not active or lead not assigned to provider

### Admin Endpoints (Requires Authentication)
- `POST /api/admin/login` - Admin login
- `GET /api/admin/user` - Get current user
- `POST /api/admin/logout` - Logout

#### Leads
- `GET /api/admin/leads` - List leads (with filters: location_id, status, date_from, date_to)
- `GET /api/admin/leads/{id}` - Get lead details
- `PUT /api/admin/leads/{id}` - Update lead
- `PUT /api/admin/leads/{id}/reassign` - Reassign lead to provider

#### Service Providers
- `GET /api/admin/service-providers` - List providers
- `POST /api/admin/service-providers` - Create provider
  - Body: `{ name, email, phone?, address?, password? }`
  - Password is optional when creating (can be set later)
- `GET /api/admin/service-providers/{id}` - Get provider
- `PUT /api/admin/service-providers/{id}` - Update provider
  - Body: `{ name?, email?, phone?, address?, password? }`
  - Password: If provided, will be hashed and updated. If empty, current password is kept.
- `DELETE /api/admin/service-providers/{id}` - Delete provider
- `POST /api/admin/service-providers/{id}/stripe-checkout` - Create Stripe checkout session (admin-initiated)
- `GET /api/admin/service-providers/{id}/billing-portal` - Get Stripe billing portal URL (admin-initiated)

#### Locations
- `GET /api/admin/locations` - List locations
- `POST /api/admin/locations` - Create location
- `GET /api/admin/locations/{id}` - Get location
- `PUT /api/admin/locations/{id}` - Update location
- `DELETE /api/admin/locations/{id}` - Delete location
- `POST /api/admin/locations/{id}/assign-providers` - Assign providers to location

### Webhooks
- `POST /api/stripe/webhook` - Stripe webhook endpoint

## Frontend Routes

### Public
- `/lead/[location]` - Lead capture form (e.g., `/lead/downtown-office`)

### Admin
- `/admin/login` - Admin login
- `/admin/dashboard` - Leads list
- `/admin/leads/[id]` - Lead detail
- `/admin/service-providers` - Service providers management
- `/admin/locations` - Locations management

### Provider
- `/provider/signup` - Provider registration
- `/provider/login` - Provider login
- `/provider/dashboard` - Provider dashboard (assigned leads)
- `/provider/leads/[id]` - Lead detail (provider view)
- `/provider/subscription` - Subscription management and checkout

## Stripe Setup

1. Create a Stripe account and get your API keys
2. Create a product and price in Stripe Dashboard
3. Set up a webhook endpoint pointing to: `https://yourdomain.com/api/stripe/webhook`
4. Select events to listen for:
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
5. Copy the webhook signing secret to your `.env` file

## Database Schema

### Tables
- `users` - Admin users
- `locations` - Physical locations
- `leads` - Customer leads
- `service_providers` - Service providers (includes password field for authentication)
- `location_service_provider` - Pivot table for location-provider relationships
- `stripe_subscriptions` - Stripe subscription tracking
- `personal_access_tokens` - Laravel Sanctum tokens for API authentication

### Service Provider Model
- Extends `Authenticatable` for authentication
- Uses `HasApiTokens` trait for Laravel Sanctum
- Password is hashed automatically using Laravel's Hash facade
- Has relationship to `stripe_subscriptions` table
- Method `hasActiveSubscription()` checks if subscription status is 'active'

## Business Rules

1. Only service providers with `active` Stripe subscriptions are eligible to receive leads
2. When a lead is submitted, it's automatically assigned to the first eligible provider for that location
3. Admins can manually reassign leads to any provider
4. Lead status can be: `new`, `contacted`, or `closed`
5. **Provider Access Control**: Providers can only access leads if they have an active subscription
   - Inactive providers receive 403 error with message: "Please contact admin to activate your account or subscribe to a plan"
   - Providers can subscribe directly through their portal using Stripe Checkout
   - Subscription status is checked on every lead API call
6. **Provider Authentication**: Providers authenticate using email/password with Laravel Sanctum tokens
7. **Provider Signup**: New providers can self-register, but must subscribe before accessing leads

## Development Notes

- The backend uses Laravel Sanctum for API authentication (token-based)
- Both admin and provider authentication use Laravel Sanctum tokens
- The frontend stores the auth token in localStorage
- API routes are exempt from CSRF protection (using custom `VerifyCsrfToken` middleware)
- CORS is configured to allow requests from the frontend
- Stripe webhooks are verified using the webhook secret
- MySQL database is used (configured in `.env`)
- Migration order is critical - service providers must be created before leads
- **Provider Authentication**: ServiceProvider model extends Authenticatable and uses HasApiTokens
- **Subscription Access Control**: All provider lead endpoints check subscription status before allowing access
- **Password Management**: Provider passwords are hashed using Laravel's Hash facade (bcrypt)
- **Subscription Status**: Checked via `hasActiveSubscription()` method which verifies status === 'active'

## Troubleshooting

### CSRF Token Mismatch Error
If you encounter "CSRF token mismatch" errors:
- API routes are automatically exempt from CSRF (configured in `app/Http/Middleware/VerifyCsrfToken.php`)
- Clear cache: `php artisan config:clear && php artisan cache:clear`
- Make sure you're using token-based authentication (Bearer token in Authorization header)

### CORS Issues
- CORS is automatically handled by Laravel for API routes
- If you encounter CORS errors, check that your frontend URL matches the allowed origins
- The default configuration should work for `http://localhost:3000`

### Authentication Issues
- Make sure the token is being sent in the Authorization header: `Bearer {token}`
- Check that the token is stored in localStorage after login
- If token expires, user will be redirected to login page automatically

### Stripe Webhook Issues
- Make sure the webhook secret is correctly set in `.env`
- Use Stripe CLI for local testing: `stripe listen --forward-to localhost:8000/api/stripe/webhook`
- Check Laravel logs for webhook errors: `storage/logs/laravel.log`
- Verify webhook events are being received: `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`
- **Important**: Webhook must update subscription status to 'active' for providers to access leads

### Provider Authentication Issues
- Make sure providers have passwords set (either via admin panel or signup)
- Check that password field exists in `service_providers` table: `php artisan migrate`
- Verify token is being sent: `Authorization: Bearer {token}` header
- Provider login returns `has_active_subscription` flag - check this before allowing access

### Provider Subscription Access Issues
- Providers with inactive subscriptions will receive 403 errors when accessing leads
- Error message: "Please contact admin to activate your account or subscribe to a plan"
- Check subscription status: `GET /api/provider/subscription/status`
- Verify Stripe webhook is updating subscription status correctly
- Only subscriptions with `status = 'active'` allow lead access

### Database Issues
- Make sure migrations have been run: `php artisan migrate`
- If migrations fail, check the order: `php artisan migrate:fresh` (drops all tables and re-runs migrations)
- Check database connection in `.env`
- Verify MySQL is running: `mysql -u root -p -e "SHOW DATABASES;"`
- Check database exists: `mysql -u root -p -e "USE lead_management; SHOW TABLES;"`

### React Hydration Errors
- Browser extensions (like Grammarly) can cause hydration warnings
- The `suppressHydrationWarning` prop is added to the body tag to handle this
- This is safe and won't affect functionality

### Migration Order Errors
- If you get foreign key constraint errors, the migration order might be wrong
- Service providers must be created before leads
- Run `php artisan migrate:fresh` to reset and re-run all migrations in correct order

## Quick Start Checklist

- [ ] MySQL database created (`lead_management`)
- [ ] `.env` file configured with database credentials
- [ ] `.env` file configured with Stripe keys (optional for basic testing)
- [ ] Migrations run successfully (`php artisan migrate`)
- [ ] Admin user created
- [ ] Laravel server running (`php artisan serve`)
- [ ] Frontend `.env.local` configured with API URL
- [ ] Next.js server running (`npm run dev`)
- [ ] Test admin login at `http://localhost:3000/admin/login`
- [ ] Create a test location in admin dashboard
- [ ] Test lead form at `http://localhost:3000/lead/{location-slug}`

## Configuration Files

### Backend `.env` Required Variables
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lead_management
DB_USERNAME=root
DB_PASSWORD=root

STRIPE_KEY=your_stripe_publishable_key
STRIPE_SECRET=your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=your_stripe_webhook_secret
STRIPE_PRICE_ID=your_stripe_price_id

FRONTEND_URL=http://localhost:3000
```

### Frontend `.env.local` Required Variables
```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

## Provider Subscription Flow

1. **Provider Signup**: Provider creates account via `POST /api/provider/signup`
2. **Login**: Provider logs in via `POST /api/provider/login`
   - Response includes `has_active_subscription` flag
   - If false, redirect to subscription page
3. **Subscribe**: Provider creates checkout session via `POST /api/provider/subscription/checkout`
   - Redirects to Stripe Checkout
   - After payment, Stripe webhook updates subscription status to 'active'
4. **Access Leads**: Once subscription is active, provider can access assigned leads
5. **Manage Subscription**: Provider can access billing portal via `GET /api/provider/subscription/billing-portal`

### Subscription Status Values
- `active` - Provider can access leads
- `incomplete` - Payment not completed, cannot access leads
- `canceled` - Subscription canceled, cannot access leads
- `past_due` - Payment failed, cannot access leads
- `trialing` - Trial period, can access leads (if implemented)

## Next Steps (Future Enhancements)

- Email notifications for new leads
- Lead assignment algorithms (round-robin, geographic proximity, etc.)
- Analytics dashboard
- Export leads to CSV
- Multiple admin users with roles
- Lead notes/history tracking
- SMS notifications
- Real-time lead notifications
- Provider performance metrics
- Provider trial periods
- Multiple subscription tiers/plans

