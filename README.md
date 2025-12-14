# SES Dashboard

A Laravel-based dashboard application for tracking AWS SES email analytics and events. Monitor email delivery, bounces, complaints, opens, and clicks with multi-user and multi-project support.

The idea and main structure is taken from the [SES Dashboard](https://github.com/Nikeev/sesdashboard) MIT Copyright (c) 2020 Nikeev, but this project is completely different. It uses Laravel, has unit tests, offers multi-user support and multi-project management, has built-in webhook payload logging, which can be turned on and off, and tracks and logs SES events correctly for tricky cases (like open/click events for single email sent to multiple recipients).




## Features

- **Multi-user Support:** Admin and regular user roles
- **Multi-project Management:** Organize emails by projects
- **Email Tracking:** Monitor delivery, bounces, complaints, opens, clicks
- **AWS SES Integration:** Webhook processing for real-time events
- **Analytics Dashboard:** Charts and statistics with filters
- **Data Export:** CSV and Excel export capabilities with filters
- **Responsive UI:** Vue.js frontend with Bootstrap

## Architecture

- **Backend:** Laravel 11 with MySQL
- **Frontend:** Vanilla JavaScript with Bootstrap 5 and Chart.js
- **Build Tool:** Laravel Mix
- **Email Service:** AWS SES
- **Webhook Processing:** SNS integration with idempotency

## Project Kickstart

### Prerequisites

- PHP 8.2+
- MySQL 8.0+
- Composer
- **Optional:** Node.js 18+ and npm/yarn (only needed if you want to modify or rebuild frontend assets)

> **Note:** The compiled frontend assets are included in the repository, so you don't need Node.js to run the application. You only need it if you want to modify Vue components, styles, or rebuild the assets.

### Quick Start

1. **Clone and install dependencies:**
   ```bash
   git clone <repository-url>
   cd <repository-name>
   composer install
   ```
   > No need to run `npm install` - compiled assets are already included!

2. **Environment configuration:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database setup:**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

4. **Start the development server:**
   ```bash
   php artisan serve
   ```

### Frontend Development (Optional)

If you want to modify Vue components, styles, or rebuild frontend assets:

1. **Install Node.js dependencies:**
   ```bash
   npm install
   ```

2. **Build assets for development:**
   ```bash
   npm run dev
   ```

3. **Watch for changes (development):**
   ```bash
   npm run watch
   ```

4. **Build for production:**
   ```bash
   npm run prod
   ```
   
## Initial Data & Admin User

The database seeder creates an initial admin user:

- **Email:** `admin@example.com`
- **Password:** `password`
- **Role:** Administrator

This user has full access to all features including user management and project administration.

## Environment Configuration

Update the following parameters in your `.env` file:

### Database Configuration
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### AWS SES Configuration - used for email sending test only
```env
AWS_ACCESS_KEY_ID=your_aws_access_key
AWS_SECRET_ACCESS_KEY=your_aws_secret_key
AWS_DEFAULT_REGION=your_aws_region

MAIL_MAILER=ses
MAIL_FROM_ADDRESS=your_verified_email@domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Application Settings
```env
APP_NAME="SES Dashboard"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

```

### Optional: Webhook Debug Logging
```env
# Enable webhook payload logging for debugging (disable in production)
WEBHOOK_DEBUG_LOG=false
```

## Testing

Run the test suite:
```bash
vendor/bin/phpunit
```

## Webhook Setup

Configure your AWS SES to send notifications to:
```
POST https://<your-domain.com>/webhook/{project_token}
```

Each project has a unique token for webhook authentication.


## TODO
- [ ] Add more detailed documentation for setting up AWS SES and SNS
