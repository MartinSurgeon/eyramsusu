# ₵ Eyram Susu — Digital Daily Savings Passbook System

A modern, mobile-first Web Application engineered for daily susu micro-savings collectors, community passbook holders, and office administrators in Ghana. Designed following human-computer interaction (HCI) and UX principles for fast field collection, zero arithmetic errors, and auditable end-of-day cash settlements.

Developed by **Mart IT Services** (WhatsApp: +233 55 786 9989).

---

## 🚀 Key Features

- **📱 31-Space Digital Passbook (`susu_cards`)**:
  - Replaces paper susu cards with automated digital space stamping.
  - Handles advance multi-space payments (e.g., GH₵100 on a GH₵20 plan stamps spaces #1 to #5 in a single transaction).
  - Handles odd amounts seamlessly by storing excess funds in the customer's permanent `change_balance` (float).
  
- **🔒 Daily Cash Handover & Settlement**:
  - Field collectors can tender their exact collected bag liability to the office.
  - Identifies shortages/overages with auditable notes.
  - Real-time liability tracking: "Money with Me" badge updates instantly after each deposit.

- **💰 Transparent Month-End Cashouts**:
  - Automatically calculates the traditional 1st-contribution management fee upon card completion or early payout.
  - Refunds accumulated float balance directly to the customer.

- **🔔 In-App Notification Center**:
  - Instant alerts for cash handovers, payout approvals, customer assignments, and cash shortages.
  - Mobile-responsive sliding drawer with unread counter badges and "Mark all read" controls.

- **🖥️ Collapsible Desktop Sidebar & Thumb-Friendly Mobile Bar**:
  - Desktop: Sleek, collapsible sidebar (`w-64` / `w-20`) with zero-flicker state persistence in `localStorage`.
  - Mobile: Elevated central action button (`Records` for Admin, `Collect` for Collector).

- **⏳ Coming Soon Countdown Landing Page**:
  - Self-contained `coming_soon.html` featuring a 3-hour launch countdown timer, VIP early access registration form, and direct WhatsApp contact.

---

## 🛠️ Technology Stack

- **Backend**: PHP 8.x (Native PDO, Prepared Statements, Bcrypt Hashing)
- **Database**: MySQL 8.x / MariaDB (InnoDB, Foreign Key Constraints, Transactions)
- **Frontend**: HTML5, Vanilla JavaScript, Vanilla CSS + TailwindCSS (v3 CDN)
- **Typography**: Google Fonts (*Outfit* & *Plus Jakarta Sans*)

---

## 📦 Installation & Setup

### 1. Prerequisites
- **XAMPP**, **WampServer**, or **LAMP/LEMP** stack (PHP 8.0+ and MySQL 5.7+).

### 2. Clone Repository
```bash
git clone https://github.com/MartinSurgeon/eyramsusu.git
cd eyramsusu
```

### 3. Database Setup
1. Open phpMyAdmin (`http://localhost/phpmyadmin/`) or MySQL CLI.
2. Import the schema:
   ```bash
   mysql -u root -p < database/schema.sql
   ```
3. Import the official starter seed data:
   ```bash
   mysql -u root -p < database/seed.sql
   ```
   *(Or run `php reset_seed_data.php` directly in your terminal to initialize).*

### 4. Database Configuration
Edit `config/db.php` if your database credentials differ from the defaults:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'eyramsusu');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 5. Access the Platform
- **Application URL**: `http://localhost/eyramsusu/`
- **Coming Soon Page**: `http://localhost/eyramsusu/coming_soon.html`

---

## 👥 Default Accounts (Adaklu Waya Operations)

| Role | Name | Username | Password | Phone |
| :--- | :--- | :--- | :--- | :--- |
| **Admin** | Agbenyenuse Stanley | `Eyram` | `Seyram1` | 0553224837 |
| **Collector** | Kuddy Peggy | `Peggy` | `Peggy123` | 0555495796 |

---

## 🧪 Automated Testing

Run the included verification suite to validate all 25 financial calculations, space-filling mechanics, business fee deductions, and database integrity:

```bash
php test_suite.php
```

---

## 📞 Support & Inquiries

- **Technical Development**: Mart IT Services
- **WhatsApp**: [+233 55 786 9989](https://wa.me/233557869989)
- **Location**: Adaklu Waya, Volta Region, Ghana
