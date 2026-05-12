# 🕵️‍♂️ Campus Lost & Found System - Project Overview for Claude

This document provides a comprehensive overview of the **Campus Lost & Found** project, a web-based platform for university communities to manage lost and found items.

## 🚀 Technical Stack
- **Backend:** PHP 8.x (using PDO for database interactions)
- **Database:** MySQL (InnoDB engine)
- **Frontend:** HTML5, CSS3 (Modular CSS with BEM methodology), Vanilla JavaScript (ES6+)
- **Communication:** AJAX via Fetch API (SPA-lite approach)
- **Security:** SHA-256 password hashing, XSS protection, SQL Injection prevention (Prepared Statements), Session Fixation protection.

---

## 📂 Project Structure
```text
Campus_Lost_And_Found/
├── 01_index.html           # Main landing page (Listing & Filtering)
├── 02_details.html         # Item detail view
├── 03_create-ad.html       # Item creation form (Requires Auth)
├── 04_auth.html            # Unified Login/Register page
├── 05_dashboard.html       # User control panel
├── 06_messages.html        # Private messaging interface
├── 07_admin.html           # Admin management interface
├── css/                    # Modular CSS
│   ├── main.css            # Entry point for CSS @imports
│   ├── base/               # Reset and base styles
│   ├── components/         # UI components (navbar, cards, buttons)
│   └── utils/              # Variables and mixins
├── php/                    # Backend Logic
│   ├── db_connect.php      # PDO Connection
│   ├── check_session.php   # Auth verification
│   ├── login_process.php   # Login logic (SHA-256)
│   ├── register_process.php# Registration (Transaction-based Supertype/Subtype)
│   ├── get_listings.php    # Fetch items for index
│   ├── upload_logic.php    # Item creation & file upload security
│   └── ...                 # Other AJAX endpoints
├── database/               # SQL setup scripts
└── uploads/                # Directory for item photos
```

---

## 📊 Database Schema (ER Summary)
The system uses a **Supertype-Subtype** design for Users.

1.  **users (Supertype):** `User_ID`, `Full_Name`, `Email`, `Password` (SHA-256), `User_Type` (Enum: student, staff).
2.  **students (Subtype):** `User_ID` (FK), `Student_Number`.
3.  **staff (Subtype):** `User_ID` (FK), `Staff_Number`.
4.  **items:** `Item_ID`, `User_ID` (FK), `Title`, `Description`, `Category_ID` (FK), `Location_ID` (FK), `Status_ID` (FK: 1=Lost, 2=Found).
5.  **item_photos:** `Photo_ID`, `Item_ID` (FK), `Photo_Path`.
6.  **messages:** `Message_ID`, `Sender_ID` (FK), `Receiver_ID` (FK), `Item_ID` (FK), `Content`, `Is_Read`.
7.  **categories / locations / statuses:** Lookup tables for dropdowns and labels.

---

## 💡 Key Implementation Details

### 1. Unified Authentication
The registration uses **PDO Transactions**. When a user registers, it first inserts into `users`, retrieves the `lastInsertId`, and then inserts into either `students` or `staff` based on the selected type. This ensures data integrity.

### 2. Secure File Uploads
In `upload_logic.php`, files are verified using `finfo` for MIME-type (not just extension), renamed with `uniqid()` to prevent path traversal, and restricted by size (5MB).

### 3. AJAX-Powered Frontend
Most pages use the **Fetch API** to interact with PHP scripts.
- **Example Flow:** `01_index.html` loads -> Calls `php/get_listings.php` -> Receives JSON -> JS maps data to HTML cards.
- This prevents full page reloads and provides a smoother UX.

### 4. Security Patterns
- **Passwords:** `hash('sha256', $password)`
- **XSS:** Data is escaped in JS using `escapeHtml()` and in PHP using `htmlspecialchars()`.
- **SQLi:** All queries use PDO Prepared Statements.
- **Sessions:** `session_regenerate_id(true)` is called upon login to prevent session fixation.

---

## 🛠 Admin Features
`07_admin.html` (interacts with `php/get_admin_listings.php`) allows authorized staff to manage all listings, update statuses, or delete items.

## 📝 Current Goal
[USER: Add your specific instructions here for Claude]
