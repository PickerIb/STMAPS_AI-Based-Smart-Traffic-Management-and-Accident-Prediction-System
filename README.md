🚦 Smart Traffic Route Guidance System (STRGS)
A Case of Remera Sector

A comprehensive web-based application that helps drivers select suitable routes using GPS and updated road status information. Traffic police officers update road status and report incidents, while administrators manage users and system settings.

## Problem Statement
Traffic congestion in Remera Sector affects road users by causing delays, fuel wastage, and poor traffic flow. Many drivers enter congested or affected roads because they do not have updated information about road status, incidents, or alternative routes. STRGS uses GPS and road status updates to guide drivers in selecting suitable routes.

## General Objective
To develop a smart traffic route guidance system that helps drivers select suitable routes using GPS and updated road status information.

## Specific Objectives
1. Analyze the challenges and requirements for a smart traffic route guidance system in Remera Sector.
2. Design and implement a smart traffic route guidance system using GPS and road status updates.
3. Evaluate the effectiveness of the developed system in supporting drivers to choose suitable routes.

## System Users
- **Driver / Road User** — Log in, use GPS location, enter destination, view road status, receive route guidance.
- **Traffic Police Officer** — Log in, update road status, report accidents/congestion/blockages, monitor affected roads.
- **Admin** — Log in, manage users, manage system settings.

## Features
### Route Guidance
- GPS-based location and destination input
- Best available route recommendations
- Road status-aware routing

### Road Status Management
- Real-time congestion and blockage updates
- Incident reporting by traffic police
- Affected road monitoring

### Analytics & Reporting
- Interactive dashboard
- Congestion distribution charts
- Route guidance history and trends
- Export reports (CSV/PDF)

### User Management
- Secure authentication
- Role-based access (Driver, Traffic Police Officer, Admin)
- Session security

## Quick Start
### Prerequisites
- PHP 7.4+ with MySQL/MariaDB
- Web server (Apache/Nginx) or XAMPP
- Python 3.8+ (optional, for route analysis engine)

### Installation
1. Clone or copy the project to your web root (e.g. `C:\xampp\htdocs\STMAPS`)
2. Import the database:
   ```bash
   mysql -u root -p < database/smart_traffic_db.sql
   ```
3. Configure `config.php` with your database credentials
4. Access the application at `http://localhost/STMAPS/`

## Usage Guide
1. **Register** as Driver or Traffic Police Officer at `register.php`
2. **Login** at `login.php` with the correct user type
3. **Drivers** — Use Route Guidance and Road Status sections
4. **Traffic Police** — Update road status, report incidents, monitor roads
5. **Admin** — Manage users and system settings (admin accounts are created by administrators)

## Project Structure
```
STMAPS/
├── index.php          # Landing page and API handlers
├── dashboard.php      # Role-based dashboard
├── login.php
├── register.php
├── config.php
├── api.php
├── database/
│   └── smart_traffic_db.sql
└── README.md
```

## Security Features
- Password hashing (`password_hash()`)
- SQL injection prevention (prepared statements)
- Secure session management
- Input validation and sanitization
- CSRF protection

## License
MIT License
