LAUNDRY MANAGEMENT SYSTEM
ប្រព័ន្ធគ្រប់គ្រងហាងបោកគក់
Project Requirements, Database Design & Folder Structure
Document metadata	Value
Version	1.0 — System Design / Before Coding
Date	16 October 2026
Project type	Single-shop PHP + MySQL Mini Project
Backend	Native PHP 8.x, PDO, PHP Sessions
Frontend	HTML5, Tailwind CSS v4, Vanilla JavaScript, Fetch API
Theme	Purple + Black; Light Mode and Dark Mode
Database	laundry_management_db (MySQL)


គោលបំណងឯកសារ: កំណត់ទំហំ Project, Modules, Database, Architecture, Folder Structure, Routes, Theme, Security និងដំណាក់កាលអភិវឌ្ឍន៍ មុនចាប់ផ្តើមសរសេរ Code។

1. Project Overview / ទិដ្ឋភាពទូទៅ
Laundry Management System ជាប្រព័ន្ធគ្រប់គ្រងដំណើរការហាងបោកគក់ ចាប់ពីការចុះឈ្មោះអតិថិជន ទទួលខោអាវ បង្កើត Order តាមដានដំណើរការបោកគក់ ទូទាត់ប្រាក់ រហូតដល់ប្រគល់ខោអាវ និងមើលរបាយការណ៍ចំណូល។
Main objectives / គោលដៅ:
- កាត់បន្ថយការកត់ត្រាដោយដៃ និងកំហុសក្នុងការគណនាតម្លៃ។
- ស្វែងរក Order តាម order_code, Customer Name និង Phone បានលឿន។
- តាមដាន Order Status និង Payment Balance បានត្រឹមត្រូវ។
- គ្រប់គ្រង Data និង Access Control សម្រាប់ Admin / Staff។
- ផ្តល់ Printable Receipt និង Daily/Monthly Revenue Reports។
1.1 Scope — Version 1 (MVP)
In scope: Authentication, Dashboard, Customers, Services, Orders, Status Tracking, Payments, Receipts, Reports. មានហាងមួយ និង Currency KHR (៛) ជាចម្បង។
Out of scope — Version 2: Online Customer Portal, Delivery Tracking, Inventory, SMS/Telegram Notifications, Multi-branch, Refund Workflow, Advanced Discount Campaigns។
Design assumptions: ហាងអាចគិតតម្លៃជា kg និង piece; ការបង់ប្រាក់អាចបង់មុន បង់មួយផ្នែក ឬបង់នៅពេលមកយក។ ទាំងនេះជាអនុសាសន៍សម្រាប់ Schema ហើយអាចកែបានតាម Business Rules ពិត។
2. Confirmed Technology Stack
Layer	Tool / Pattern	Reason
UI	HTML5 + Tailwind CSS v4	Reusable utilities, responsive, maintainable styling
Interactivity	Vanilla JavaScript + Fetch API	AJAX without a frontend framework
Backend	Native PHP 8.x	Practice PHP fundamentals
Architecture	MVC-inspired + Service Layer	Clear separation of concerns
Database	MySQL + InnoDB	Relational data, transactions, foreign keys
DB access	PDO + prepared statements	Secure parameterized SQL
Authentication	PHP Sessions	Server-side login state
CSS build	Node.js/npm + Tailwind CLI	Compile CSS for deployment
Local run	PHP built-in server; XAMPP optional	Development convenience
Source control	Git / GitHub	Version history


Architecture guideline: Request → public/index.php → Router/Middleware → Controller → Service → Model (PDO/MySQL) → View/JSON Response។ Business Logic សម្រាប់ Price Calculation និង Payment ត្រូវនៅ Services/ មិនសរសេររួមក្នុង HTML Views ទេ។
3. Users, Permissions & Modules
3.1 Roles
- Admin / Owner: គ្រប់គ្រង Users, Services, Orders, Payments និង Reports; អាចកែ Settings (បើបានបន្ថែម)។
- Staff / Cashier: គ្រប់គ្រង Customers, Create Orders, Update Status, Record Payments, Print Receipts; មិនអាចគ្រប់គ្រង Accounts ឬ Report កម្រិតម្ចាស់ហាងបានទេ។
- Customer: Version 1 មិនចាំបាច់មាន Login។ Staff បញ្ចូលព័ត៌មាន Customer ជំនួស។
3.2 Module catalog
Code	Module	MVP features	Access
M01	Authentication / Users	Login, Logout, role guard; Admin manage staff accounts	Both; Admin for user management
M02	Dashboard	Order counts, pending/ready, revenue summary	Both (revenue detail Admin)
M03	Customer Management	Add, list, edit, search; soft-disable where applicable	Both
M04	Service Management	Service type, price, kg/piece, active flag	Admin edit; Staff read
M05	Order Management	Create Order, order items, totals, search, detail	Both
M06	Status Tracking	Received → Processing → Ready → Collected; log changes	Both
M07	Payment Management	Cash/KHQR/bank transfer, partial, outstanding balance	Both
M08	Receipt	Detail, print, order code, amounts, balance	Both
M09	Reports	Daily/monthly orders, revenue, unpaid balance	Admin


3.3 Key functional requirements
1. Authentication: Login ដោយ username/email + password; verify hash; regenerate session ID on login; logout invalidates session។
2. Customer: name/phone required; phone as text (រក្សា leading zero); find existing customer before creating a new one។
3. Service: billing_unit = kg or piece; price must be non-negative; mark inactive instead of deleting a service already used in orders។
4. Order: unique order_code; at least one order_item; save service name, unit and price snapshots; calculate total on server-side។
5. Status: allowed states received, processing, ready, collected, cancelled; log each change with user/timestamp; define who can cancel។
6. Payment: record individual positive payments; sum paid amounts; do not allow overpayment; store payment method/reference/time។
7. Receipt: display shop info (static/config in MVP), customer, items, service units, amount paid and balance; printer-friendly layout។
8. Report: daily/monthly successful payment totals, order counts and outstanding balances; make date filters inclusive/exclusive consistently។
4. Business Workflow / System Flow
4.1 Customer → Order → Pickup
Customer arrives
    ↓
Staff searches/creates customer
    ↓
Select service(s), quantity/weight, expected pickup date
    ↓
Server calculates subtotal and total
    ↓
Create order + order_items + initial status log (transaction)
    ↓
Optional deposit/payment + print receipt
    ↓
received → processing → ready
    ↓
Customer pays remaining balance (if any)
    ↓
collected + picked_up_at recorded
4.2 Status rules
Status	Khmer meaning	Typical next step
received	ទទួលខោអាវ	processing or cancelled
processing	កំពុងបោក/អ៊ុត	ready (or cancellation with policy)
ready	រួចរាល់រង់ចាំយក	collected
collected	ប្រគល់រួច	Final / read-only in MVP
cancelled	បានបោះបង់	Final; paid cancellations require manual reconciliation


Important: Order Status មិនដូច Payment Status ទេ។ Payment Status គណនា unpaid, partial, paid ពី payments ជំនួសការរក្សាទុក status ដដែលច្រើនកន្លែង។
4.3 Monetary calculation
line_total      = quantity × unit_price
subtotal        = SUM(order_items.line_total)
total_amount    = subtotal − discount_amount
paid_amount     = SUM(payments.amount)
remaining       = total_amount − paid_amount
Store amounts as DECIMAL, never floating-point. Version 1 assumes discount_amount = 0 unless the shop enables manual discounts. Server must reject quantity <= 0, negative price and overpayment.
5. MySQL Database Design — Seven Tables
Database: laundry_management_db
Engine/encoding: InnoDB, utf8mb4
Money: DECIMAL(12,2) (KHR in MVP)
IDs: BIGINT UNSIGNED AUTO_INCREMENT
5.1 users — Login accounts
Field	Type	Notes
id	BIGINT UNSIGNED	PK, auto increment
full_name	VARCHAR(120)	Required
username	VARCHAR(60)	Unique, required
email	VARCHAR(150)	Unique, optional
password_hash	VARCHAR(255)	Required, hashed
role	ENUM('admin','staff')	Required
is_active	BOOLEAN	Default true
created_at, updated_at	DATETIME	Timestamp fields


5.2 customers — Customers
Field	Type	Notes
id	BIGINT UNSIGNED	PK
name	VARCHAR(120)	Required
phone	VARCHAR(20)	Required; index for search, not necessarily unique
address, notes	TEXT	Nullable
is_active	BOOLEAN	Default true
created_at, updated_at	DATETIME	Timestamp fields


5.3 services — Laundry service pricing
Field	Type	Notes
id	BIGINT UNSIGNED	PK
service_code	VARCHAR(30)	Unique
name	VARCHAR(120)	Required
billing_unit	ENUM('kg','piece')	Required
price_per_unit	DECIMAL(12,2)	Required, >= 0
description	TEXT	Nullable
is_active	BOOLEAN	Default true
created_at, updated_at	DATETIME	Timestamp fields


5.4 orders — Order header
Field	Type	Notes
id	BIGINT UNSIGNED	PK
order_code	VARCHAR(30)	Unique, required
customer_id	BIGINT UNSIGNED	FK → customers.id
created_by	BIGINT UNSIGNED	FK → users.id
received_at	DATETIME	Required
expected_pickup_at, picked_up_at	DATETIME	Nullable
status	ENUM	received/processing/ready/collected/cancelled
subtotal	DECIMAL(12,2)	Default 0
discount_amount	DECIMAL(12,2)	Default 0
total_amount	DECIMAL(12,2)	Default 0
notes	TEXT	Nullable
created_at, updated_at	DATETIME	Timestamp fields


5.5 order_items — Items and locked historical prices
Field	Type	Notes
id	BIGINT UNSIGNED	PK
order_id	BIGINT UNSIGNED	FK → orders.id
service_id	BIGINT UNSIGNED	FK → services.id
service_name	VARCHAR(120)	Snapshot
billing_unit	ENUM('kg','piece')	Snapshot
quantity	DECIMAL(10,2)	> 0; integer for piece
unit_price	DECIMAL(12,2)	Snapshot from services
line_total	DECIMAL(12,2)	quantity × unit_price
description	VARCHAR(255)	Nullable


5.6 payments — Partial or full payments
Field	Type	Notes
id	BIGINT UNSIGNED	PK
order_id	BIGINT UNSIGNED	FK → orders.id
received_by	BIGINT UNSIGNED	FK → users.id
amount	DECIMAL(12,2)	> 0
payment_method	ENUM('cash','khqr','bank_transfer','other')	Required
reference_no	VARCHAR(100)	Nullable
paid_at	DATETIME	Required
notes	TEXT	Nullable
created_at	DATETIME	Timestamp


5.7 order_status_logs — History
Field	Type	Notes
id	BIGINT UNSIGNED	PK
order_id	BIGINT UNSIGNED	FK → orders.id
changed_by	BIGINT UNSIGNED	FK → users.id
old_status	ENUM	Nullable for initial creation
new_status	ENUM	Required
notes	TEXT	Nullable
changed_at	DATETIME	Required


5.8 Relationships and indexes
Relationship	Cardinality
customers → orders	1:N
users → orders (created_by)	1:N
orders → order_items	1:N
services → order_items	1:N
orders → payments	1:N
users → payments (received_by)	1:N
orders → order_status_logs	1:N
users → order_status_logs (changed_by)	1:N


Essential indexes: unique users.username, orders.order_code, services.service_code; indexes on customers.phone, orders(customer_id, received_at), orders(status, expected_pickup_at), order_items.order_id, payments(order_id, paid_at), order_status_logs(order_id, changed_at). Foreign keys should prevent deleting transactional parents by mistake.
Transactions: Create Order + Items + Log as one transaction; payment creation must lock/check Order and outstanding balance inside a transaction to prevent concurrent overpayment.
6. Recommended Project Folder Structure
laundry-management/
├── app/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── UserController.php
│   │   ├── CustomerController.php
│   │   ├── ServiceController.php
│   │   ├── OrderController.php
│   │   ├── PaymentController.php
│   │   └── ReportController.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Customer.php
│   │   ├── Service.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   ├── Payment.php
│   │   └── OrderStatusLog.php
│   ├── Services/
│   │   ├── OrderService.php
│   │   ├── PaymentService.php
│   │   └── ReportService.php
│   ├── Middleware/
│   │   ├── RequireAuth.php
│   │   └── RequireRole.php
│   ├── Core/
│   │   ├── Router.php
│   │   ├── Database.php
│   │   ├── View.php
│   │   ├── Auth.php
│   │   ├── Csrf.php
│   │   └── Validator.php
│   └── Helpers/
│       └── functions.php
├── bootstrap/
│   └── app.php
├── config/
│   ├── app.php
│   ├── database.php
│   └── local.example.php
├── database/
│   ├── migrations/
│   │   ├── 001_create_users.sql
│   │   ├── 002_create_customers.sql
│   │   ├── 003_create_services.sql
│   │   ├── 004_create_orders.sql
│   │   ├── 005_create_order_items.sql
│   │   ├── 006_create_payments.sql
│   │   └── 007_create_order_status_logs.sql
│   └── seeds/
│       └── demo_data.sql
├── public/                       # Only publicly served directory
│   ├── index.php                  # Front Controller
│   ├── router.php                 # PHP local-server router
│   ├── .htaccess                  # Apache rewrite rules
│   └── assets/
│       ├── css/
│       │   └── app.css            # GENERATED by Tailwind CLI
│       ├── js/
│       │   ├── app.js
│       │   ├── theme.js
│       │   └── orders.js
│       └── images/
│           └── logo.svg
├── resources/
│   ├── css/
│   │   └── app.css                # EDIT THIS Tailwind source
│   └── views/
│       ├── layouts/
│       │   ├── app.php
│       │   └── guest.php
│       ├── components/
│       │   ├── sidebar.php
│       │   ├── topbar.php
│       │   └── flash.php
│       ├── auth/login.php
│       ├── dashboard/index.php
│       ├── users/{index,create,edit}.php
│       ├── customers/{index,create,edit}.php
│       ├── services/{index,create,edit}.php
│       ├── orders/{index,create,show,edit}.php
│       ├── payments/index.php
│       ├── reports/index.php
│       └── errors/{403,404,500}.php
├── routes/
│   └── web.php
├── storage/
│   └── logs/.gitkeep
├── tests/
│   └── manual-test-cases.md
├── package.json
├── .gitignore
└── README.md
NOTE: Curly brace notation like {index,create,edit}.php above is a compact description of three physical files, not a literal filename. Example: customers/index.php, customers/create.php, customers/edit.php.
6.1 Folder responsibilities
Location	Responsibility
app/Controllers	Receive request, authorize, validate, call services, return View/JSON
app/Models	SQL queries and persistence using PDO
app/Services	Order totals, transactions, payment rules and reporting logic
app/Middleware	Authentication and role checks per route
app/Core	Router, PDO connection, CSRF, view renderer, common validation
bootstrap	Session startup, autoload, config loading, router initialization
config	Safe app/database configuration; local.php is private and gitignored
database/migrations	Ordered SQL schema files (run once, track history manually in MVP)
public	Only web-accessible entry point and static compiled assets
resources/css	Tailwind source stylesheet; not served directly
resources/views	Reusable PHP templates and page markup
routes/web.php	Route definitions + guards
storage/logs	App errors; never expose as public URLs
tests	Manual test scenarios for project demonstration


6.2 File naming convention
- PHP class files: PascalCase.php e.g. OrderController.php.
- PHP views: lowercase index.php, create.php, edit.php, show.php.
- SQL columns/tables: snake_case e.g. order_items, created_by.
- URLs: plural, lowercase e.g. /orders, /services.
- JS files: lowercase words/hyphens; Tailwind utilities in PHP markup use complete class strings.
- One responsibility per class; no SQL in Views; never trust $_POST values without validation.
7. Routing Map (HTML / Form POST / AJAX)
Method	Path	Purpose	Roles
GET	/login	Login page	Guest
POST	/login	Sign in	Guest
POST	/logout	Sign out	Authenticated
GET	/dashboard	Dashboard	Admin, Staff
GET	/customers	Customer list/search	Admin, Staff
GET / POST	/customers/create	Show form / save customer	Admin, Staff
GET / POST	/customers/{id}/edit	Show form / update customer	Admin, Staff
GET	/services	Services list	Admin, Staff
GET / POST	/services/create	Add service	Admin
GET / POST	/services/{id}/edit	Update service	Admin
GET	/orders	Order list/search	Admin, Staff
GET / POST	/orders/create	Create order	Admin, Staff
GET	/orders/{id}	Order detail / receipt	Admin, Staff
POST	/orders/{id}/status	Update status	Admin, Staff
GET / POST	/orders/{id}/edit	Edit allowed order	Admin, Staff
POST	/orders/{id}/payments	Add payment	Admin, Staff
GET	/reports	Reports / filters	Admin
GET	/users	Manage staff accounts	Admin


Route note: PHP HTML forms support GET/POST directly. Use POST for mutations and a CSRF token in every state-changing request. AJAX can return JSON from the same routes when needed; maintain clear content-type responses.
8. UI Design System — Purple + Black
8.1 Color palette
Token	Hex	Use
Primary Purple	#7C3AED	Main button, selected nav, focus rings
Primary Hover	#6D28D9	Hover / active actions
Soft Purple	#A78BFA	Highlights / badge accents
Dark Background	#0C0C12	Dark Mode application background
Dark Card	#1C1B27	Dark Mode cards / panels
White	#FFFFFF	Light cards / primary light text
Light Background	#F8F7FC	Light Mode page background
Primary Text Light	#18181B	Light Mode body text
Muted Gray	#A1A1AA	Secondary text (check contrast)


Design components: Navbar, collapsible Sidebar, Stat Cards, Search/Filters, Forms, Responsive Tables, Modal Confirmations, Badge status, Toast Alerts, Printable Receipt.
Accessibility: Important statuses must not depend on color alone; always display text labels/icons. Ensure contrast and keyboard focus states.
8.2 Tailwind v4 — installation for Native PHP
Run from the root laundry-management/:
npm init -y
npm install -D tailwindcss @tailwindcss/cli
Suggested package.json scripts:
{
  "scripts": {
    "dev:css": "tailwindcss -i ./resources/css/app.css -o ./public/assets/css/app.css --watch",
    "build:css": "tailwindcss -i ./resources/css/app.css -o ./public/assets/css/app.css --minify"
  },
  "devDependencies": {
    "@tailwindcss/cli": "^4.0.0",
    "tailwindcss": "^4.0.0"
  }
}
Dependency note: The ^4.0.0 versions are a documented compatible major-version example. Let npm install write the current resolved compatible versions and commit package-lock.json; no manual version pinning is required.

File resources/css/app.css:
@import "tailwindcss";
@custom-variant dark (&:where(.dark, .dark *));
@source "../../resources/views";
@source "../../app";
@source "../../public/assets/js";

@theme {
  --color-primary: #7C3AED;
  --color-primary-hover: #6D28D9;
  --color-dark-base: #0C0C12;
  --color-dark-card: #1C1B27;
}
PHP Layout <head> should include the generated CSS:
<link rel="stylesheet" href="/assets/css/app.css">
Example button and card:
<button class="rounded-lg bg-primary px-4 py-2 text-white hover:bg-primary-hover">
  New Order
</button>
<div class="rounded-xl bg-white p-5 text-zinc-900 dark:bg-dark-card dark:text-white">
  Order Summary
</div>
8.3 Theme toggle — Light / Dark
Before rendering the page, set .dark on the root <html> element. Save preference in localStorage via public/assets/js/theme.js.
const savedTheme = localStorage.getItem('theme');
const isDark = savedTheme === 'dark' ||
  (!savedTheme && matchMedia('(prefers-color-scheme: dark)').matches);
document.documentElement.classList.toggle('dark', isDark);

function setTheme(nextTheme) {
  localStorage.setItem('theme', nextTheme);
  document.documentElement.classList.toggle('dark', nextTheme === 'dark');
}
For best visual result, execute the initial theme selection before stylesheet rendering (or place a small equivalent script early in <head>) to reduce a light-theme flash. The theme preference does not need a database column in MVP.
8.4 Important Tailwind rule
Do not dynamically concatenate class fragments (e.g. bg-${color}-500 in PHP/JS); Tailwind scans source files for complete class names. Use mapping of fully written class strings instead.
9. Run & Development Setup
1. Install PHP 8.x, MySQL and Node.js/npm. Create database laundry_management_db with utf8mb4.
2. Create files/folders matching section 6. Copy config/local.example.php → config/local.php and fill local credentials. local.php must be gitignored. This is a PHP array config file, NOT .env (Native PHP does not parse .env automatically).
3. Build the seven SQL migration files (or import a schema SQL file). Create an admin account with password hashed by password_hash(); do not store plain passwords in seeds.
4. Install Tailwind CLI using npm install. Start CSS watcher using npm run dev:css in one terminal.
5. Start the PHP development server from project root in another terminal:
php -S localhost:8000 -t public public/router.php
6. Open http://localhost:8000/login, sign in and test the Order Workflow. Use Apache/XAMPP virtual-host DocumentRoot pointing to /public if running with Apache.
7. For deploy: npm run build:css; publish public/ as web root and ensure application folders are inaccessible from the web. PHP built-in server is for local development only.
Development router contract: public/router.php should return false for existing files inside public/ (so CSS/JS load normally) and otherwise require public/index.php.
10. Security, Quality & Validation
Check	Required behavior
SQL safety	All queries via PDO prepared statements
Output encoding	Use htmlspecialchars(..., ENT_QUOTES, 'UTF-8') in Views
CSRF	CSRF token for login and all POST mutations (including Fetch API)
Sessions	HttpOnly cookie, SameSite, Secure on HTTPS, rotate session ID at login
Authorization	Role checks in Middleware; never rely on hidden UI buttons
Input validation	Required fields, valid units, quantity rules, numeric prices, expected dates
Data integrity	DB foreign keys + transactions for order/payment writes
Status integrity	Enforce allowed transitions; append status log every time
Error handling	Generic error to users; detailed logs only under storage/logs
Production	Disable debug display; never serve app/, config/ or database/
Repo hygiene	Ignore config/local.php, logs, node_modules/; commit package-lock.json


10.1 Manual test checklist
- [ ] Admin / Staff log in and out correctly; unauthenticated routes redirect to Login.
- [ ] Staff cannot open Admin-only route by typing URL directly.
- [ ] Customer CRUD and search by phone works, including phone starting with 0.
- [ ] Service kg + piece price calculations return correct line_total.
- [ ] Create Order writes header + items + initial status log atomically.
- [ ] Price in old receipt does not change when service price is updated.
- [ ] Partial payment updates remaining balance; overpayment is rejected.
- [ ] Invalid status transition is rejected; valid changes are logged.
- [ ] Print receipt displays customer/order/items/payment correctly.
- [ ] Reports match the sum of recorded payments for selected date range.
- [ ] Light/Dark toggle persists after refresh.
- [ ] Mobile screen shows usable responsive sidebar, forms and order table.
11. Development Plan & Deliverables
Phase	Build	Definition of done
1	Foundation	Folder structure, config, router, PDO, CSS watcher
2	Auth + Dashboard	Admin/staff login, session, role middleware, base layout
3	Master Data	Customer + Service CRUD and search
4	Orders + Status	Create/show/edit allowed orders; items; logs; totals
5	Payments + Receipt	Partial/full payment, balance validation, print layout
6	Reports + QA	Revenue filters, mobile/dark QA, authorization/security tests


Suggested first implementation: Phase 1 → public/index.php, public/router.php, bootstrap/app.php, app/Core/Router.php, app/Core/Database.php, resources/css/app.css, resources/views/layouts/app.php, routes/web.php.
12. Open Decisions (before final SQL migration)
- Confirm whether orders should allow both kg and piece in one order (recommended: yes).
- Confirm whether partial/deposit payments are enabled (recommended: yes).
- Confirm whether cancelled orders with payments need refunds in MVP (recommended: keep paid cancellations restricted until refund workflow exists).
- Confirm shop's pickup policy: block collected until paid in full? (recommended: yes).
- Confirm receipt currency and language (current default: Khmer Riel / Khmer + English labels).
13. Reference Documentation
- Tailwind CSS v4 CLI: https://tailwindcss.com/docs/installation/tailwind-cli
- Tailwind CSS Dark Mode: https://tailwindcss.com/docs/dark-mode
- Tailwind class detection / @source: https://tailwindcss.com/docs/detecting-classes-in-source-files
- PHP PDO: https://www.php.net/manual/en/book.pdo.php
- PHP password hashing: https://www.php.net/manual/en/function.password-hash.php
Document status: Architecture & schema proposal approved as baseline except Open Decisions. No source-code project has been implemented by this documentation file.