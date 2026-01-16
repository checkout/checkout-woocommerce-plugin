#!/bin/bash

echo "🚀 Starting Checkout.com WooCommerce Plugin Docker Environment..."
echo ""

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker Desktop first."
    exit 1
fi

# Start containers
echo "📦 Starting Docker containers..."
docker-compose up -d

echo ""
echo "⏳ Waiting for WordPress to be ready..."
sleep 10

# Check if containers are running
if docker ps | grep -q checkout-com-wp; then
    echo ""
    echo "✅ Docker environment started successfully!"
    echo ""
    echo "📍 Access points:"
    echo "   WordPress:  http://localhost:8080"
    echo "   phpMyAdmin: http://localhost:8081"
    echo ""
    echo "📝 WordPress Setup:"
    echo "   Database: wordpress"
    echo "   Username: wordpress"
    echo "   Password: wordpress"
    echo ""
    echo "🔍 View logs:"
    echo "   docker-compose logs -f wordpress"
    echo ""
    echo "🛑 Stop containers:"
    echo "   docker-compose down"
else
    echo "❌ Failed to start containers. Check logs:"
    echo "   docker-compose logs"
fi
