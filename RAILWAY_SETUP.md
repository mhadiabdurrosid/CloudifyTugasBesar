# Railway Deployment Guide for Cloudify

## 🚀 Quick Setup Instructions

### Step 1: Link MySQL Service and Set Environment Variables

**IMPORTANT**: Railway needs your MySQL database connection configured properly!

#### Option A: Link MySQL Service (Recommended)
1. Go to your Railway project
2. Click on your web service
3. Go to "Variables" tab
4. Click "Add Service Variable"
5. Select your MySQL service - Railway will auto-inject variables

#### Option B: Manual Environment Variables
If Option A doesn't work, manually add these in your web service Variables:

Based on your MySQL URL: `mysql://root:ifdumpRRCCYwVRygDxOWvSOZKsAYNfry@crossover.proxy.rlwy.net:16870/railway`

```
MYSQLHOST=crossover.proxy.rlwy.net
MYSQLPORT=16870
MYSQLUSER=root
MYSQLPASSWORD=ifdumpRRCCYwVRygDxOWvSOZKsAYNfry
MYSQLDATABASE=railway
```

**OR** add the full DATABASE_URL (Railway's format):
```
DATABASE_URL=mysql://root:ifdumpRRCCYwVRygDxOWvSOZKsAYNfry@crossover.proxy.rlwy.net:16870/railway
```

⚠️ **Make sure to add these to your WEB SERVICE, not just the MySQL service!**

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

## 🎯 Deployment Steps

   ### Step-by-Step Process:

   1. **Add Environment Variables FIRST (Critical!)**
      - Go to Railway Dashboard → Your Project
      - Click on your **web service** (not MySQL service!)
      - Go to "Variables" tab
      - Manually add the variables listed in Step 1 above
      - Click "Deploy" or wait for auto-deploy

   2. **Commit and Push Your Code**
      ```bash
      git add .
      git commit -m "Fix Railway deployment - add env check"
      git push
      ```

   3. **Monitor Deployment**
      - Watch Railway logs for "📡 Database: crossover.proxy.rlwy.net:16870"
      - Should see "✅ Database is ready!"
      - If you see "⚠️ No database configuration found!" → Variables not set correctly

   4. **Test Your Deployment**
      - Visit `https://your-app.railway.app/check_env.php` first
      - This page will show if environment variables are configured
      - Once check_env.php shows ✅ green, visit `/index.php`

   5. **Login with Default Credentials**
      - Username: `admin`
      - Password: `admin123`

   ## 📦 Files Modified

   - `Caddyfile` - Fixed TLS configuration for Railway
   - `Dockerfile` - Updated to use startup script
   - `start.sh` - New startup script with database initialization
   - `RAILWAY_SETUP.md` - This guide

   ---

   ✨ Your Cloudify app should now deploy successfully on Railway!
