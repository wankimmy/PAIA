# Security Documentation

This document outlines the security measures implemented in the PAIA application to ensure OWASP compliance and protect against common web vulnerabilities.

## 🔐 Authentication & Authorization

### Email Whitelist
- **Single User Access**: Only `putrafyp@gmail.com` is allowed to access the application
- **Middleware Protection**: `EnsureAuthorizedEmail` middleware checks email on all requests
- **Configuration**: Set `AUTHORIZED_EMAIL` in `.env` file
- **Logging**: All unauthorized access attempts are logged with IP and user agent

### OTP Authentication
- **Rate Limiting**: 5 OTP requests per minute, 10 verification attempts per minute
- **OTP Expiration**: 10 minutes
- **One-Time Use**: OTPs are marked as used after successful verification
- **Email Enumeration Prevention**: Generic error messages prevent email enumeration attacks
- **Input Validation**: Email format validation, code must be exactly 6 digits

## 🛡️ Security Headers (OWASP Compliance)

All responses include the following security headers:

- **X-Content-Type-Options**: `nosniff` - Prevents MIME type sniffing
- **X-Frame-Options**: `DENY` - Prevents clickjacking attacks
- **X-XSS-Protection**: `1; mode=block` - Enables XSS filtering
- **Referrer-Policy**: `strict-origin-when-cross-origin` - Controls referrer information
- **Content-Security-Policy**: Restricts resource loading to prevent XSS
- **Permissions-Policy**: Disables geolocation, microphone, camera
- **Strict-Transport-Security**: HSTS for HTTPS connections
- **Server Information**: Removed `X-Powered-By` and `Server` headers

## 🚦 Rate Limiting

- **API Endpoints**: 60 requests per minute (default)
- **Authentication**: 5 OTP requests per minute, 10 verifications per minute
- **AI Endpoints**: 20 requests per minute (AI chat, voice commands)
- **Prevents**: Brute force attacks, DoS attacks, API abuse

## 📝 Input Validation & Sanitization

### Maximum Length Limits
- **Title**: 500 characters
- **Description**: 10,000 characters
- **Body/Notes**: 50,000 characters
- **Label/Username**: 255 characters
- **Password**: 1,000 characters
- **AI Messages**: 10,000 characters

### Validation Rules
- All inputs are validated using Laravel's Validator
- String inputs are trimmed and sanitized
- File uploads are validated for type and size
- SQL injection prevention via Eloquent ORM (parameterized queries)

## 🔒 Data Protection

### Encryption
- **Notes**: Encrypted at rest using AES-256-CBC
- **Passwords**: Encrypted at rest using AES-256-CBC
- **Encryption Key**: Stored in `APP_KEY` environment variable

### Data Access Control
- All queries are scoped to the authenticated user
- User relationships ensure data isolation
- Foreign key constraints prevent orphaned records

## 📤 File Upload Security

### Import Controller
- **Max File Size**: 10MB (configurable)
- **Allowed Types**: JSON only (`application/json`, `text/json`)
- **MIME Type Validation**: Server-side validation of file type
- **Content Validation**: JSON structure validation before processing
- **Transaction Safety**: Database transactions ensure data integrity

### Voice Controller
- **Max File Size**: 10MB (configurable)
- **Allowed Types**: Audio files only (`mp3`, `wav`, `ogg`, `m4a`, `webm`)
- **MIME Type Validation**: Server-side validation

## 🚫 Error Handling & Data Leakage Prevention

### Production Error Responses
- Generic error messages (no stack traces)
- No file paths or line numbers exposed
- No database structure information
- No internal system details

### Debug Mode
- Full error details only when `APP_DEBUG=true`
- Never expose errors in production
- All errors logged server-side for debugging

### Logging
- Security events logged (unauthorized access, failed auth)
- Error details logged server-side only
- IP addresses and user agents logged for security monitoring

## 🌐 CORS Configuration

- **Allowed Origins**: Configured via `FRONTEND_URL` environment variable
- **Credentials**: Supported for authenticated requests
- **Methods**: All standard HTTP methods allowed
- **Headers**: All headers allowed (for API flexibility)

## 🔍 SQL Injection Prevention

- **Eloquent ORM**: All database queries use parameterized queries
- **Query Builder**: Laravel's query builder prevents SQL injection
- **No Raw Queries**: No raw SQL queries with user input
- **Input Binding**: All user inputs are bound as parameters

## 🧹 XSS Prevention

- **Output Escaping**: All user-generated content is escaped in responses
- **Content Security Policy**: Restricts script execution
- **Input Sanitization**: HTML tags stripped from inputs where appropriate
- **JSON Responses**: All API responses are JSON (no HTML injection)

## 🔐 CSRF Protection

- **Token Validation**: CSRF tokens validated for state-changing operations
- **API Exceptions**: OTP endpoints excluded (stateless authentication)
- **Sanctum**: Token-based authentication for API routes

## 📊 Security Monitoring

### Logged Events
- Unauthorized email access attempts
- Failed OTP verifications
- File upload attempts with invalid types
- Import/export operations
- Error occurrences (server-side only)

### Log Locations
- `storage/logs/laravel.log` - Application logs
- Security events include: IP address, user agent, timestamp

## ✅ Security Checklist

- [x] Email whitelist enforcement
- [x] Rate limiting on all endpoints
- [x] Security headers (OWASP compliance)
- [x] Input validation and sanitization
- [x] SQL injection prevention
- [x] XSS prevention
- [x] CSRF protection
- [x] Data encryption at rest
- [x] Error handling without data leakage
- [x] File upload validation
- [x] CORS configuration
- [x] Security logging
- [x] Authentication rate limiting
- [x] Password/OTP security

## 🔧 Configuration

### Required Environment Variables

```env
# Security
AUTHORIZED_EMAIL=putrafyp@gmail.com

# Application
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:... # Generated key

# CORS
FRONTEND_URL=https://kawan.safwanhakim.com
```

### Security Configuration File

Located at `config/security.php`:
- Authorized email
- Rate limiting settings
- Input length limits
- File upload limits

## 🚨 Production Deployment Checklist

1. ✅ Set `APP_ENV=production`
2. ✅ Set `APP_DEBUG=false`
3. ✅ Set `AUTHORIZED_EMAIL=putrafyp@gmail.com`
4. ✅ Generate secure `APP_KEY`
5. ✅ Configure `FRONTEND_URL` for CORS
6. ✅ Set up SSL/TLS certificate
7. ✅ Configure secure SMTP for email
8. ✅ Review and test all security measures
9. ✅ Monitor security logs regularly
10. ✅ Keep dependencies updated

## 📚 OWASP Top 10 Compliance

1. **A01: Broken Access Control** ✅
   - Email whitelist middleware
   - User-scoped queries
   - Authorization checks

2. **A02: Cryptographic Failures** ✅
   - AES-256-CBC encryption
   - Secure key storage
   - HTTPS enforcement

3. **A03: Injection** ✅
   - Eloquent ORM (parameterized queries)
   - Input validation
   - No raw SQL queries

4. **A04: Insecure Design** ✅
   - Security-first architecture
   - Single-user design
   - Minimal attack surface

5. **A05: Security Misconfiguration** ✅
   - Security headers
   - Error handling
   - CORS configuration

6. **A06: Vulnerable Components** ✅
   - Regular dependency updates
   - Composer security advisories

7. **A07: Authentication Failures** ✅
   - OTP-based authentication
   - Rate limiting
   - Session management

8. **A08: Software and Data Integrity** ✅
   - File upload validation
   - Transaction safety
   - Data integrity checks

9. **A09: Security Logging Failures** ✅
   - Comprehensive logging
   - Security event tracking
   - Error logging

10. **A10: Server-Side Request Forgery** ✅
    - No external URL fetching
    - Controlled API endpoints
    - Input validation

## 🔄 Regular Security Maintenance

1. **Update Dependencies**: Run `composer update` regularly
2. **Review Logs**: Check security logs weekly
3. **Monitor Access**: Review unauthorized access attempts
4. **Update Secrets**: Rotate keys and tokens periodically
5. **Security Audits**: Regular code reviews for security issues

