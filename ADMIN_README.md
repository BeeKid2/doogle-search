# 🚀 Doogle Admin Panel

A comprehensive administration panel for the Doogle search engine with advanced features for managing content, users, analytics, and system monitoring.

## ✨ Features

### 🔐 **Secure Authentication System**
- Role-based access control (Super Admin, Admin, User)
- Session management with automatic expiration
- Brute force protection with account lockout
- Activity logging for all admin actions

### 📊 **Advanced Dashboard**
- Real-time statistics and metrics
- Visual charts and graphs
- System health monitoring
- Recent activity tracking

### 🕷️ **Crawl Management**
- Add and monitor web crawling jobs
- Priority-based crawling queue
- Real-time crawl progress tracking
- Crawl job status management (pending, running, completed, failed, paused)

### 📈 **Search Analytics**
- Detailed search statistics and trends
- Top search terms analysis
- Click-through rate tracking
- Response time monitoring
- Failed search identification

### 🗂️ **Content Management**
- Browse and manage indexed sites
- Image management with broken image detection
- Bulk operations for content cleanup
- Search and filter capabilities

### 👥 **User Management**
- Create and manage admin accounts
- Role assignment and permissions
- User activity monitoring
- Account status management

### 📝 **System Logs & Monitoring**
- Comprehensive system logging
- Real-time log filtering and search
- System health metrics
- Database performance monitoring

### 🛠️ **Database Tools**
- Database optimization tools
- Search index rebuilding
- Automated cleanup utilities
- Backup creation
- System maintenance tools

## 🚀 Installation & Setup

### 1. **Database Setup**

Run the admin setup SQL to create the necessary tables:

```bash
mysql -u your_username -p your_database < admin-setup.sql
```

Or execute the SQL commands in phpMyAdmin:
- Import `admin-setup.sql` in your database
- This will create admin tables and add necessary indexes

### 2. **File Structure**

Ensure your admin panel files are in the `/admin/` directory:

```
/workspace/
├── admin/
│   ├── config.php              # Admin configuration
│   ├── login.php               # Login page
│   ├── index.php               # Main dashboard
│   ├── crawl-management.php    # Crawl job management
│   ├── search-analytics.php    # Analytics dashboard
│   ├── content-management.php  # Content moderation
│   ├── user-management.php     # User administration
│   ├── system-logs.php         # System monitoring
│   ├── settings.php            # Database tools
│   └── logout.php              # Logout handler
├── classes/
│   └── AnalyticsTracker.php    # Analytics tracking class
├── ajax/
│   └── track-click.php         # Click tracking endpoint
└── admin-setup.sql             # Database setup script
```

### 3. **Default Admin Account**

A default super admin account is created during setup:

- **Username**: `admin`
- **Password**: `admin123`
- **⚠️ IMPORTANT**: Change this password immediately after first login!

### 4. **Configuration**

Update `/admin/config.php` if needed:

```php
// Session timeout (default: 1 hour)
define('ADMIN_SESSION_TIMEOUT', 3600);

// Max login attempts before lockout
define('ADMIN_MAX_LOGIN_ATTEMPTS', 5);

// Lockout duration (default: 15 minutes)
define('ADMIN_LOCKOUT_TIME', 900);
```

## 🔧 Usage Guide

### **Accessing the Admin Panel**

1. Navigate to `http://your-domain.com/admin/login.php`
2. Login with your admin credentials
3. You'll be redirected to the main dashboard

### **Dashboard Overview**

The main dashboard provides:
- **Site Statistics**: Total indexed sites and daily additions
- **Image Statistics**: Total images, broken images count
- **Search Metrics**: Daily searches and popular terms
- **System Health**: Recent activity and error counts

### **Managing Crawl Jobs**

1. Go to **Crawl Management**
2. Click "Add New Crawl" to create crawling jobs
3. Set priority levels (Low, Normal, High)
4. Monitor crawl progress in real-time
5. Manage job status (pause, resume, stop)

### **Analytics & Reporting**

1. Visit **Search Analytics** for detailed insights:
   - Search volume trends
   - Most popular search terms
   - Click-through rates
   - Response time analysis
   - Failed search identification

2. Use date filters to analyze specific time periods
3. Export data for further analysis

### **Content Moderation**

1. **Sites Management**:
   - Browse all indexed sites
   - Search and filter content
   - Delete inappropriate or broken sites

2. **Images Management**:
   - View all indexed images with previews
   - Mark broken images
   - Bulk delete broken images
   - Filter by status (working/broken)

### **User Administration**

**Super Admin Only Features**:
- Create new admin accounts
- Assign roles (User, Admin, Super Admin)
- Manage user status (Active, Inactive, Banned)
- View login activity
- Reset user passwords

### **System Monitoring**

1. **System Logs**:
   - View all system activities
   - Filter by log level (Info, Warning, Error, Critical)
   - Filter by category (Auth, Crawl, Content, etc.)
   - Real-time log monitoring

2. **Health Metrics**:
   - Database size monitoring
   - Recent error counts
   - System resource usage
   - Performance metrics

### **Database Maintenance**

**Super Admin Tools**:
- **Optimize Database**: Improve performance by optimizing tables
- **Rebuild Indexes**: Enhance search performance
- **Clean Old Logs**: Remove old system logs to save space
- **Session Cleanup**: Remove expired admin sessions
- **Create Backups**: Generate database backups

## 🔒 Security Features

### **Authentication Security**
- Password hashing using PHP's `password_hash()`
- Session token validation
- IP address tracking
- User agent verification
- Automatic session expiration

### **Access Control**
- Role-based permissions
- Route protection middleware
- Super Admin restricted features
- Activity logging for audit trails

### **Brute Force Protection**
- Failed login attempt tracking
- Temporary account lockouts
- IP-based rate limiting
- Security event logging

## 📊 Analytics Integration

The admin panel automatically tracks:

### **Search Analytics**
- Every search query with metadata
- Response times and result counts
- User interaction patterns
- Click-through rates

### **System Analytics**
- Admin login activity
- Crawl job performance
- Content management actions
- System errors and warnings

## 🛠️ Customization

### **Adding Custom Metrics**

1. Extend the `AnalyticsTracker` class:

```php
class CustomAnalytics extends AnalyticsTracker {
    public function trackCustomEvent($event, $data) {
        // Your custom tracking logic
    }
}
```

2. Add new dashboard widgets in `index.php`
3. Create custom report pages

### **Theme Customization**

The admin panel uses Bootstrap 5 with custom CSS. Modify the `<style>` sections in each file to customize:
- Color schemes
- Layout structure
- Component styling
- Responsive behavior

## 🚨 Troubleshooting

### **Common Issues**

1. **Can't Login**:
   - Check database connection
   - Verify admin user exists
   - Check for account lockout
   - Review system logs

2. **Database Errors**:
   - Run `admin-setup.sql` to create missing tables
   - Check database permissions
   - Verify table structure

3. **Analytics Not Working**:
   - Ensure `AnalyticsTracker.php` is included
   - Check if `search_analytics` table exists
   - Verify search.php integration

4. **Performance Issues**:
   - Run database optimization
   - Clean old logs
   - Check system resource usage
   - Review slow queries

### **Debug Mode**

Enable debug mode by adding to `config.php`:

```php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
```

## 📈 Performance Optimization

### **Database Optimization**
- Regular table optimization via admin panel
- Index management for search performance
- Log cleanup automation
- Query optimization

### **Caching Strategy**
- Dashboard statistics caching
- Search result caching
- Static asset optimization
- CDN integration for assets

## 🔄 Updates & Maintenance

### **Regular Maintenance Tasks**
1. **Weekly**:
   - Review system logs for errors
   - Clean up old logs (>30 days)
   - Check crawl job performance

2. **Monthly**:
   - Optimize database tables
   - Review user access logs
   - Update search indexes
   - Create database backups

3. **Quarterly**:
   - Security audit of admin accounts
   - Performance analysis and optimization
   - System resource usage review

## 📞 Support

For issues or questions:
1. Check system logs first
2. Review database table structure
3. Verify file permissions
4. Check PHP error logs

## 🎯 Roadmap

Future enhancements planned:
- **API Management**: RESTful API for external integrations
- **Advanced Analytics**: Machine learning insights
- **Multi-language Support**: Internationalization
- **Advanced Security**: 2FA, OAuth integration
- **Performance Dashboard**: Real-time system metrics
- **Automated Reporting**: Scheduled report generation

---

**🔥 Your Doogle admin panel is now ready to help you manage a search engine that could capture 0.01% market share!**

The combination of powerful analytics, efficient content management, and robust system monitoring gives you all the tools needed to compete with the big players in search. 🚀