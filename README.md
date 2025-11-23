# GUVI Internship User Authentication System

**Live Demo:** [http://13.53.176.199](http://13.53.176.199)

A full-featured Login and Registration web application built for the GUVI Internship. It implements the core workflow—**Register → Login → Profile**—using modern, secure practices and the exact tech stack as defined by the GUVI Internship requirements.

---

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Workflow](#project-workflow)
- [Installation](#installation)
- [Configuration](#configuration)
  - [MySQL Setup](#mysql-setup)
  - [Redis Setup](#redis-setup)
  - [MongoDB Setup](#mongodb-setup)
- [Starting the Application](#starting-the-application)
- [Deployment](#deployment)
- [License](#license)

---

## Features

- **User Registration:**  
  Seamless registration via jQuery AJAX—no form submissions.
- **Login Authentication:**  
  Secure login workflow using AJAX and MySQL prepared statements.
- **Profile Management:**  
  View and update profile data; extended metadata stored in MongoDB.
- **Sessions:**  
  - Backend session data: **Redis**
  - Frontend login state: **Browser localStorage** (not PHP sessions)
- **Responsive UI:**  
  Bootstrap-powered for all device sizes.
- **Security Practices:**  
  Strict separation of concerns; all credentials and profile data securely handled.
- **Code Structure:**  
  HTML, CSS, JS, and PHP are kept in separate files, following best practices.

---

## Tech Stack

**Frontend:**  
HTML, CSS, Bootstrap, JavaScript, jQuery AJAX

**Backend:**  
PHP

**Databases:**  
MySQL (user credentials, profile data)  
Redis (backend sessions)  
MongoDB (extended profile metadata)

---

## Project Workflow

1. **Registration**  
   - User enters basic info; saved to MySQL (via prepared statements).

2. **Login**  
   - Authentication performed with AJAX; session token stored in browser `localStorage`.
   - Session data stored in Redis on backend.

3. **Profile Page**  
   - AJAX loads user info for display and edit.
   - Updates made via AJAX; changes reflected in MySQL and MongoDB.

---

## Installation

### Prerequisites

- PHP >= 7.4
- MySQL
- Redis
- MongoDB
- A web server (e.g., Apache, nginx), or PHP's built-in server

### 1. Clone the Repository

```bash
git clone https://github.com/AdhishMagic/Log-in-Register.git
cd Log-in-Register
```

### 2. Install Dependencies

Most dependencies are native; ensure you have PHP extensions for MySQL, Redis, and MongoDB.

- **PHP MySQL Extension:** `php-mysql`
- **PHP Redis Extension:** `php-redis`
- **PHP MongoDB Extension:** `php-mongodb`

---

## Configuration

### MySQL Setup

1. Create a new MySQL database:

   ```sql
   CREATE DATABASE guvi_auth;
   ```

2. Create user table:

   ```sql
   CREATE TABLE users (
     id INT PRIMARY KEY AUTO_INCREMENT,
     username VARCHAR(255) UNIQUE NOT NULL,
     password VARCHAR(255) NOT NULL,
     name VARCHAR(255),
     age INT,
     dob DATE,
     contact VARCHAR(255)
   );
   ```

3. Update your database credentials in `/backend/db_config.php`.

### Redis Setup

- Install and start Redis:

   ```bash
   sudo apt-get install redis-server
   sudo systemctl enable redis-server
   sudo systemctl start redis-server
   ```

- Update connection details in `/backend/redis_config.php`.

### MongoDB Setup

- Install MongoDB and start the service:

   ```bash
   sudo apt-get install -y mongodb
   sudo systemctl start mongodb
   ```

- Extended profile metadata will be stored for each user. Update connection code in your PHP files if needed.

---

## Starting the Application

### Using PHP Built-in Server (for development):

```bash
php -S localhost:8000
```

- Open `http://localhost:8000/views/register.html` or `login.html` in your browser.

### Using Apache/nginx

- Point your server root to the `/views` directory for static HTML and configure to serve `/backend` PHP endpoints.

---

## Deployment

You can deploy this project on cloud platforms such as AWS EC2.

1. **Provision an EC2 instance** (Ubuntu recommended).
2. Install all required packages: PHP, MySQL, Redis, MongoDB, Apache/nginx.
3. Clone the repository and configure `/backend/db_config.php` and `/backend/redis_config.php` with production settings.
4. Ensure ports for Redis (6379) and MongoDB (27017) are open for local access.
5. Set up Apache/nginx for public access. Point document root to `/views`.

---

## License

*License information to be added.*

---

**Maintainer:** [AdhishMagic](https://github.com/AdhishMagic)

For questions, open an [issue](https://github.com/AdhishMagic/Log-in-Register/issues) or reach out via the repository.
