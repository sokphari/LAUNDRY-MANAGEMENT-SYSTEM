# Laundry Management System — User Guide

## What this system is

The Laundry Management System is a web application for running the daily work of one laundry shop. It keeps customer details, service prices, laundry orders, order status, payments, receipts, reports, and staff activity in one place. The interface supports English and Khmer, light and dark themes, and desktop and mobile screens.

The system is intended for shop owners and staff. Customers do not sign in; staff create and update customer records for them.

## Why use it

- Keep customer contact information and order history together.
- Calculate order totals from selected services, quantities, and saved prices.
- See whether an order is received, processing, ready, collected, or cancelled.
- Track partial payments, refunds, and remaining balances.
- Print laundry tickets and payment receipts.
- Review store activity and staff work with role-based access.
- Keep an audit trail for important actions and manage database backups.

These features reduce reliance on handwritten records and make it easier to find an order, check its balance, and see who performed an action. The system does not provide customer self-service, delivery tracking, inventory management, or multi-branch management.

## Getting started

Before using the system, an administrator or developer should:

1. Install PHP 8.1 or newer, MySQL, and Node.js/npm.
2. Create the `laundry_db` database with `utf8mb4` encoding.
3. Copy `config/local.example.php` to `config/local.php` and enter the MySQL connection settings. Keep the real credentials private.
4. Install frontend dependencies and build the CSS:

   ```sh
   npm ci
   npm run build:css
   ```

5. Apply the database migrations and create the first Admin account:

   ```sh
   php database/migrate.php
   php database/create-admin.php
   ```

   The account command asks for the Admin’s name, username, optional email, and a password of at least 12 characters. It stores a password hash.

6. For local development, start PHP from the project root:

   ```sh
   php -S localhost:8000 -t public public/router.php
   ```

   Open `http://localhost:8000/login`. For production, use a web server whose document root is the project’s `public/` directory; the PHP development server is for local use only.

## Roles and access

### Admin

The Admin can review whole-store information, manage staff accounts and permissions, manage services, and use reports and audit logs. Only an Admin can open Backup & Restore. Permissions are also checked on the server, so hiding a navigation link is not the only access control.

### Staff

Staff use the operational parts of the system, such as customers, orders, payments, and viewing services. Their dashboard and personal work reports are scoped to their own staff account. This personal reporting scope does not prevent a staff member with the required permission from finding or processing a shop order created by a colleague.

Admins can change Staff role permissions and individual staff permission overrides; Admin access remains full. As a result, the exact modules visible to a staff member can vary by the permissions assigned to that account. Backup routes remain Admin-only.

## Daily workflow

1. Sign in with the username or email and password assigned to you. The first Admin account is created with `php database/create-admin.php`; an Admin can add staff accounts from **Users → User accounts**.
2. For a new customer, open **Customers → Create customer**. Enter the customer’s name and phone number; address and notes are optional. Phone numbers are stored as text so leading zeroes remain intact.
3. The Admin sets up available laundry services from **Services → Create service**. Enter the service name, billing unit (such as kilogram or piece), and price. Staff can view the service catalog when they have permission.
4. Open **Orders → Create order**. Choose a customer, set received and pickup dates, add one or more services, and enter each quantity. Add an optional deposit and notes, then review the summary and create the order.
5. Open an order to check customer details, service items, payment history, and status history. Move it through the allowed workflow: **Received → Processing → Ready → Collected**. A collected or cancelled order is final. Full payment is required before an order can be marked collected.
6. Record an additional payment from the order or Payments page when money is received. The system rejects a payment greater than the outstanding balance. Print a laundry ticket for the order and a payment receipt for a recorded payment.
7. Use the dashboard for the current work overview. Admins see store-wide information and staff activity; Staff see their own work. Use date range controls to review another period.

## Modules and their features

| Module | What it does | Common actions |
|---|---|---|
| **Dashboard** | Summarizes shop or personal activity, orders, payments, and charts for a selected period. | Choose a date range; review order status and monthly net payments. Admins can compare staff activity. Staff see their own activity and personal statistics. |
| **Customers** | Stores the people who bring laundry to the shop. | Search the list; create, view, and edit customer records; preserve phone numbers, addresses, and notes. Deactivation preserves related history. |
| **Services** | Maintains the shop’s service catalog and pricing. | Search and filter by active status; view, create, or edit a service; activate/deactivate it. A service with no order history can be deleted; a used service should be deactivated so past orders remain intact. Staff service access is view-only by default. |
| **Orders** | Tracks each laundry job from intake through pickup. | Search by order code, customer, or phone; filter by status and received date; create, view, or edit eligible orders; update status; review items and history; print a laundry ticket. A used service’s name, billing unit, and price are saved with the order. |
| **Payments** | Shows payment and refund transactions and their status. | Filter/search transactions, view payment details, print receipts, review reconciliation information, and export the payment data. Admins can correct or refund payments when granted the related permission. |
| **Reports** | Summarizes order value, payments, refunds, balances, order statuses, and service performance for a selected range. | Choose a date range, apply custom dates, and export a PDF or CSV report. Staff report data is limited to their own work; report access depends on permissions. |
| **Users** *(Admin)* | Manages staff accounts and access. | Create or edit a user, enable/disable an account, reset a password, change a role, and configure role permissions or per-user overrides. Users can update their own profile separately. |
| **My Profile** | Lets a signed-in user maintain personal account details. | Change the displayed name, email, gender, and profile image using an uploaded image or URL. Usernames are managed by an Admin; password changes are under account security. |
| **Audit Logs** *(Admin by default)* | Records important actions and security events. | Search/filter activity, review who performed an action and when, and inspect the recorded event details. Access can be adjusted through permissions. |
| **Backup & Restore** *(Admin only)* | Creates and restores protected database snapshots. | Create/download a manual backup; configure automatic backup frequency and retention; restore a selected snapshot after confirmation. A pre-restore safety backup is created, and backup/restore/settings activity is audited. Backup files are stored outside `public/`. |
| **Notifications** | Shows security and account activity relevant to the signed-in user. | Check the unread count, review recent login/account events, and mark notifications as read. Admins receive broader security activity; Staff notification scope is limited. |

## Important order and payment rules

- Order status and payment status are separate. An order can be **Ready** while still owing money.
- Service prices and billing units are saved on each order item. Later catalog edits do not change the historical order or its receipt.
- Quantities must be positive. Piece quantities must be whole numbers; kilogram quantities can use up to three decimal places.
- A payment must be positive and cannot exceed the remaining balance.
- A cancelled order cannot receive additional payments. An order with recorded payments may require a refund or payment correction, subject to permission.
- Admins should review reports with the selected date range in mind: order totals are based on order received dates, while payment and refund totals use their transaction dates.

## Useful settings and controls

- Use the language control to switch between English and Khmer.
- Use the theme control to switch between light and dark mode.
- On mobile, open navigation with the menu button. Order lists can use compact cards or horizontal scrolling depending on language and screen size; swipe sideways when the page displays a scroll hint.
- If automatic backups are disabled, existing backup files are retained, but no new scheduled backups are made. Re-enable the schedule or create a manual backup to protect new data.

## Verification and known limits

The application includes a manual workflow checklist in the project README, but this repository has no automated test files or test runner configured. A full end-to-end verification requires a reachable MySQL database, migrated schema, and test Admin and Staff accounts. Use non-production test data when checking order, payment, refund, restore, and permission flows.

Backups need the server scheduler configured to run `database/backup-scheduler.php`; enabling automatic backups in the UI alone does not schedule a cron job. Off-site storage also needs a protected remote destination configured separately.
