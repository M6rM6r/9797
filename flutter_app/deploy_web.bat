@echo off
REM Firebase Web Deployment Script for Flutter App
REM Arabic Coupon App - Auto-Deploy After Changes

echo 🚀 Starting Flutter Web Deployment
echo ======================================

REM Check if Flutter is installed
flutter --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Flutter not found. Please install Flutter first.
    echo 📥 Download from: https://flutter.dev/docs/get-started/install
    pause
    exit /b 1
)

REM Check if Firebase CLI is installed
firebase --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ Firebase CLI not found. Installing...
    npm install -g firebase-tools
    if %errorlevel% neq 0 (
        echo ❌ Failed to install Firebase CLI
        pause
        exit /b 1
    )
)

REM Navigate to flutter app directory
cd /d "%~dp0"

echo 📱 Building Flutter web app...
echo.

REM Build Flutter web app
flutter build web --no-sound-null-safety

if %errorlevel% neq 0 (
    echo ❌ Flutter build failed
    pause
    exit /b 1
)

echo ✅ Flutter web build completed
echo.

REM Navigate to web directory
cd build\web

echo 🔥 Deploying to Firebase Hosting...
echo.

REM Deploy to Firebase Hosting
firebase deploy --only hosting

if %errorlevel% neq 0 (
    echo ❌ Firebase deployment failed
    pause
    exit /b 1
)

echo.
echo ✅ Deployment completed successfully!
echo.
echo 🌍 Your Flutter Web App is now live at:
echo    https://x9797x707x.web.app
echo.
echo 📊 Firebase Analytics is tracking all interactions
echo 🔥 Firebase Hosting provides global CDN and SSL
echo.
echo 🎯 Next Steps:
echo 1. Visit https://x9797x707x.web.app
echo 2. Monitor Flutter app performance
echo 3. Check Firebase Console for analytics
echo 4. Test mobile app features in web version
echo.
echo 🔗 Firebase Console: https://console.firebase.google.com/project/x9797x707x
echo.
echo 🎉 Press any key to open your deployed Flutter Web App...
pause >nul
start https://x9797x707x.web.app
