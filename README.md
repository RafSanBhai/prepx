# ExamPortal BD — MCQ Exam System
### Complete Multi-Role Exam Platform for Bangladesh Bar Council & BJS

---

## 📁 File Structure

```
mcq_exam_system/
├── index.php                   # Smart root redirect
├── login.php                   # Login page
├── register.php                # Student registration
├── logout.php                  # Session destroy
├── exam_view.php               # Live exam interface with countdown timer
├── result.php                  # Instant results with answer review
├── config.php                  # DB connection, helpers, CSRF, sessions
├── db.sql                      # Full database schema (import this first)
├── sample_questions.csv        # Sample CSV for bulk import testing
├── .htaccess                   # Security & performance config (Apache)
│
├── admin/
│   ├── dashboard.php           # Admin home with stats
│   ├── exams.php               # Create/edit/delete exams
│   ├── questions.php           # Per-exam question manager
│   ├── categories.php          # Subject categories (CrPC, Penal Code…)
│   ├── csv_import.php          # Bulk question upload via CSV
│   ├── activation_codes.php    # Generate codes from bKash transaction IDs
│   ├── users.php               # Manage students & teachers; grant access
│   ├── results.php             # All results & analytics
│   └── announcements.php       # Publish notices to student dashboard
│
├── student/
│   ├── dashboard.php           # Student home: scores, performance charts
│   ├── exams.php               # Browse & filter available exams
│   ├── profile.php             # Redeem activation code + edit profile
│   └── history.php             # Full exam attempt history
│
├── includes/
│   ├── header.php              # Shared HTML <head> + flash messages
│   ├── footer.php              # Shared footer + JS include
│   └── admin_sidebar.php       # Admin navigation sidebar
│
└── assets/
    ├── css/main.css            # Complete design system (Navy + Gold theme)
    └── js/main.js              # Sidebar toggle, exam timer, option selector
```

---

## 🚀 Installation Guide

### Step 1 — Upload Files
Upload the entire `mcq_exam_system/` folder to your InfinityFree `htdocs/` (public root).
Your site should be: `https://yourdomain.infinityfreeapp.com/`

### Step 2 — Create MySQL Database
1. Log in to InfinityFree cPanel → **MySQL Databases**
2. Create a new database (e.g., `if0_12345_examportal`)
3. Create a database user with a strong password
4. Add the user to the database with **All Privileges**
5. Go to **phpMyAdmin** and import `db.sql`

### Step 3 — Configure `config.php`
Open `config.php` and update:
```php
define('DB_HOST', 'sql200.infinityfree.com');  // your MySQL host from cPanel
define('DB_NAME', 'if0_12345_examportal');      // your database name
define('DB_USER', 'if0_12345_examuser');        // your db username
define('DB_PASS', 'YourStrongPassword');        // your db password
define('SITE_URL', 'https://yourdomain.com');   // no trailing slash
```

### Step 4 — Login as Super Admin
- URL: `https://yourdomain.com/login.php`
- Email: `admin@examportal.com`
- Password: `password` ← **Change this immediately!**

To change the default admin password, log in and go to profile, or run:
```sql
UPDATE users SET password = '$2y$10$...' WHERE email = 'admin@examportal.com';
```
Generate the hash with PHP: `echo password_hash('YourNewPassword', PASSWORD_BCRYPT);`

---

## 🔑 Subscription Workflow (bKash/Nagad)

1. **Student pays** via bKash/Nagad and sends Transaction ID to admin
2. **Admin goes to** `/admin/activation_codes.php`
3. **Pastes the Transaction ID** → system generates a unique 16-character code
4. **Admin sends the code** to the student (WhatsApp/SMS/email)
5. **Student logs in** → goes to Profile → enters the code
6. **Access is granted** instantly — Full Premium OR specific categories

### Granular Category Access
Admin can generate a code that grants access to **specific categories only** (e.g., only CrPC, not all premium subjects). This allows flexible course-wise pricing.

---

## 📤 CSV Bulk Import

### Format
| Column | Required | Example |
|--------|----------|---------|
| question_text | ✅ | What does CrPC stand for? |
| option_a | ✅ | Code of Criminal Procedure |
| option_b | ✅ | Civil Rights Protection Code |
| option_c | ✅ | Court Record Procedure Code |
| option_d | ✅ | Criminal Rights Protection Code |
| correct_option | ✅ | A |
| explanation | ❌ | CrPC stands for… |
| marks | ❌ | 1 |

- First row can be a header (auto-detected and skipped)
- Save Excel → **File > Save As > CSV (Comma delimited)**
- Use double-quotes for cells containing commas: `"Yes, this applies"`
- Use the included `sample_questions.csv` as a template

---

## 👥 User Roles

| Role | Capabilities |
|------|-------------|
| **Super Admin** | Everything: users, codes, exams, categories, announcements, results |
| **Teacher** | Create exams, manage questions, import CSV, view results |
| **Student (Free)** | Only free/demo exams |
| **Student (Premium)** | All exams (full or category-specific via activation code) |

---

## 🛡 Security Features

- `password_hash()` / `password_verify()` for all passwords
- PDO prepared statements — 100% SQL injection safe
- CSRF tokens on every form
- Session cookie: `httponly`, `samesite=Strict`, optional `secure`
- `.htaccess` blocks direct access to `config.php`, `db.sql`, `includes/`
- No `exec()`, no shell commands — InfinityFree compatible
- No external dependencies — no Composer, no npm

---

## ⏱ Exam Timer

The countdown timer uses **vanilla JS `setInterval`** (no AJAX):
- Timer starts from `duration_minutes × 60` seconds
- Goes red in the last 5 minutes
- **Auto-submits the form** when it reaches zero
- Works even if the student accidentally leaves the page (attempt is saved)
- Resume support: if a student refreshes, remaining time is recalculated from `started_at`

---

## 📊 Negative Marking

Set at the exam level (`negative_marking` field):
- `0` = no negative marking
- `0.25` = subtract 0.25 per wrong answer
- `0.5` = subtract 0.5 per wrong answer
- Skipped questions get 0 (no deduction)
- Final score is floored at 0 (cannot go negative)

---

## 🗄 Database Schema Overview

| Table | Purpose |
|-------|---------|
| `users` | All users (super_admin, teacher, student) |
| `categories` | Subject categories (CrPC, Penal Code, etc.) |
| `exams` | Exam sets with timing and marking rules |
| `questions` | MCQ questions with 4 options per exam |
| `activation_codes` | 16-char codes tied to bKash transaction IDs |
| `user_category_access` | Granular per-category access grants |
| `exam_attempts` | Each student's exam sitting |
| `student_answers` | Per-question answers for each attempt |
| `announcements` | Admin notices shown on student dashboard |

---

## 🌐 InfinityFree Specific Notes

- **No `exec()`** — none used in this system
- **No Composer** — pure PHP, zero dependencies
- **Hits limit** — no AJAX polling; timer is pure client-side JS
- **MySQL 5.6+** compatible — uses `InnoDB`, standard SQL
- **File uploads** — CSV only (lightweight), max 5MB set in `.htaccess`
- **PHP `fgetcsv()`** — used for CSV parsing, built-in and lightweight

---

## 🎨 Design System

- **Theme:** Refined legal/academic aesthetic — Deep Navy + Gold
- **Fonts:** DM Serif Display (headings) + DM Sans (body) via Google Fonts
- **Responsive:** Mobile-first CSS, works on all screen sizes
- **Print:** Result page has print-friendly styles (`@media print`)

---

## 📞 Default Admin Credentials

```
URL:      /login.php
Email:    admin@examportal.com
Password: password
```
**⚠ Change the password immediately after first login.**

---

*Built with pure PHP, MySQL, HTML5, CSS3 — No frameworks, no Composer.*
*Optimised for InfinityFree shared hosting.*
