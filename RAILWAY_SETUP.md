# Railway Deployment Guide for Cloudify

## 🚀 Quick Setup Instructions

### Step 1: Set Environment Variables in Railway

Go to your Railway project → Variables tab and add these variables:

Based on your MySQL URL: `mysql://root:ifdumpRRCCYwVRygDxOWvSOZKsAYNfry@crossover.proxy.rlwy.net:16870/railway`

Add these individual variables:

```
MYSQLHOST=crossover.proxy.rlwy.net
MYSQLPORT=16870
MYSQLUSER=root
MYSQLPASSWORD=ifdumpRRCCYwVRygDxOWvSOZKsAYNfry
MYSQLDATABASE=railway
```

**✅ Railway will automatically inject these when your MySQL service is linked.**

### Step 2: Deploy Your Application

1. **Commit your changes:**
   ```bash
   git add .
   git commit -m "Fix Railway deployment config"
   git push
   ```

2. **Railway will automatically redeploy** with the new configuration.

### Step 3: Monitor Deployment Logs

Watch for these messages in the Railway logs:

```
🚀 Starting Cloudify application...
⏳ Waiting for database connection...
✅ Database is ready!
📊 Initializing database schema...
✅ Database schema initialized
👥 Setting up users...
🎉 Initialization complete!
🌐 Starting FrankenPHP web server...
```

## 🔧 What We Fixed

1. **TLS Certificate Issues**
   - Changed Caddyfile to serve plain HTTP on `:{$PORT:80}`
   - Railway handles TLS at the edge, so container doesn't need HTTPS

2. **Database Connection**
   - Added proper database connection wait logic
   - Automatically initializes database schema on startup
   - Uses Railway's MySQL environment variables

3. **Startup Script**
   - Created `start.sh` to handle initialization sequence
   - Waits for database to be ready before starting
   - Initializes schema and creates default users

## 📝 Default Login Credentials

After successful deployment, you can login with:

- **Admin Account:**
  - Username: `admin`
  - Password: `admin123`

- **User Account:**
  - Username: `user1`
  - Password: `user123`

## 🐛 Troubleshooting

### Application still fails to respond?

1. **Check Railway Logs:**
   - Look for database connection errors
   - Verify environment variables are set correctly

2. **Verify Database Service:**
   - Ensure MySQL service is running in Railway
   - Check that services are linked in Railway project

3. **Check Port Binding:**
   - Railway automatically sets the `PORT` variable
   - Our app listens on `:{$PORT:80}`

4. **Database Connection Test:**
   - Logs should show "✅ Database is ready!"
   - If stuck at "Waiting for database..." check MySQL service status

### Still having issues?

Check that Railway has created these environment variables automatically when you added the MySQL service:
- `MYSQLHOST`
- `MYSQLPORT`
- `MYSQLUSER`
- `MYSQLPASSWORD`
- `MYSQLDATABASE`

These should match the values in your MySQL connection URL.

## 🎯 Next Steps

1. Commit and push the changes
2. Wait for Railway to redeploy
3. Check the deployment logs for success messages
4. Access your application via Railway's provided URL
5. Login with default credentials and change passwords

## 📦 Files Modified

- `Caddyfile` - Fixed TLS configuration for Railway
- `Dockerfile` - Updated to use startup script
- `start.sh` - New startup script with database initialization
- `RAILWAY_SETUP.md` - This guide

---

✨ Your Cloudify app should now deploy successfully on Railway!
