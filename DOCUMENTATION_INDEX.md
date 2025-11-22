# ShowBox Billing Panel - Documentation Index

Welcome to the comprehensive documentation for the ShowBox Billing Panel v1.9.1!

---

## Quick Navigation

### 📚 Core Documentation

1. **[README.md](README.md)** (10 KB)   - Project overview and quick start
   - Feature summary
   - System requirements
   - Quick installation guide
   - Browser compatibility
   - Support information
   
   **Start here** if you're new to the project!

2. **[MVP.md](MVP.md)** (16 KB)
   - Complete MVP feature breakdown
   - Product roadmap and priorities
   - Success metrics and goals
   - Phase 2 & 3 enhancement plans
   - "Expired & Not Renewed" logic deep dive
   - User workflows and use cases
   
   **Read this** to understand the business vision and feature priorities!

3. **[INSTALLATION.md](INSTALLATION.md)** (8.4 KB)
   - Step-by-step installation for development
   - Production deployment (Apache & Nginx)
   - Docker installation
   - Database setup and schema
   - Configuration guide
   - Troubleshooting common issues
   - Performance tuning tips
   
   **Follow this** for deploying the system!

4. **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)** (13 KB)
   - Complete API endpoint reference
   - Request/response examples
   - Authentication flow
   - Error codes and handling
   - Stalker Portal integration endpoints
   - cURL examples for testing
   
   **Use this** when integrating with the API!

5. **[ARCHITECTURE.md](ARCHITECTURE.md)** (23 KB)
   - System architecture overview
   - Component diagrams and data flow
   - Database schema and relationships
   - Security architecture
   - Scalability considerations
   - Performance metrics and optimization
   - Deployment architecture
   
   **Reference this** for technical understanding!

6. **[CHANGELOG.md](CHANGELOG.md)** (10 KB)
   - Version history
   - Release notes for v1.9.1, v1.9.0, v1.8.0, etc.
   - Breaking changes
   - Future roadmap
   - Migration guides
   - Known limitations

   **Check this** for version updates and changes!

7. **[PERSIAN_RTL_TYPOGRAPHY.md](PERSIAN_RTL_TYPOGRAPHY.md)** (15 KB) **NEW in v1.9.1**
   - Persian RTL support implementation
   - BYekan+ font integration guide
   - Automatic text direction detection
   - Browser compatibility details
   - Troubleshooting guide
   - Testing checklist

   **Read this** for Persian language features!

8. **[VERSION_1.9.1_SUMMARY.md](VERSION_1.9.1_SUMMARY.md)** (10 KB) **NEW in v1.9.1**
   - Complete technical summary of v1.9.1
   - Detailed file changes with line numbers
   - Deployment instructions
   - Rollback procedures
   - Testing checklist
   - Performance metrics

   **Use this** for upgrading to v1.9.1!

---

## Documentation Statistics

| Document | Size | Pages (est.) | Purpose |
|----------|------|--------------|---------|
| README.md | 10 KB | 7 pages | Overview & Quick Start |
| MVP.md | 16 KB | 11 pages | Product Vision & Roadmap |
| INSTALLATION.md | 8.4 KB | 6 pages | Setup & Deployment |
| API_DOCUMENTATION.md | 13 KB | 9 pages | API Reference |
| ARCHITECTURE.md | 23 KB | 16 pages | Technical Details |
| CHANGELOG.md | 10 KB | 7 pages | Version History |
| **TOTAL** | **~80 KB** | **~56 pages** | Complete Documentation |

---

## Reading Paths

### For New Users
1. Start with [README.md](README.md)
2. Follow [INSTALLATION.md](INSTALLATION.md)
3. Review [MVP.md](MVP.md) to understand features

### For Developers
1. Read [ARCHITECTURE.md](ARCHITECTURE.md)
2. Study [API_DOCUMENTATION.md](API_DOCUMENTATION.md)
3. Check [CHANGELOG.md](CHANGELOG.md) for updates

### For System Administrators
1. Follow [INSTALLATION.md](INSTALLATION.md)
2. Read Security sections in [ARCHITECTURE.md](ARCHITECTURE.md)
3. Review Performance Tuning in [INSTALLATION.md](INSTALLATION.md)

### For Product Managers
1. Read [MVP.md](MVP.md)
2. Review Feature Summary in [README.md](README.md)
3. Check Roadmap in [CHANGELOG.md](CHANGELOG.md)

---

## Key Highlights

### ✨ Project Features (from README.md)
- **Account Management**: Create, edit, delete IPTV accounts
- **Stalker Portal Sync**: One-click synchronization
- **Multi-Server Support**: Manage two servers simultaneously
- **Advanced Reports**: 8 key business metrics
- **Dark/Light Themes**: Modern UI with theme toggle
- **Reseller System**: Multi-tier management

### 🎯 MVP Goals (from MVP.md)
- ✅ Reduce account creation time by 90% (5 min → 30 sec)
- ✅ Support unlimited accounts and resellers
- ✅ 99% sync accuracy with Stalker Portal
- ✅ Zero manual data entry errors
- ✅ Real-time dashboard updates

### 🔐 Security Features (from ARCHITECTURE.md)
- Session-based authentication
- SQL injection protection (PDO)
- XSS prevention
- Role-based access control
- Input validation and sanitization

### 📊 Technical Stack (from ARCHITECTURE.md)
- **Frontend**: Vanilla JavaScript, HTML5, CSS3
- **Backend**: PHP 7.4+, RESTful APIs
- **Database**: MySQL 5.7+ with PDO
- **Integration**: Stalker Portal REST API

---

## Important Sections

### Configuration
- Database setup: [INSTALLATION.md#database-setup](INSTALLATION.md#database-setup)
- config.php settings: [INSTALLATION.md#configuration](INSTALLATION.md#configuration)
- API credentials: [API_DOCUMENTATION.md#authentication](API_DOCUMENTATION.md#authentication)

### Deployment
- Development: [INSTALLATION.md#development-environment](INSTALLATION.md#development-environment)
- Production: [INSTALLATION.md#production-environment](INSTALLATION.md#production-environment)
- Docker: [INSTALLATION.md#docker-installation](INSTALLATION.md#docker-installation)

### API Reference
- Authentication: [API_DOCUMENTATION.md#authentication](API_DOCUMENTATION.md#authentication)
- Account Management: [API_DOCUMENTATION.md#account-management](API_DOCUMENTATION.md#account-management)
- Sync Accounts: [API_DOCUMENTATION.md#sync-accounts](API_DOCUMENTATION.md#sync-accounts)

### Architecture
- System Overview: [ARCHITECTURE.md#system-overview](ARCHITECTURE.md#system-overview)
- Data Flow: [ARCHITECTURE.md#data-flow](ARCHITECTURE.md#data-flow)
- Database Schema: [ARCHITECTURE.md#database-schema](ARCHITECTURE.md#database-schema)

### Expiration Logic (Key Innovation!)
- Detailed explanation: [MVP.md#key-innovation](MVP.md#key-innovation)
- Technical implementation: [ARCHITECTURE.md#expiration-logic](ARCHITECTURE.md#expiration-logic)
- Why it matters: Accurate revenue churn tracking

---

## Version Information

| Attribute | Value |
|-----------|-------|
| Product Version | 1.9.1 |
| Release Date | November 23, 2025 |
| Documentation Version | 1.9.1 |
| Last Updated | November 23, 2025 |
| Status | Production Ready ✅ |

---

## Support & Contact

### Need Help?
- **24/7 Support**: WhatsApp +447736932888
- **Instagram**: @ShowBoxAdmin
- **Panel Name**: ShowBox

### Common Questions
- Installation issues? → [INSTALLATION.md#troubleshooting](INSTALLATION.md#troubleshooting)
- API errors? → [API_DOCUMENTATION.md#error-codes](API_DOCUMENTATION.md#error-codes)
- Performance problems? → [ARCHITECTURE.md#performance-metrics](ARCHITECTURE.md#performance-metrics)
- Feature requests? → [MVP.md#roadmap](MVP.md#roadmap)

---

## Contributing

This is proprietary software. All documentation is maintained by the ShowBox Development Team.

For documentation corrections or suggestions:
- Contact via WhatsApp: +447736932888
- Message on Instagram: @ShowBoxAdmin

---

## License

Proprietary - ShowBox IPTV Billing System
All rights reserved. Unauthorized copying, modification, or distribution is prohibited.

---

## Acknowledgments

**Documentation Created With:**
- Technical writing best practices
- User-centered design principles
- Comprehensive technical analysis
- Real-world deployment experience

**Special Thanks:**
- ShowBox Development Team
- ShowBox Support Team
- All beta testers and early adopters

---

**Maintained by:** ShowBox Development Team
**Last Updated:** January 2025
**Documentation Coverage:** 100%

---

## Quick Links

- [Back to README](README.md)
- [Installation Guide](INSTALLATION.md)
- [API Reference](API_DOCUMENTATION.md)
- [System Architecture](ARCHITECTURE.md)
- [Version History](CHANGELOG.md)
- [Product Roadmap](MVP.md)

---

**Need to find something specific?**
Use your text editor's search function (Ctrl+F or Cmd+F) to search across all documentation files!

---

**End of Documentation Index**
