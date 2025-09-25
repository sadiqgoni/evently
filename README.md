# Evently - Event Management System

A comprehensive event management platform built with PHP that allows users to discover, create, and manage events. The system features role-based access control with separate interfaces for customers, vendors, and administrators.

## 🌟 Features

### For Customers
- **Event Discovery**: Browse and search through a wide variety of events
- **Ticket Booking**: Easy ticket purchasing with secure payment processing
- **Digital Tickets**: Download and manage digital tickets with QR codes
- **Wallet System**: Manage account balance for quick payments
- **Event Categories**: Filter events by categories like Music, Sports, Conferences, etc.

### For Vendors/Event Organizers
- **Event Creation**: Create and publish events with rich details and images
- **Event Management**: Edit, update, and manage event information
- **Sales Analytics**: Track ticket sales and revenue
- **Earnings Dashboard**: Monitor earnings and request withdrawals
- **Ticket Scanning**: QR code scanning for event check-ins

### For Administrators
- **User Management**: Manage customers, vendors, and their accounts
- **Event Oversight**: Monitor and moderate all events on the platform
- **Transaction Management**: Handle payments, refunds, and withdrawals
- **System Analytics**: Comprehensive dashboard with system-wide statistics

## 📸 Screenshots

### Homepage
![Homepage](assets/images/Screenshot%202025-09-25%20at%2015.10.04.png)
*Clean and modern homepage with featured events*

### Event Details
![Event Details](assets/images/Screenshot%202025-09-25%20at%2015.10.24.png)
*Detailed event information with booking options*

### Event Creation
![Event Creation](assets/images/Screenshot%202025-09-25%20at%2015.10.43.png)
*Easy-to-use event creation form*

### Customer Dashboard
![Customer Dashboard](assets/images/Screenshot%202025-09-25%20at%2015.10.53.png)
*Customer dashboard with ticket management*

### Admin Panel
![Admin Panel](assets/images/Screenshot%202025-09-25%20at%2015.11.21.png)
*Powerful admin interface for system management*

### User Management
![User Management](assets/images/Screenshot%202025-09-25%20at%2015.11.32.png)
*Comprehensive user management system*

### System Analytics
![System Analytics](assets/images/Screenshot%202025-09-25%20at%2015.11.43.png)
*Detailed analytics and reporting*

## 🚀 Technology Stack

- **Backend**: PHP 8.0+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, Bootstrap 5
- **JavaScript**: Vanilla JS with modern ES6+ features
- **PDF Generation**: TCPDF for ticket generation
- **QR Code**: QR code generation for tickets
- **Server**: Apache (XAMPP compatible)

## 🛠️ Installation

### Prerequisites
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Apache web server
- Composer (for dependency management)

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/sadiqgoni/evently.git
   cd evently
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Database Setup**
   - Create a MySQL database named `evently`
   - Import the database schema (SQL file to be provided)
   - Update database credentials in `includes/config.php`

4. **Configure Environment**
   ```php
   // includes/config.php
   $servername = "localhost";
   $username = "your_db_username";
   $password = "your_db_password";
   $dbname = "evently";
   ```

5. **Set Directory Permissions**
   ```bash
   chmod -R 777 uploads/
   ```

6. **Access the Application**
   - Place the project in your web server directory (e.g., `htdocs` for XAMPP)
   - Visit `http://localhost/evently` in your browser

## 📁 Project Structure

```
evently/
├── admin/                  # Admin panel files
├── auth/                   # Authentication pages
├── customer/               # Customer dashboard and features
├── vendor/                 # Vendor dashboard and features
├── events/                 # Public event pages
├── includes/               # Core PHP includes
│   ├── config.php         # Database configuration
│   ├── functions.php      # Utility functions
│   ├── auth_middleware.php # Authentication middleware
│   ├── header.php         # Common header
│   └── footer.php         # Common footer
├── assets/                 # Static assets
│   ├── css/               # Stylesheets
│   └── images/            # Project screenshots
├── uploads/                # User uploaded files
│   └── events/            # Event images
└── vendor/                 # Composer dependencies
```

## 🔐 Security Features

- **SQL Injection Protection**: Prepared statements for all database queries
- **XSS Prevention**: Input sanitization and output escaping
- **Authentication**: Secure session-based authentication
- **Authorization**: Role-based access control
- **File Upload Security**: Type validation and secure file handling
- **Password Security**: Secure password hashing

## 🎯 Key Functionalities

### Event Management
- Create events with rich media support
- Set ticket prices and availability
- Manage event categories and tags
- Real-time availability tracking

### Payment System
- Integrated wallet system
- Secure payment processing
- Transaction history and receipts
- Vendor earnings management

### Ticket System
- QR code generation for tickets
- PDF ticket downloads
- Mobile-friendly ticket scanning
- Duplicate prevention

### Analytics & Reporting
- Sales analytics for vendors
- User engagement metrics
- Revenue tracking
- System-wide statistics

## 🚦 Getting Started

### Default User Accounts
After installation, you can create accounts through the registration system or use the admin panel to create initial users.

### User Roles
1. **Customer**: Can browse events, purchase tickets, and manage their account
2. **Vendor**: Can create events, manage sales, and track earnings
3. **Admin**: Full system access with user and event management capabilities

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Sadiq Goni**
- GitHub: [@sadiqgoni](https://github.com/sadiqgoni)
- Project Link: [https://github.com/sadiqgoni/evently](https://github.com/sadiqgoni/evently)

## 🙏 Acknowledgments

- Bootstrap team for the amazing CSS framework
- TCPDF for PDF generation capabilities
- All contributors who helped improve this project

## 📞 Support

If you encounter any issues or have questions, please:
1. Check the existing issues on GitHub
2. Create a new issue with detailed information
3. Contact the maintainer directly

---

**Made with ❤️ for the event management community**