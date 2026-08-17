# ClosetIQ — Step-by-Step Development Guide

## Project Overview
ClosetIQ is a web-based Smart Wardrobe Inventory and Outfit Planner built with **HTML5, CSS3, JavaScript, PHP, and MySQL**, developed and tested using **XAMPP**.

---

## Step 1: Environment Setup

### 1.1 Install XAMPP
1. Download XAMPP from https://www.apachefriends.org/
2. Install with default settings (Apache, MySQL, PHP, phpMyAdmin).
3. Launch the XAMPP Control Panel.

### 1.2 Create Project Folder
Create the folder `C:\xampp\htdocs\ClosetIQ\` (or `C:\xampp\htdocs\ClosetIQ2\` if you prefer).

### 1.3 Start Services
1. Start **Apache** from the XAMPP Control Panel.
2. Start **MySQL** from the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin in your browser.

### 1.4 Create Database
1. Click **New** in phpMyAdmin.
2. Database name: `closetiq_db`
3. Collation: `utf8_general_ci`
4. Click **Create**.

### 1.5 Import Schema
1. Select the `closetiq_db` database.
2. Go to the **Import** tab.
3. Choose `database/closetiq.sql` (created in Step 2 below).
4. Click **Go**.

---

## Step 2: Database Design (MySQL)

Create the file `database/closetiq.sql` with the following schema:

```sql
CREATE DATABASE IF NOT EXISTS closetiq_db;
USE closetiq_db;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE clothing_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    category ENUM('tops','bottoms','footwear','accessories') NOT NULL,
    color VARCHAR(50) NOT NULL,
    size VARCHAR(20),
    season ENUM('spring','summer','autumn','winter','all') DEFAULT 'all',
    image_path VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE outfit_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    outfit_details JSON NOT NULL,
    weather_data JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Step 3: Project Structure

```
C:\xampp\htdocs\ClosetIQ\
├── config/
│   └── database.php
├── includes/
│   ├── header.php
│   ├── footer.php
│   └── auth.php
├── css/
│   └── style.css
├── js/
│   └── main.js
├── uploads/
│   └── [user_id]/
├── api/
│   └── weather.php
├── database/
│   └── closetiq.sql
├── index.php
├── dashboard.php
├── wardrobe.php
├── outfits.php
├── history.php
└── logout.php
```

---

## Step 4: Core Backend (PHP)

### 4.1 Database Connection (`config/database.php`)
- Create a PDO instance.
- Set error mode to `PDO::ERRMODE_EXCEPTION`.
- Set charset to `utf8mb4`.
- Use `localhost` as host, `root` as username, empty password (XAMPP default).

### 4.2 Authentication (`includes/auth.php`)
- Start session with `session_start()`.
- Define `require_login()`: redirect to `index.php` if user is not authenticated.
- Define `login($username, $password)`: fetch user by username, verify password with `password_verify()`.
- Define `register($username, $email, $password)`: hash password with `password_hash()`, insert user.
- Define `logout()`: destroy session and redirect.

### 4.3 Weather API (`api/weather.php`)
- Accept `city` parameter via GET.
- Use `file_get_contents()` or cURL to call OpenWeatherMap API.
- API endpoint: `https://api.openweathermap.org/data/2.5/weather?q={city}&appid={API_KEY}&units=metric`
- Return JSON response with temperature, condition, humidity.
- **Security:** Never expose the API key in client-side code. This file acts as a server-side proxy.

### 4.4 Page Logic
Each PHP page should:
- Include `config/database.php` and `includes/auth.php`.
- Call `require_login()` at the top (except `index.php`).
- Handle POST requests for forms (register, login, add/edit/delete clothing).
- Use prepared statements for all database queries.
- Redirect after POST to prevent form resubmission.

---

## Step 5: Core Frontend (HTML/CSS/JS)

### 5.1 HTML Structure
Use semantic HTML5 elements (`<header>`, `<nav>`, `<main>`, `<section>`, `<footer>`).
Include `includes/header.php` and `includes/footer.php` on every page for consistent navigation.

### 5.2 CSS Styling (`css/style.css`)
- Use a modern, clean color palette (e.g., #2c3e50 for headers, #ecf0f1 for backgrounds).
- Use **Flexbox and CSS Grid** for layout.
- Ensure **responsive design**: use media queries for mobile (<768px).
- Style forms, buttons, tables, and cards consistently.
- Add hover effects and transitions for interactivity.

### 5.3 JavaScript Interactivity (`js/main.js`)
- **Form Validation:** Validate registration/login forms before submission (non-empty, email format, password length).
- **Dynamic UI:** Use `fetch()` for AJAX calls (e.g., weather data without page reload).
- **Image Preview:** Show preview of selected image before upload.
- **Category Filtering:** Filter clothing items by category on the wardrobe page.
- **Outfit Generation:** Trigger outfit suggestion generation and display results dynamically.

---

## Step 6: Coding Best Practices

### 6.1 Security
- **SQL Injection Prevention:** Always use prepared statements with bound parameters (`PDO::prepare()` + `execute()`).
- **XSS Prevention:** Escape output with `htmlspecialchars()` when displaying user input.
- **CSRF Protection:** Include a hidden token in forms and validate it server-side.
- **Password Security:** Use `password_hash()` and `password_verify()`. Never store plain text passwords.
- **File Upload Security:** 
  - Check `mime_content_type()` to ensure only images are uploaded.
  - Generate a unique filename (e.g., `uniqid() . '.' . $ext`) to prevent overwriting.
  - Store uploads outside the web root or in a dedicated `uploads/` folder with restricted access.

### 6.2 Code Organization
- Separate logic (PHP), presentation (HTML), and behavior (JS).
- Use meaningful variable and function names (e.g., `$stmt`, `getUserById()`, `renderHeader()`).
- Add block comments at the top of each file describing its purpose.
- Add inline comments for complex logic (e.g., outfit matching rules).
- Follow consistent indentation (4 spaces for PHP, 2 spaces for CSS/JS).

### 6.3 Error Handling
- Use `try-catch` blocks for database operations.
- Display user-friendly error messages (e.g., "Invalid email or password") rather than raw database errors.
- Log detailed errors to a file (`error_log()`) for debugging, but do not display them to users.

---

## Step 7: Testing

### 7.1 Functional Testing
- **Authentication:** Test registration, login, and logout with valid and invalid inputs.
- **Wardrobe CRUD:** Test adding, editing, viewing, and deleting clothing items.
- **Image Upload:** Test uploading valid images and reject invalid file types.
- **Outfit Suggestions:** Verify that suggestions are generated based on categories and weather data.
- **History:** Verify that generated outfits are saved and displayed in history.

### 7.2 Browser Testing
- Test on Chrome, Firefox, and Edge.
- Test responsive layout on mobile screen sizes (use browser DevTools device emulation).

### 7.3 Input Validation Testing
- Submit empty forms.
- Submit forms with invalid data (e.g., short password, invalid email).
- Verify that validation errors are displayed correctly.

---

## Step 8: Documentation

### 8.1 Code Documentation
- Comment the database schema in `closetiq.sql`.
- Add a `README.md` with setup instructions, features list, and technology stack.

### 8.2 Project Documentation
- Take screenshots of all major pages for the final report.
- Document the database ERD and system architecture.
- Prepare a user guide explaining how to use each feature.

---

## Quick Reference: Key Files and Their Roles

| File | Role |
|------|------|
| `config/database.php` | PDO database connection |
| `includes/auth.php` | Session management, login/register/logout functions |
| `includes/header.php` | Common header with navigation |
| `includes/footer.php` | Common footer |
| `api/weather.php` | Server-side weather API proxy |
| `index.php` | Landing page / login / register |
| `dashboard.php` | User dashboard with stats and weather |
| `wardrobe.php` | Clothing inventory CRUD |
| `outfits.php` | Outfit suggestion engine |
| `history.php` | Past outfit logs |
| `css/style.css` | Responsive styling |
| `js/main.js` | Client-side validation and interactivity |
| `database/closetiq.sql` | MySQL schema |

---

## Submission Checklist

- [ ] All template sections populated in the Word document.
- [ ] `closetiq.sql` imported and database created.
- [ ] Application runs on http://localhost/ClosetIQ/ (or `ClosetIQ2`).
- [ ] All core features implemented and tested.
- [ ] Input validation and error handling present.
- [ ] Code is organized and commented.
- [ ] Final report includes screenshots and documentation.
