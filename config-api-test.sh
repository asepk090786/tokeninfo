#!/bin/bash
# Config Versioning System - API Testing Script
# 
# Gunakan script ini untuk test config versioning endpoints
# Usage: bash config-api-test.sh [command] [arguments]

# Configuration
SERVER="https://token.sman1-pontang.sch.id"
API_VERSION_URL="$SERVER/api/version.json"
API_CONFIG_URL="$SERVER/api/config.json"
API_HEALTH_URL="$SERVER/api/config/health"
API_UPDATE_URL="$SERVER/api/config/update"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
print_header() {
    echo -e "${BLUE}=== $1 ===${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

# Test 1: Check version endpoint (no-cache)
test_version_endpoint() {
    print_header "Test 1: Version Endpoint (GET /api/version.json)"
    
    echo ""
    echo "Request:"
    echo "curl -v '$API_VERSION_URL'"
    echo ""
    
    echo "Response:"
    response=$(curl -s -i "$API_VERSION_URL")
    
    # Extract headers and body
    headers=$(echo "$response" | head -n 20)
    body=$(echo "$response" | tail -n +21)
    
    echo "$headers"
    echo ""
    echo "Body:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
    
    # Verify cache headers
    echo ""
    print_info "Verifying cache headers:"
    if echo "$headers" | grep -q "Cache-Control.*no-cache"; then
        print_success "Cache-Control header is 'no-cache' (correct)"
    else
        print_error "Cache-Control header missing or incorrect"
    fi
    
    if echo "$headers" | grep -q "ETag"; then
        print_success "ETag header present (correct)"
    else
        print_error "ETag header missing"
    fi
    
    echo ""
}

# Test 2: Check config endpoint (cached)
test_config_endpoint() {
    print_header "Test 2: Config Endpoint (GET /api/config.json)"
    
    echo ""
    echo "Request:"
    echo "curl -v '$API_CONFIG_URL'"
    echo ""
    
    echo "Response:"
    response=$(curl -s -i "$API_CONFIG_URL")
    
    headers=$(echo "$response" | head -n 20)
    body=$(echo "$response" | tail -n +21)
    
    echo "$headers"
    echo ""
    echo "Body:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
    
    # Verify cache headers
    echo ""
    print_info "Verifying cache headers:"
    if echo "$headers" | grep -q "Cache-Control.*max-age=3153.*"; then
        print_success "Cache-Control header has max-age (correct)"
    else
        print_error "Cache-Control header missing or incorrect"
    fi
    
    if echo "$headers" | grep -q "immutable"; then
        print_success "immutable flag present (correct)"
    else
        print_error "immutable flag missing (less important)"
    fi
    
    echo ""
}

# Test 3: Check versioned config endpoint
test_versioned_config_endpoint() {
    print_header "Test 3: Versioned Config Endpoint (GET /api/config.json?v=X.X.X)"
    
    # Get current version first
    version_response=$(curl -s "$API_VERSION_URL")
    current_version=$(echo "$version_response" | jq -r '.config_version' 2>/dev/null)
    
    if [ -z "$current_version" ] || [ "$current_version" = "null" ]; then
        print_error "Could not parse version from version.json"
        return
    fi
    
    versioned_url="$API_CONFIG_URL?v=$current_version"
    
    echo ""
    echo "Current version: $current_version"
    echo "Versioned URL: $versioned_url"
    echo ""
    
    echo "Request:"
    echo "curl -i '$versioned_url'"
    echo ""
    
    echo "Response:"
    response=$(curl -s -i "$versioned_url")
    
    headers=$(echo "$response" | head -n 20)
    body=$(echo "$response" | tail -n +21)
    
    echo "$headers"
    echo ""
    echo "Body:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
    
    echo ""
}

# Test 4: Health check endpoint
test_health_endpoint() {
    print_header "Test 4: Health Endpoint (GET /api/config/health)"
    
    echo ""
    echo "Request:"
    echo "curl -v '$API_HEALTH_URL'"
    echo ""
    
    echo "Response:"
    response=$(curl -s -i "$API_HEALTH_URL")
    
    headers=$(echo "$response" | head -n 20)
    body=$(echo "$response" | tail -n +21)
    
    echo "$headers"
    echo ""
    echo "Body:"
    echo "$body" | jq . 2>/dev/null || echo "$body"
    
    echo ""
}

# Test 5: Admin update endpoint (requires auth)
test_update_endpoint() {
    print_header "Test 5: Admin Update Endpoint (POST /api/config/update)"
    
    echo ""
    print_info "This endpoint requires Sanctum authentication token"
    echo ""
    
    read -p "Enter auth token (press Enter to skip): " auth_token
    
    if [ -z "$auth_token" ]; then
        print_info "Skipping update test (no auth token provided)"
        return
    fi
    
    # Get current config first
    config_response=$(curl -s "$API_CONFIG_URL")
    
    # Prepare update payload
    new_version="1.0.1"
    payload=$(cat <<EOF
{
  "version": "$new_version",
  "config": $config_response,
  "message": "Configuration updated by test script"
}
EOF
)
    
    echo "Request:"
    echo "curl -X POST '$API_UPDATE_URL' \\"
    echo "  -H 'Authorization: Bearer YOUR_TOKEN' \\"
    echo "  -H 'Content-Type: application/json' \\"
    echo "  -d '{...}'"
    echo ""
    
    echo "Payload:"
    echo "$payload" | jq .
    echo ""
    
    echo "Response:"
    response=$(curl -s -X POST \
        -H "Authorization: Bearer $auth_token" \
        -H "Content-Type: application/json" \
        -d "$payload" \
        "$API_UPDATE_URL")
    
    echo "$response" | jq . 2>/dev/null || echo "$response"
    
    echo ""
}

# Test 6: Performance test (load test)
test_performance() {
    print_header "Test 6: Performance Test (concurrent requests)"
    
    read -p "Enter number of concurrent requests (default: 10): " concurrent
    concurrent=${concurrent:-10}
    
    read -p "Enter test duration in seconds (default: 5): " duration
    duration=${duration:-5}
    
    echo ""
    print_info "Running $concurrent concurrent requests for $duration seconds..."
    print_info "Testing version endpoint (lightweight, should be fast)"
    echo ""
    
    start_time=$(date +%s%N)
    
    # Use GNU parallel if available, otherwise use bash loop
    if command -v parallel &> /dev/null; then
        seq 1 $concurrent | parallel -j $concurrent "curl -s '$API_VERSION_URL' > /dev/null" 2>/dev/null
    else
        for ((i=1; i<=concurrent; i++)); do
            for ((j=1; j<=5; j++)); do
                curl -s "$API_VERSION_URL" > /dev/null &
            done
            wait
        done
    fi
    
    end_time=$(date +%s%N)
    elapsed=$(( (end_time - start_time) / 1000000 )) # Convert to ms
    
    total_requests=$((concurrent * 5))
    avg_time=$((elapsed / total_requests))
    rps=$((total_requests * 1000 / elapsed))
    
    echo ""
    print_success "Total requests: $total_requests"
    print_success "Total time: ${elapsed}ms"
    print_success "Average response time: ${avg_time}ms"
    print_success "Requests per second: $rps"
    echo ""
}

# Test 7: Compare cache headers
test_cache_headers_comparison() {
    print_header "Test 7: Cache Headers Comparison"
    
    echo ""
    echo "Version Endpoint Cache Headers:"
    echo "Response format: curl -i -X OPTIONS '$API_VERSION_URL'"
    curl -s -i -X OPTIONS "$API_VERSION_URL" | head -n 15
    
    echo ""
    echo ""
    echo "Config Endpoint Cache Headers:"
    echo "Response format: curl -i -X OPTIONS '$API_CONFIG_URL'"
    curl -s -i -X OPTIONS "$API_CONFIG_URL" | head -n 15
    
    echo ""
    echo "Summary:"
    print_info "version.json should have: Cache-Control: no-cache"
    print_info "config.json should have: Cache-Control: public, max-age=31536000"
    echo ""
}

# Test 8: JSON validation
test_json_validation() {
    print_header "Test 8: JSON Validation"
    
    echo ""
    print_info "Validating version.json structure..."
    version_response=$(curl -s "$API_VERSION_URL")
    
    # Check required fields
    required_fields=("config_version" "config_url" "last_updated" "timestamp")
    
    for field in "${required_fields[@]}"; do
        if echo "$version_response" | jq -e ".$field" > /dev/null 2>&1; then
            print_success "Field '$field' exists"
        else
            print_error "Field '$field' missing"
        fi
    done
    
    echo ""
    print_info "Validating config.json structure..."
    config_response=$(curl -s "$API_CONFIG_URL")
    
    required_fields=("version" "exambro_page_url" "school_name" "app_name")
    
    for field in "${required_fields[@]}"; do
        if echo "$config_response" | jq -e ".$field" > /dev/null 2>&1; then
            print_success "Field '$field' exists"
        else
            print_error "Field '$field' missing"
        fi
    done
    
    echo ""
}

# Show usage
show_usage() {
    cat <<EOF
Config Versioning System - API Testing Script

Usage: $0 [command]

Commands:
  all              Run all tests
  version          Test version endpoint
  config           Test config endpoint
  versioned        Test versioned config endpoint
  health           Test health endpoint
  update           Test admin update endpoint (requires auth)
  performance      Run performance/load test
  headers          Compare cache headers
  validate         Validate JSON structures
  help             Show this help message

Examples:
  $0 all
  $0 version
  $0 config
  $0 performance

EOF
}

# Main script
case "${1:-help}" in
    all)
        test_version_endpoint
        test_config_endpoint
        test_versioned_config_endpoint
        test_health_endpoint
        test_cache_headers_comparison
        test_json_validation
        ;;
    version)
        test_version_endpoint
        ;;
    config)
        test_config_endpoint
        ;;
    versioned)
        test_versioned_config_endpoint
        ;;
    health)
        test_health_endpoint
        ;;
    update)
        test_update_endpoint
        ;;
    performance)
        test_performance
        ;;
    headers)
        test_cache_headers_comparison
        ;;
    validate)
        test_json_validation
        ;;
    help)
        show_usage
        ;;
    *)
        print_error "Unknown command: $1"
        show_usage
        exit 1
        ;;
esac
