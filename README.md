# Capitito Inventory Management System

![Version](https://img.shields.io/badge/version-1.0.0-green.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL%20v2%2B-red.svg)

A comprehensive, modern inventory management system built specifically for **Capitito** with beautiful UI/UX design using brand colors.

**Developed by:** Okonudo EseAbasi  
**Company:** Bendless Tech  
**Version:** 1.0.0

---

## 🎨 Brand Colors

- **Primary Green:** `#185D30` - Main actions, headers, primary elements
- **Accent Red:** `#D02126` - Alerts, delete actions, important information
- **Background White:** `#FDFFFE` - Clean, readable backgrounds

---

## ✨ Features

### 📦 **Orders Management**
- Create new orders with dynamic item selection
- Real-time grand total calculation
- Multiple payment methods (Cash, Card, Transfer)
- Printable order confirmations with company branding
- Complete order history with filtering and pagination
- Admin-only edit/delete capabilities

### 📊 **Stock Inventory**
- Real-time stock tracking
- Automatic sales calculation from orders
- Role-based field editing (Admin vs Staff)
- Auto-calculated closing stock (Opening + Imports - Sales - HQ Returned)
- Daily stock records with date tracking

### 💰 **Financial Management**
- Daily financial summary tracking
- Automatic cash flow calculations
- Cash rollover (today's cash left = tomorrow's old cash)
- Expense tracking with remarks
- Permanent financial history storage
- Admin edit/delete capabilities for historical records

### 📅 **Product Summary**
- Real-time daily product summaries from orders
- Track items sold, quantities, times, and staff
- Historical summaries with advanced filtering
- Daily totals with auto-reset at midnight
- Comprehensive reporting dashboard

### 🗓️ **Reconciliation Calendar**
- Year-view calendar interface
- One-time reconciliation per date (cannot be undone)
- Staff tracking per reconciliation
- WhatsApp integration for evidence submission
- Skip dates and reconcile later

### 🔐 **Security Features**
- Nonce verification on all forms
- Data sanitization and validation
- SQL injection protection with prepared statements
- Role-based access control (RBAC)
- CSRF protection
- Capability checks on sensitive operations

### 🎯 **User Experience**
- Real-time digital clock on every page
- Responsive design (mobile, tablet, desktop)
- Modern card-based UI
- Intuitive navigation
- Accessible modals and forms
- Loading states and notifications
- Print-friendly layouts

---

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- Modern web browser (Chrome, Firefox, Safari, Edge)

---

## 🚀 Installation

1. **Upload Plugin**
   - Download the `capitito-inventory-management` folder
   - Upload to `/wp-content/plugins/` directory
   - Or install via WordPress admin: Plugins → Add New → Upload Plugin

2. **Activate Plugin**
   - Go to WordPress admin → Plugins
   - Find "Capitito Inventory Management System"
   - Click "Activate"

3. **Database Setup**
   - Plugin automatically creates all necessary database tables on activation
   - No manual database configuration required

4. **Configure Settings**
   - Go to Capitito IMS → Settings
   - Set your WhatsApp number for evidence submission
   - Configure company name for order confirmations

5. **Add Items**
   - Create a page with shortcode `[ims_admin_panel]`
   - Add your inventory items (name and price)

6. **Create Pages**
   - Create WordPress pages for each feature
   - Add respective shortcodes (see below)

---

## 📝 Shortcodes

### Available Shortcodes

```
[ims_orders]              - Orders page (Create new orders)
[ims_order_history]       - Order history (View past orders)
[ims_stock]               - Stock inventory management
[ims_product_summary]     - Daily product summary
[ims_financial_summary]   - Daily financial summary
[ims_financial_history]   - Financial history records
[ims_reconciliation]      - Reconciliation calendar
[ims_admin_panel]         - Admin panel (Admin only)
```

### Example Usage

Create a page titled "New Order" and add:
```
[ims_orders]
```

Create a page titled "Stock Management" and add:
```
[ims_stock]
```

---

## 👥 User Roles

### Administrator
**Full Access - All Capabilities:**
- ✅ Manage all inventory items (CRUD)
- ✅ Edit/delete any order
- ✅ Edit stock opening values
- ✅ Edit/delete financial records
- ✅ Access all features
- ✅ View all reports

### Capitito Staff
**Limited Access - Operational Tasks:**
- ✅ Create new orders
- ✅ View order history (read-only for others' orders)
- ✅ Update stock (imports, HQ returned only)
- ✅ Submit daily financial summaries
- ✅ Reconcile daily records
- ❌ Cannot edit opening stock
- ❌ Cannot delete orders/financial records
- ❌ Cannot manage items

---

## 🔄 Daily Operations Workflow

### 1. **Morning Setup**
- Staff logs in
- Checks yesterday's cash left (becomes today's old cash)
- Updates stock opening values (Admin only)

### 2. **During Day**
- Create orders as customers purchase
- Update stock imports as new inventory arrives
- Sales automatically update from orders

### 3. **End of Day**
- Complete financial summary:
  - Record cash, card, transfer payments
  - Enter expenses with remarks
  - Submit summary (becomes permanent)
- Cash left automatically becomes tomorrow's old cash
- Reconcile the day in calendar

### 4. **Reporting**
- View product summary for items sold
- Check financial history
- Review order history with filters

---

## 💾 Database Structure

### Tables Created

- `wp_capitito_ims_items` - Inventory items master
- `wp_capitito_ims_orders` - Order headers
- `wp_capitito_ims_order_items` - Order line items
- `wp_capitito_ims_stock` - Daily stock records
- `wp_capitito_ims_financial_summary` - Daily financial data
- `wp_capitito_ims_product_summary` - Daily product summaries
- `wp_capitito_ims_reconciliation` - Calendar reconciliation

All tables use proper indexing for optimal performance.

---

## 🎨 Customization

### Brand Colors

Colors are defined in CSS variables for easy customization:

```css
:root {
    --primary-green: #185D30;
    --accent-red: #D02126;
    --background-white: #FDFFFE;
}
```

To change colors, edit `assets/css/styles.css`

### WhatsApp Integration

Set your WhatsApp number in Settings:
- Format: Country code + number (no + or spaces)
- Example: `2348012345678`

---

## 🔧 Troubleshooting

### Issue: Shortcode displays raw text
**Solution:** Make sure plugin is activated and page is published.

### Issue: Stock not updating
**Solution:** Check user role permissions. Staff cannot edit opening values.

### Issue: Orders not appearing
**Solution:** Clear browser cache and refresh page.

### Issue: Financial summary already submitted
**Solution:** Only Admin can edit submitted summaries via Financial History.

### Issue: Can't reconcile date
**Solution:** Date may already be reconciled. Check calendar for checkmark.

---

## 📊 Reports & Analytics

### Available Reports

1. **Product Summary**
   - Items sold per day
   - Quantities and times
   - Staff performance

2. **Financial History**
   - Daily sales totals
   - Cash flow tracking
   - Expense reporting

3. **Order History**
   - Complete order records
   - Payment method breakdown
   - Staff order tracking

4. **Stock Reports**
   - Opening/closing stock
   - Import tracking
   - Sales by item

---

## 🔒 Security Best Practices

- ✅ All AJAX requests use nonce verification
- ✅ Data sanitized with WordPress functions
- ✅ SQL queries use prepared statements
- ✅ Capability checks on every action
- ✅ No direct file access allowed
- ✅ XSS protection with output escaping
- ✅ CSRF tokens on all forms

---

## 🆘 Support

For support, customization, or feature requests:

**Developer:** Okonudo EseAbasi  
**Company:** Bendless Tech  
**Email:** [Your Email]  
**Website:** [Your Website]

---

## 📜 License

This plugin is licensed under GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

---

## 🎯 Roadmap / Future Features

- [ ] Export reports to PDF/Excel
- [ ] Email notifications for low stock
- [ ] Barcode scanning support
- [ ] Multi-location support
- [ ] Advanced analytics dashboard
- [ ] Mobile app integration
- [ ] Supplier management
- [ ] Purchase order system

---

## 📝 Changelog

### Version 1.0.0 (2025-10-23)
- Initial release
- Complete inventory management system
- Orders, stock, financial tracking
- Product summaries and reconciliation
- Beautiful brand-colored UI/UX
- Role-based access control
- Real-time calculations
- Print functionality

---

## 🙏 Acknowledgments

Built with ❤️ by **Okonudo EseAbasi** from **Bendless Tech**

For **Capitito** - Excellence in inventory management.

---

**Made in Nigeria 🇳🇬**
