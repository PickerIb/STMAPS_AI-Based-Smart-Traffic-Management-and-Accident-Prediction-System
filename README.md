# AI-Based Smart Traffic Management and Accident Prediction System

A web application that uses AI (baseline rule-based + optional Python engine) to optimize traffic flow and predict accident risk. The system analyzes traffic readings (vehicle count, speed, density) and environmental factors to produce congestion and accident-risk insights.

## 🌟 Features

### 🎯 AI Prediction Engine
- **Dual Engine Architecture**: PHP fallback engine with optional Python engine
- **Real-time Predictions**: Fast processing with progress indicators
- **Congestion Prediction**: low / moderate / high / severe
- **Accident Probability**: 0.0 - 1.0 with confidence score
- **Actionable Recommendations**: Alerts + suggested mitigation steps

### 📊 Analytics & Reporting
- **Interactive Dashboard**: Real-time analytics and visualizations
- **Performance Distribution Charts**: Visual representation of student performance
- **Prediction Trends**: Historical analysis of predictions over time
- **Class Distribution**: Breakdown by grade levels and classes
- **Recent Activity Feed**: Live updates of system activities
- **Export Functionality**: Download reports as PDF or CSV

### 📈 Data Management
- **CSV Data Upload**: Bulk import traffic readings with flexible column mapping
- **Sample Data Templates**: CSV template included in `data/upload_template_students.csv` (traffic readings)
- **Road Segments / Readings / Accidents**: Traffic-focused database schema
- **Audit Logging**: Activity tracking and logging

### 🔐 User Management
- **Secure Authentication**: Password-based login system
- **User Settings**: Profile management and password updates
- **Session Management**: Secure session handling
- **Role-based Access**: Different access levels for users

## 🚀 Quick Start

### Prerequisites
- PHP 7.4+ with MySQL/MariaDB
- Web server (Apache/Nginx) or XAMPP
- Python 3.8+ (optional, for ML engine)

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd AI_SAPP
   ```

2. **Set up the database**
   ```sql
   -- Import the database schema
   mysql -u root -p < database.sql
   ```

3. **Configure database connection**
   Update `config.php` with your database credentials (default DB is `smart_traffic_db`).

4. **Install Python dependencies** (optional)
   ```bash
   pip install -r requirements.txt
   ```

5. **Access the application**
   - XAMPP: `http://localhost/AI_SAPP/`
   - Production: `http://your-domain.com/`

## 📋 Input Features

The baseline predictor uses core traffic + environment features:

### Traffic Factors
- Vehicle count (per interval)
- Average speed (km/h)
- Density (veh/km)
- Reading time

### Environmental Factors
- Weather (clear/rain/fog/storm/snow)
- Visibility (m) (optional)
- Temperature (°C) (optional)

### Family & Background
- Family Income ($20,000-$200,000)
- Parent Education Level (1-5 scale)
- Learning Style (Visual, Auditory, Kinesthetic, Reading, Mixed)
- Motivation Level (1-10 scale)

## 🎮 Usage Guide

### 1. User Registration & Login
- Register a new account with username, email, and password
- Login with your credentials to access the dashboard

### 2. Generate AI Predictions
1. Navigate to the "New Prediction" section
2. Fill in the comprehensive student data form
3. Use "Load Sample Data" for quick testing
4. Click "Generate AI Prediction" and watch the progress
5. View detailed results with recommendations

### 3. Upload Student Data
1. Click "Upload Data" on the dashboard
2. Select a CSV file with student information
3. The system automatically maps columns
4. View upload success confirmation

### 4. View Reports & Analytics
1. Click "View Reports" to access analytics
2. View performance distribution charts
3. Analyze prediction trends over time
4. Export data as PDF or CSV

## 🛠️ Technical Architecture

### Backend Technologies
- **PHP 7.4+**: Main application logic and API endpoints
- **MySQL/MariaDB**: Database for data storage
- **PDO**: Secure database interactions
- **Session Management**: User authentication and state

### Frontend Technologies
- **HTML5 & CSS3**: Modern web standards
- **Bootstrap 5**: Responsive framework
- **JavaScript/jQuery**: Interactive functionality
- **Chart.js**: Data visualization
- **Font Awesome**: Icons and UI elements

### API Endpoints (Index actions)
- `POST /index.php?action=login` - User authentication
- `POST /index.php?action=register` - User registration
- `POST /index.php?action=predict` - Congestion + accident risk prediction
- `POST /index.php?action=upload_data` - Upload traffic readings CSV
- `POST /index.php?action=get_activity` - Get recent activity
- `POST /index.php?action=get_reports` - Get analytics data

## 📁 Project Structure

```
STMAPS/
├── 📄 index.php                    # Main application and API endpoints
├── 📄 dashboard.php                # Dashboard interface
├── 📄 login.php                    # Login page
├── 📄 register.php                 # Registration page
├── 📄 config.php                   # Database configuration
├── 📄 check_session.php            # Session management
├── 📄 logout.php                   # Logout functionality
├── 🐍 prediction_engine.py         # Python traffic engine (optional)
├── 📄 requirements.txt             # Python dependencies
├── 📄 database.sql                 # Database schema
├── 📄 .htaccess                    # Apache configuration
├── 📁 data/
│   └── upload_template_students.csv # Sample CSV template (traffic readings)
└── 📄 README.md                    # Project documentation
```

## 🔒 Security Features

- **Password Hashing**: Secure password storage using PHP's `password_hash()`
- **SQL Injection Prevention**: Prepared statements and parameterized queries
- **Session Security**: Secure session handling and validation
- **Input Validation**: Client-side and server-side validation
- **XSS Protection**: Output escaping and sanitization
- **CSRF Protection**: Form token validation

## 📊 Performance Categories

The AI model predicts student performance into four categories:

- **Excellent** (4.2+ score): Outstanding academic performance
- **Good** (3.5-4.2 score): Above-average performance
- **Average** (2.5-3.5 score): Standard performance
- **Poor** (<2.5 score): Below-average performance

## 🚀 Key Features Implemented

### ✅ Completed Features
- [x] User authentication and registration
- [x] AI prediction engine with progress indicators
- [x] CSV data upload with flexible column mapping
- [x] Real-time activity feed with pagination
- [x] Comprehensive reports and analytics
- [x] Interactive charts and visualizations
- [x] Export functionality (PDF/CSV)
- [x] User settings and password management
- [x] Responsive design for all devices
- [x] Sample data templates

### 🔧 Configuration Options
- **Python Engine**: Can be enabled/disabled in `config.php`
- **Database**: MySQL/MariaDB with configurable connection
- **Session Timeout**: Configurable session management
- **File Upload**: CSV upload with size limits

## 🐛 Troubleshooting

### Common Issues
1. **Prediction Engine Hanging**: Check Python dependencies and timeout settings
2. **Database Connection**: Verify credentials in `config.php`
3. **File Upload Issues**: Check file permissions and PHP upload limits
4. **Session Problems**: Clear browser cookies and check session configuration

### Error Logs
- Check PHP error logs for backend issues
- Monitor browser console for frontend errors
- Review database logs for connection problems

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is open source and available under the **MIT License**.

## 📞 Support

- **Documentation**: Check this README and inline code comments
- **Issues**: Report bugs via GitHub Issues
- **Email**: Contact the development team

## 🙏 Acknowledgments

- **Bootstrap Team**: For the responsive framework
- **Font Awesome**: For the icon library
- **Chart.js**: For data visualization
- **PHP & MySQL Communities**: For robust web development tools

---

**AI_SAPP** - Empowering education through artificial intelligence! 🎓🤖