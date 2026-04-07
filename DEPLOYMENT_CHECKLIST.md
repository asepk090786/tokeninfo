# Config Versioning System - Implementation Checklist

## ✅ Completed Components

### Web Server Side (Laravel)
- [x] Create `/public/api/version.json` - Version metadata file
- [x] Create `/public/api/config.json` - Configuration settings file
- [x] Create `ConfigApiController.php` - API endpoints:
  - `getVersion()` - GET /api/version.json (no-cache)
  - `getConfig()` - GET /api/config.json (aggressively cached)
  - `updateConfig()` - POST /api/config/update (admin update)
  - `health()` - GET /api/config/health (health check)
- [x] Add routes in `routes/web.php`:
  - GET /api/version.json
  - GET /api/config.json
  - GET /api/config/health
  - POST /api/config/update (auth required)

### APK Side (Android)
- [x] Create `VersionChecker.java` - Periodic version checking class:
  - Fetches version.json every 60-90 seconds
  - Compares with local version
  - Downloads config.json if changed
  - Handles errors gracefully
  - Manages background thread lifecycle
- [x] Enhance `ApiConfigStore.java` - Add cache management:
  - `updateLastConfigUpdateTime()`
  - `getLastConfigUpdateTime()`
  - `isCacheValid()`
  - `setCacheValidityHours()`
  - `clearAllCache()`
- [x] Update `MainActivity.java` - Integration:
  - `setupVersionChecker()` - Initialize with callback
  - `onCreate()` - Initialize checker
  - `onResume()` - Start periodic checks
  - `onPause()` - Stop checks (battery saving)
  - `onDestroy()` - Cleanup

## 🔧 Configuration Files

### version.json Location & Structure
```
File: /www/wwwroot/token.sman1-pontang.sch.id/public/api/version.json
Size: ~200 bytes
Update Frequency: When admin updates config
Cache: NO-CACHE (always fresh)

Key Fields:
- config_version: "1.0.0" (semantic version)
- config_url_versioned: "...config.json?v=1.0.0"
- last_updated: ISO8601 timestamp
- min_app_version: "1.0.0"
```

### config.json Location & Structure
```
File: /www/wwwroot/token.sman1-pontang.sch.id/public/api/config.json
Size: ~1-2 KB
Update Frequency: When version.json changes
Cache: AGGRESSIVE (1 year with immutable URL)

Key Fields:
- version: "1.0.0"
- exambro_page_url: Main exam configuration URL
- school_name: Display name
- app_name: App title
- warning_audio_enabled: Boolean
- exam_rotation_mode: "auto" | "portrait" | "landscape"
- data: Object with custom fields
```

## 🚀 Deployment Steps

### Step 1: Web Server Deployment
```bash
1. Copy ConfigApiController.php to app/Http/Controllers/
2. Update routes/web.php with new endpoints
3. Ensure /public/api/ directory exists and writable
4. Create /public/api/version.json with initial version
5. Create /public/api/config.json with initial config
6. Test endpoints:
   curl https://token.sman1-pontang.sch.id/api/version.json
   curl https://token.sman1-pontang.sch.id/api/config.json
   curl https://token.sman1-pontang.sch.id/api/config/health
```

### Step 2: APK Source Code Updates
```bash
1. Copy VersionChecker.java to app/src/main/java/com/exambrowser/app/
2. Modify ApiConfigStore.java:
   - Add cache management constants
   - Add cache management methods at end of class
3. Modify MainActivity.java:
   - Add VersionChecker member variable
   - Add setupVersionChecker() method
   - Update onCreate(), onResume(), onPause(), onDestroy()
4. Verify no compilation errors:
   Build → Make Project
   All files should compile without errors
```

### Step 3: APK Compilation & Testing
```bash
1. Rebuild APK:
   ./gradlew clean buildDebug
2. Install test APK:
   adb install -r app/build/outputs/apk/debug/app-debug.apk
3. Monitor logcat for version checker:
   adb logcat | grep VersionChecker
4. Verify periodic checks (should see every 60-90s):
   "D VersionChecker: Next check scheduled in X seconds"
5. Test config update:
   - Update version.json (increment version number)
   - Wait for next check cycle
   - Verify "I VersionChecker: New version detected"
   - Verify "I ExamStatus: Config updated to version"
```

### Step 4: CDN Configuration (Optional)
```bash
If using CDN like Cloudflare:

1. Login to CDN panel
2. Add cache rules:
   - /api/version.json:
     * Cache Level: Bypass OR age 0
     * Browser Cache: Respect Origin
   
   - /api/config.json:
     * Cache Level: Cache Everything
     * Browser Cache TTL: 1 year
     * Edge TTL: 1 year
     * Immutable: Yes (if option exists)

3. Verify cache headers:
   curl -I https://token.sman1-pontang.sch.id/api/config.json?v=1.0.0
   Look for: Cache-Control: public, max-age=31536000
```

## 📊 Verification Commands

### Check Web Endpoints
```bash
# Version endpoint (should NOT be cached)
curl -v https://token.sman1-pontang.sch.id/api/version.json
# Look for: Cache-Control: no-cache

# Config endpoint (should be cached)
curl -v https://token.sman1-pontang.sch.id/api/config.json
# Look for: Cache-Control: public, max-age=31536000

# Health endpoint
curl https://token.sman1-pontang.sch.id/api/config/health
```

### Check APK LogCat (Android Device)
```bash
# Connect device and run:
adb logcat | grep -E "VersionChecker|ExamStatus"

# Should see:
# I VersionChecker: Version checker started, interval: 60-90 seconds
# D VersionChecker: Next check scheduled in XX seconds
# D VersionChecker: Remote version: 1.0.0, Local version: 1.0.0
```

### Manual APK Configuration Check
```bash
adb shell
sqlite3 /data/data/com.exambrowser.app/shared_prefs/exambro_config.xml
.dump
# Look for:
# <string name="exam_page_url">...</string>
# <string name="school_name">...</string>
# <long name="last_config_update_time">...</long>
```

## ⚠️ Important Notes

### Version Number Format
- Use semantic versioning: MAJOR.MINOR.PATCH
- Examples: 1.0.0, 1.0.1, 1.1.0, 2.0.0
- Increment PATCH for small fixes
- Increment MINOR for new features
- Increment MAJOR for breaking changes

### When to Update Config
- ✅ School name changes
- ✅ Exam page URL changes
- ✅ Support contact info changes
- ✅ Feature flags / settings changes
- ❌ Do NOT update just to test (use checkNow() on APK directly)

### Monitoring Checklist
- [ ] Monitor server CPU during config update
- [ ] Monitor network bandwidth (should be < 2MB peak)
- [ ] Check APK logcat for "Version check error"
- [ ] Verify config propagates within 90 seconds
- [ ] Test with 10 devices first, then 100, then production

### Common Issues & Fixes

**Issue**: APK not detecting config updates
- **Cause**: VersionChecker not started (happen in onResume)
- **Fix**: Verify onResume() calls versionChecker.start()
- **Test**: Check logcat for "Version checker started"

**Issue**: Server returning 404 for config.json
- **Cause**: File not in /public/api/ directory
- **Fix**: Create file with: `touch /public/api/config.json`
- **Test**: curl to endpoint should return JSON

**Issue**: High server load / excessive requests
- **Cause**: Interval too short or devices not staggered
- **Fix**: Check CHECK_INTERVAL_* constants in VersionChecker
- **Default**: Already 60-90s random (should be fine)

**Issue**: APK not updating even after web update
- **Cause**: Cached config used, version check timeout
- **Fix**: 
  1. Check network connectivity
  2. Force manual check: versionChecker.checkNow()
  3. Clear cached config: ApiConfigStore.clearAllCache()
  4. Wait for next periodic check

## 🎯 Testing Scenarios

### Scenario 1: Basic Version Check
```
1. Start APK in emulator
2. Monitor logcat
3. Wait 60-90 seconds
4. Should see: "D VersionChecker: Remote version: 1.0.0, Local version: 1.0.0"
5. Should see: "D VersionChecker: Version tidak berubah, no update needed"
```

### Scenario 2: Config Update Detection
```
1. Start APK
2. From web admin, update config (increment version)
3. Wait for next check cycle (max 90 seconds)
4. Should see: "I VersionChecker: New version detected"
5. Should see: "I ExamStatus: Config updated to version X.X.X"
6. UI should refresh automatically
```

### Scenario 3: Network Failure Recovery
```
1. Start APK
2. Disconnect WiFi/disable cellular
3. Wait for version check (should timeout)
4. Should see: "E VersionChecker: IOException / Network error"
5. APK should continue using cached config
6. Reconnect network
7. Next check should succeed
```

### Scenario 4: Mass Concurrent Access (Load Test)
```
1. Start 50+ APK (or 50 test threads)
2. All devices check version.json simultaneously
3. Stagger should prevent thundering herd
4. Monitor server CPU (should < 30%)
5. Monitor network (should < 10 Mbps)
```

## 📝 File Reference

| File | Purpose | Status |
|------|---------|--------|
| `/public/api/version.json` | Version metadata | ✅ Ready |
| `/public/api/config.json` | Config values | ✅ Ready |
| `app/Http/Controllers/ConfigApiController.php` | API endpoints | ✅ Ready |
| `routes/web.php` | Route registration | ✅ Updated |
| `app/.../VersionChecker.java` | Periodic checker | ✅ Ready |
| `app/.../ApiConfigStore.java` | Cache management | ✅ Updated |
| `app/.../MainActivity.java` | Integration | ✅ Updated |

## 🔐 Security Notes

- Config endpoint is PUBLIC (no auth required) for APK to fetch
- Only version.json endpoint public for frequent access
- config.json URL includes version (immutable, safe for CDN)
- Admin update endpoint protected with Sanctum auth
- Consider firewall rules: Allow /api/version.json globally
- Consider DDoS protection: These endpoints may be hit frequently

## 📚 Related Documentation

See `CONFIG_VERSIONING_GUIDE.md` for:
- Detailed architecture and design
- Full API endpoint documentation
- Load test scenarios with metrics
- Troubleshooting guide
- Monitoring and debugging instructions

---

**Last Updated**: 2026-04-06  
**Status**: Production Ready  
**Next Step**: Deploy to production server
