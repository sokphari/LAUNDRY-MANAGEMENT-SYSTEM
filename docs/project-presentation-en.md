# Laundry Management System
## Project Presentation — English

### 1 — Project overview

**Laundry Management System** is a web application for managing the everyday work of a single laundry shop. It brings customers, laundry services, orders, status updates, payments, receipts, reports, staff activity, and backups into one system.

**Audience:** laundry shop owners, administrators, and staff.

**Presenter cue:** Introduce the project as an operations tool for the complete journey from receiving garments to returning them to the customer.

### 2 — Why create this project?

Laundry shops need to keep customer contacts, garment details, pickup dates, prices, payments, and order progress organized. When those details are spread across paper notes or separate records, finding an order and checking its balance can take extra time.

This system provides one shared place to record and follow that work. It is designed to help staff find order information quickly, calculate charges consistently, and let owners review shop activity.

### 3 — What the system does

- Keeps customer contact details and service prices.
- Creates orders with one or more services and quantities.
- Tracks orders through **Received → Processing → Ready → Collected** (or **Cancelled**).
- Records deposits, additional payments, refunds, and remaining balances.
- Prints laundry tickets and payment receipts.
- Shows operational summaries, reports, and staff activity according to permissions.
- Maintains audit records and protected database backups.

### 4 — Users and access

**Admin / Owner**

- Sees shop-wide dashboard information and staff activity.
- Manages staff accounts, role permissions, and services.
- Uses reports, audit logs, and Backup & Restore.

**Staff**

- Sees personal dashboard statistics and activity.
- Performs permitted daily work with customers, orders, and payments.
- Can view the service catalog by default.
- Can help process shop orders created by colleagues when granted the relevant operational permissions.

Access is checked on the server. The Admin can configure Staff role permissions and individual Staff overrides. Backup and restore routes are Admin-only.

### 5 — Typical laundry workflow

```text
Find or add customer
        ↓
Create order and select services / quantities
        ↓
Review total, record optional deposit, create order
        ↓
Received → Processing → Ready
        ↓
Record remaining payment → Mark Collected → Print receipt
```

Order charges use the service price and billing unit saved when the order is created. Later changes to the service catalog do not rewrite old order items. The system requires full payment before an order can be marked Collected.

### 6 — Main modules

| Module | Main features |
|---|---|
| **Dashboard** | Date filters, order and payment statistics, charts; Admin shop-wide and Staff personal views. |
| **Customers** | Search, create, view, and edit customer records; preserve contact details and history. |
| **Services** | Create and edit service prices and billing units, filter by status, activate/deactivate; Staff view-only by default. |
| **Orders** | Create, search, filter, edit eligible orders, update status, view order/payment history, print laundry tickets. |
| **Payments** | Review transactions and refunds, record eligible payments, reconcile balances, view and print receipts, export payment data. |
| **Reports** | Filter date ranges; review orders, payments, refunds, balances, and service performance; export PDF or CSV. |
| **Users & Profile** | Admin manages accounts and permissions; each user maintains their personal profile. |
| **Audit Logs & Notifications** | Review security and account events, unread notifications, and recorded system activity. |
| **Backup & Restore** | Admin creates and downloads backups, configures scheduled backups, and restores a confirmed snapshot. |

Actual access can vary according to assigned permissions; Backup & Restore remains Admin-only.

### 7 — Business safeguards

- Passwords are stored as hashes; login uses server-side sessions and rate limiting.
- Database queries use PDO prepared statements, and state-changing forms use CSRF protection.
- The server checks roles and permissions for protected actions.
- Order changes, payments, refunds, and key account/security actions are recorded in audit logs.
- Payment amounts are validated against the remaining order balance.
- Historical order items keep their saved service name, billing unit, and price.
- Backup files are stored outside the public web folder; restore requires confirmation and creates a safety backup first.

### 8 — Project advantages

- **More organized operations:** customer and order information is kept together.
- **Faster lookup:** staff can search orders by order code, customer, or phone.
- **Clear payment tracking:** paid amounts, refunds, and balances are visible.
- **Consistent order pricing:** line totals are calculated from stored service prices and quantities.
- **Shared work with personal accountability:** staff can serve shop orders while dashboards and reports identify each person’s activity.
- **Better owner visibility:** Admin dashboards, reports, notifications, and audit logs support shop oversight.
- **A safer recovery path:** manual and scheduled backups help protect operational data.

These features support better day-to-day decisions; they do not replace staff review or a separate off-site backup plan.

### 9 — Technology and structure

- **Backend:** Native PHP 8, PDO, and MySQL.
- **Frontend:** PHP-rendered HTML, Tailwind CSS, and vanilla JavaScript with Fetch-based updates.
- **Architecture:** routes and middleware → controllers → services/models → MySQL → reusable views.
- **Interface:** English and Khmer, light and dark themes, responsive desktop and mobile layouts.

### 10 — Current scope and next opportunities

The current system focuses on the daily workflow for **one laundry shop**. It includes customer, service, order, payment, reporting, user access, audit, notification, and backup functions.

Features outside the current scope include a customer self-service portal, delivery tracking, inventory management, and multi-branch operation. These can be considered as future extensions if the business needs them.

### Closing

**Laundry Management System helps a shop record work consistently, serve orders across the team, track money clearly, and give the owner a view of shop activity.**
