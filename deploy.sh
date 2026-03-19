#!/bin/bash
# 🚀 Arabic Coupon Platform - Complete Deployment Script
# This script sets up the entire platform with all microservices

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="arabic-coupon-platform"
DOMAIN="9797.coupons"
EMAIL="admin@9797.coupons"

echo -e "${BLUE}🚀 Starting deployment of ${PROJECT_NAME}${NC}"

# Function to print status
print_status() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

# Check prerequisites
check_prerequisites() {
    echo "Checking prerequisites..."

    # Check if Docker is installed
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed. Please install Docker first."
        exit 1
    fi

    # Check if Docker Compose is installed
    if ! command -v docker-compose &> /dev/null; then
        print_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi

    # Check if Node.js is installed
    if ! command -v node &> /dev/null; then
        print_error "Node.js is not installed. Please install Node.js first."
        exit 1
    fi

    # Check if Python is installed
    if ! command -v python3 &> /dev/null; then
        print_error "Python 3 is not installed. Please install Python 3 first."
        exit 1
    fi

    # Check if Flutter is installed
    if ! command -v flutter &> /dev/null; then
        print_warning "Flutter is not installed. Mobile app build will be skipped."
    fi

    print_status "Prerequisites check passed"
}

# Setup environment variables
setup_environment() {
    echo "Setting up environment variables..."

    if [ ! -f ".env" ]; then
        cp .env.example .env
        print_warning "Please edit .env file with your actual configuration values"
        echo "Press Enter to continue after editing .env"
        read -r
    fi

    # Generate secure keys if not set
    if grep -q "your_super_secure_api_token_here" .env; then
        API_TOKEN=$(openssl rand -hex 32)
        sed -i "s/your_super_secure_api_token_here/$API_TOKEN/" .env
        print_status "Generated secure API token"
    fi

    if grep -q "your_jwt_secret_key_here" .env; then
        JWT_SECRET=$(openssl rand -hex 64)
        sed -i "s/your_jwt_secret_key_here/$JWT_SECRET/" .env
        print_status "Generated JWT secret"
    fi

    if grep -q "your_32_character_encryption_key" .env; then
        ENCRYPTION_KEY=$(openssl rand -hex 32)
        sed -i "s/your_32_character_encryption_key/$ENCRYPTION_KEY/" .env
        print_status "Generated encryption key"
    fi

    print_status "Environment setup completed"
}

# Build and start services
start_services() {
    echo "Building and starting services..."

    # Create necessary directories
    mkdir -p \
        data/warehouse \
        data/pipelines \
        data/analytics \
        infrastructure/nginx/ssl \
        infrastructure/k8s \
        infrastructure/security \
        ai/ml-models \
        ai/nlp \
        ai/computer-vision \
        ai/recommendation \
        testing/unit \
        testing/integration \
        testing/e2e \
        testing/performance \
        tools/scripts \
        tools/ci-cd \
        tools/monitoring

    # Build and start all services
    docker-compose up -d --build

    print_status "Services started successfully"
}

# Setup databases
setup_databases() {
    echo "Setting up databases..."

    # Wait for PostgreSQL to be ready
    echo "Waiting for PostgreSQL..."
    docker-compose exec -T postgres sh -c 'while ! pg_isready -U postgres; do sleep 1; done'

    # Run Laravel migrations
    docker-compose exec -T laravel-admin php artisan migrate --force
    docker-compose exec -T laravel-admin php artisan db:seed --force

    # Setup Redis
    docker-compose exec -T redis redis-cli FLUSHALL

    print_status "Databases setup completed"
}

# Setup monitoring
setup_monitoring() {
    echo "Setting up monitoring..."

    # Wait for services to be healthy
    echo "Waiting for services to be healthy..."
    sleep 30

    # Setup Grafana datasources and dashboards
    ./tools/scripts/setup-monitoring.sh

    print_status "Monitoring setup completed"
}

# Build Flutter app
build_flutter_app() {
    echo "Building Flutter app..."

    if command -v flutter &> /dev/null; then
        cd flutter_app

        # Get dependencies
        flutter pub get

        # Build web version
        flutter build web --release

        # Build Android APK (if Android SDK available)
        if [ -d "android" ]; then
            flutter build apk --release
        fi

        # Build iOS (if on macOS with Xcode)
        if [ "$(uname)" = "Darwin" ] && command -v xcodebuild &> /dev/null; then
            flutter build ios --release --no-codesign
        fi

        cd ..
        print_status "Flutter app built successfully"
    else
        print_warning "Flutter not available, skipping mobile app build"
    fi
}

# Deploy to Firebase
deploy_firebase() {
    echo "Deploying to Firebase..."

    # Deploy functions
    firebase deploy --only functions

    # Deploy web app
    firebase deploy --only hosting

    print_status "Firebase deployment completed"
}

# Setup SSL certificates
setup_ssl() {
    echo "Setting up SSL certificates..."

    # Use Let's Encrypt for production
    if [ "$APP_ENV" = "production" ]; then
        ./tools/scripts/setup-ssl.sh
    else
        print_warning "SSL setup skipped for development environment"
    fi

    print_status "SSL setup completed"
}

# Run tests
run_tests() {
    echo "Running tests..."

    # Python tests
    docker-compose exec -T api-gateway python -m pytest /app/tests/ -v

    # Laravel tests
    docker-compose exec -T laravel-admin php artisan test

    # Flutter tests (if available)
    if [ -d "flutter_app" ]; then
        cd flutter_app
        flutter test
        cd ..
    fi

    print_status "Tests completed"
}

# Setup CI/CD
setup_cicd() {
    echo "Setting up CI/CD..."

    # Setup GitHub Actions secrets
    ./tools/scripts/setup-github-secrets.sh

    print_status "CI/CD setup completed"
}

# Create admin user
create_admin_user() {
    echo "Creating admin user..."

    docker-compose exec -T laravel-admin php artisan tinker --execute="
    \\App\\Models\\User::create([
        'name' => 'Administrator',
        'email' => '$EMAIL',
        'password' => bcrypt('$ADMIN_PASSWORD'),
        'email_verified_at' => now(),
    ]);
    "

    print_status "Admin user created"
}

# Final setup
final_setup() {
    echo "Performing final setup..."

    # Setup cron jobs
    ./tools/scripts/setup-cron.sh

    # Setup log rotation
    ./tools/scripts/setup-logging.sh

    # Setup backups
    ./tools/scripts/setup-backups.sh

    # Generate API documentation
    ./tools/scripts/generate-docs.sh

    print_status "Final setup completed"
}

# Main deployment function
main() {
    echo "=========================================="
    echo "🚀 ARABIC COUPON PLATFORM DEPLOYMENT"
    echo "=========================================="

    check_prerequisites
    setup_environment
    start_services
    setup_databases
    setup_monitoring
    build_flutter_app
    deploy_firebase
    setup_ssl
    run_tests
    setup_cicd
    create_admin_user
    final_setup

    echo ""
    echo "=========================================="
    echo -e "${GREEN}🎉 DEPLOYMENT COMPLETED SUCCESSFULLY!${NC}"
    echo "=========================================="
    echo ""
    echo "Your Arabic Coupon Platform is now running at:"
    echo "🌐 Web App: https://$DOMAIN"
    echo "📱 API: https://api.$DOMAIN"
    echo "🔧 Admin: https://admin.$DOMAIN"
    echo "📊 Monitoring: https://monitoring.$DOMAIN"
    echo ""
    echo "Default credentials:"
    echo "Admin Email: $EMAIL"
    echo "Admin Password: $ADMIN_PASSWORD"
    echo ""
    echo "Next steps:"
    echo "1. Configure your domain DNS"
    echo "2. Setup SSL certificates"
    echo "3. Configure payment gateways"
    echo "4. Add coupon data"
    echo "5. Monitor performance"
    echo ""
    echo -e "${BLUE}Happy coupon hunting! 🎯${NC}"
}

# Run main function
main "$@"
