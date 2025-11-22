# SplashOrder

**Multi-tenant Restaurant Online Ordering SaaS Platform**

A complete, production-ready SaaS solution for restaurant online ordering built with PHP 7.x+ and MySQL. Features multi-tenancy, subscription management, public ordering interface, and REST API.

---

## Features

### Core Features
- ✅ **Multi-tenant Architecture**: Isolated data for each restaurant brand
- ✅ **Branch Management**: Support multiple locations per restaurant
- ✅ **Menu Management**: Categories, items, options, and modifiers
- ✅ **Online Ordering**: Public ordering interface for delivery and pickup
- ✅ **Order Management**: Real-time order tracking with status updates
- ✅ **Customer Management**: Guest checkout and registered customers
- ✅ **Coupons & Discounts**: Flexible coupon system with validation
- ✅ **Subscription Plans**: Tiered plans with usage quotas
- ✅ **Billing & Invoicing**: Invoice generation and payment tracking
- ✅ **Analytics & Reports**: Sales reports and best-selling items
- ✅ **REST API**: External order creation via API
- ✅ **File Uploads**: Secure image uploads for logos and menu items
- ✅ **CSRF Protection**: Built-in security against CSRF attacks
- ✅ **Input Validation**: Comprehensive validation and sanitization

### Tech Stack
- **Backend**: PHP 7.0+ (compatible with PHP 8.x)
- **Database**: MySQL 5.7+ / MariaDB 10.2+
- **Frontend**: Vanilla JavaScript, HTML5, CSS3
- **Architecture**: Custom lightweight MVC framework
- **Security**: PDO prepared statements, password hashing, CSRF tokens

---

## Installation

### Requirements
- PHP >= 7.0 (tested up to PHP 8.x)
- MySQL >= 5.7 or MariaDB >= 10.2
- Apache/Nginx with mod_rewrite enabled
- PHP Extensions: `pdo`, `pdo_mysql`, `mbstring`, `gd` (for image processing)

### Step 1: Clone the Repository

```bash
git clone https://github.com/ahmedsaadawi13/SplashOrder.git
cd SplashOrder
```

### Step 2: Configure Environment

Copy the environment file and update with your database credentials:

```bash
cp .env.example .env
```

Edit `.env`:
```ini
DB_HOST=localhost
DB_DATABASE=splashorder
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

### Step 3: Create Database

Import the database schema:

```bash
mysql -u your_db_user -p < database.sql
```

This will create:
- All database tables with proper indexes
- Demo data including 2 restaurants with full menus
- Admin and user accounts
- Sample orders and customers

### Step 4: Set Permissions

Ensure the storage directory is writable:

```bash
chmod -R 775 storage/uploads
chown -R www-data:www-data storage/uploads  # Adjust for your web server user
```

### Step 5: Configure Web Server

#### Apache

Create a virtual host configuration:

```apache
<VirtualHost *:80>
    ServerName splashorder.local
    DocumentRoot /path/to/SplashOrder/public

    <Directory /path/to/SplashOrder/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/splashorder-error.log
    CustomLog ${APACHE_LOG_DIR}/splashorder-access.log combined
</VirtualHost>
```

Enable mod_rewrite:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### Nginx

```nginx
server {
    listen 80;
    server_name splashorder.local;
    root /path/to/SplashOrder/public;

    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?url=$uri&$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;  # Adjust PHP version
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### Step 6: Access the Application

Visit: `http://splashorder.local` (or your configured domain)

---

## Default Credentials

### Platform Admin
- Email: `admin@splashorder.com`
- Password: `password`

### Tenant Admins
**Pizza Paradise:**
- Email: `john@pizzaparadise.com`
- Password: `password`

**Burger Palace:**
- Email: `sarah@burgerpalace.com`
- Password: `password`

### Staff Users
- `mike@pizzaparadise.com` / `password`
- `lisa@burgerpalace.com` / `password`

---

## Public Ordering Demo

### Pizza Paradise
Visit: `http://splashorder.local/order/pizza-paradise`

### Burger Palace
Visit: `http://splashorder.local/order/burger-palace`

---

## REST API Documentation

### Authentication

All API requests require an API key in the header:

```
X-API-KEY: your_tenant_api_key
```

To get API keys for demo tenants, query the database:

```sql
SELECT name, api_key FROM tenants;
```

### Create Order Endpoint

**Endpoint:** `POST /api/orders/create`

**Headers:**
```
Content-Type: application/json
X-API-KEY: your_tenant_api_key
```

**Request Body:**
```json
{
  "customer_name": "John Doe",
  "customer_phone": "+1-555-0100",
  "customer_email": "john@example.com",
  "order_type": "delivery",
  "delivery_address": "123 Main St",
  "delivery_city": "New York",
  "delivery_area": "Manhattan",
  "payment_method": "cod",
  "notes": "Please ring doorbell",
  "items": [
    {
      "menu_item_id": 1,
      "quantity": 2
    },
    {
      "menu_item_id": 2,
      "quantity": 1
    }
  ]
}
```

**For Pickup Orders:**
```json
{
  "customer_name": "Jane Smith",
  "customer_phone": "+1-555-0200",
  "order_type": "pickup",
  "branch_id": 1,
  "payment_method": "online",
  "items": [
    {
      "menu_item_id": 3,
      "quantity": 1
    }
  ]
}
```

**Success Response (201):**
```json
{
  "success": true,
  "message": "Order created successfully",
  "order": {
    "id": 123,
    "order_number": "ORD-A1B2C3D4",
    "status": "new",
    "total": 45.99,
    "created_at": "2025-01-15 14:30:00"
  }
}
```

**Error Responses:**

401 Unauthorized:
```json
{
  "error": "Invalid API key"
}
```

400 Bad Request:
```json
{
  "error": "Missing required field: customer_name"
}
```

### cURL Example

```bash
curl -X POST http://splashorder.local/api/orders/create \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: pp_abc123..." \
  -d '{
    "customer_name": "Test Customer",
    "customer_phone": "+1-555-9999",
    "order_type": "delivery",
    "delivery_address": "456 Oak Ave",
    "delivery_city": "Los Angeles",
    "payment_method": "cod",
    "items": [
      {"menu_item_id": 1, "quantity": 1}
    ]
  }'
```

---

## Project Structure

```
SplashOrder/
├── app/
│   ├── controllers/      # Application controllers
│   │   ├── Auth.php
│   │   ├── Dashboard.php
│   │   ├── Orders.php
│   │   ├── PublicOrder.php
│   │   └── Api/
│   │       └── Orders.php
│   ├── models/          # Data models
│   │   ├── Tenant.php
│   │   ├── User.php
│   │   ├── Order.php
│   │   ├── MenuItem.php
│   │   └── ...
│   ├── views/           # View templates
│   │   ├── layouts/
│   │   ├── auth/
│   │   ├── dashboard/
│   │   ├── orders/
│   │   └── public/
│   └── core/            # Core framework
│       ├── Database.php
│       ├── Router.php
│       ├── Controller.php
│       ├── Model.php
│       ├── Auth.php
│       └── ...
├── config/              # Configuration files
├── public/              # Public web root
│   ├── index.php        # Front controller
│   ├── .htaccess
│   └── assets/
│       ├── css/
│       ├── js/
│       └── img/
├── storage/
│   └── uploads/         # Uploaded files
├── tests/               # Test files
├── .env.example         # Environment template
├── database.sql         # Database schema
└── README.md
```

---

## User Roles

### Platform Admin
- Full system access
- Manage all tenants
- View platform-wide statistics

### Tenant Admin
- Manage restaurant settings
- Manage branches, menu, orders
- View subscription and billing
- Manage staff users

### Staff
- View and manage orders
- View menu and customers
- Limited administrative access

---

## Subscription Plans

The system includes 3 pre-configured plans:

### Starter - $29.99/month
- 1 Branch
- 50 Menu Items
- 500 Orders/month
- Basic Support

### Professional - $79.99/month
- 5 Branches
- 200 Menu Items
- 2,000 Orders/month
- Priority Support

### Enterprise - $199.99/month
- Unlimited Branches
- Unlimited Menu Items
- Unlimited Orders
- Dedicated Support

Quotas are enforced automatically. Tenants are blocked from exceeding limits.

---

## Testing

### Manual Testing Checklist

#### Authentication
- [ ] User registration
- [ ] User login
- [ ] User logout
- [ ] Password validation
- [ ] Email validation
- [ ] CSRF token verification

#### Multi-tenancy
- [ ] Tenant isolation (users can't see other tenants' data)
- [ ] Tenant-specific menu items
- [ ] Tenant-specific orders
- [ ] Tenant-specific branches

#### Orders
- [ ] Create order (delivery)
- [ ] Create order (pickup)
- [ ] Apply coupon code
- [ ] Update order status
- [ ] View order details
- [ ] Order filtering by status

#### Subscriptions
- [ ] Branch quota enforcement
- [ ] Menu item quota enforcement
- [ ] Orders per month tracking
- [ ] Quota warning messages

#### API
- [ ] Create order via API
- [ ] API key authentication
- [ ] Invalid API key rejection
- [ ] Missing required fields validation
- [ ] Invalid menu item rejection

### Running Functional Tests

```bash
php tests/functional_tests.php
```

---

## Deployment

### Production Checklist

1. **Environment Configuration**
   ```bash
   # Set to production
   APP_ENV=production

   # Use strong database credentials
   DB_PASSWORD=strong_random_password
   ```

2. **Security Settings**
   - Disable error display in PHP
   - Enable HTTPS
   - Set secure cookie settings
   - Configure firewall rules

3. **Performance**
   - Enable OPcache
   - Configure MySQL query cache
   - Use CDN for static assets
   - Enable gzip compression

4. **Backups**
   - Set up automated database backups
   - Backup uploaded files regularly
   - Test restore procedures

5. **Monitoring**
   - Set up error logging
   - Monitor disk space
   - Track application performance
   - Set up uptime monitoring

---

## Security Features

- **SQL Injection Protection**: PDO prepared statements throughout
- **XSS Protection**: Output escaping via `e()` helper
- **CSRF Protection**: Token validation on all state-changing requests
- **Password Security**: BCrypt hashing with cost factor 10
- **Input Validation**: Comprehensive validation rules
- **File Upload Security**: Type and size validation, unique filenames
- **Authentication**: Session-based with secure logout
- **Multi-tenancy**: Automatic tenant isolation at model layer

---

## Performance Considerations

### Database Optimization
- Indexed columns for common queries (tenant_id, status, created_at)
- Composite indexes for multi-column queries
- Foreign key constraints with appropriate ON DELETE actions

### Caching Recommendations
- Implement Redis/Memcached for session storage
- Cache frequently accessed menu data
- Use query result caching for reports

### Scaling
- Horizontal scaling supported (stateless design)
- Database replication for read-heavy workloads
- CDN integration for assets and uploads

---

## Future Enhancements

- Email notifications via SMTP
- SMS notifications for order updates
- Real-time order tracking (WebSockets)
- Mobile apps (iOS/Android)
- Multi-language support
- Advanced reporting with charts
- Inventory management
- Table reservation system
- Loyalty program
- Payment gateway integration (Stripe, PayPal)

---

## License

This project is open-source software for educational and demonstration purposes.

---

## Support

For issues and questions:
- GitHub Issues: [https://github.com/ahmedsaadawi13/SplashOrder/issues](https://github.com/ahmedsaadawi13/SplashOrder/issues)

---

## Credits

Developed as a comprehensive example of modern PHP SaaS architecture.

**Technologies Used:**
- PHP 7.x - 8.x
- MySQL / MariaDB
- Vanilla JavaScript
- CSS3

---

## Changelog

### Version 1.0.0 (2025-01-15)
- Initial release
- Multi-tenant architecture
- Complete ordering system
- Subscription management
- REST API
- Full admin dashboard
- Public ordering interface

---

**Built with ❤️ for the restaurant industry**
