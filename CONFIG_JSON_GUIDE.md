# Neo Exam - config.json Structure & Status Logic

**Date**: 2026-04-06  
**Version**: 1.0.0  
**Purpose**: Complete configuration for Neo Exam APK with integrated status checking

---

## Overview

File `config.json` mengontrol:
1. ✅ URL endpoint untuk fetch status server, PIN, dan peringatan
2. ✅ Interval dan timeout untuk setiap status check
3. ✅ Behavior saat error, timeout, dan offline
4. ✅ UI indicator tampilan dan label
5. ✅ Caching strategy
6. ✅ Network retry logic

---

## File Locations

| Location | Purpose | Usage |
|----------|---------|-------|
| `/app/src/main/assets/config.json` | Bundled fallback (built-in) | Load saat first startup |
| `/public/api/config.json` | Server config (via versioning) | Download via VersionChecker |
| `/app/files/config.json` | Runtime cache | Used after download |

---

## Config.json Complete Structure

### 1. Core Configuration

```json
{
  "version": "1.0.0",
  "exambro_page_url": "https://token.sman1-pontang.sch.id/exambro",
  "school_name": "SMA Negeri 1 Pontang",
  "app_name": "CBT Garuda",
  "warning_audio_enabled": true,
  "exam_rotation_mode": "auto"
}
```

| Field | Type | Purpose | Notes |
|-------|------|---------|-------|
| `version` | String | Config version (semantic) | Auto-incremented by web admin |
| `exambro_page_url` | URL | Main exam page | Base URL untuk semua API calls |
| `school_name` | String | School display name | Shown di home screen |
| `app_name` | String | App title | Shown di home screen |
| `warning_audio_enabled` | Boolean | Enable audio alert untuk peringatan | Play sound saat warning |
| `exam_rotation_mode` | String | Screen rotation mode | `auto`, `portrait`, `landscape` |

### 2. API Endpoints Configuration

```json
"api_endpoints": {
  "exambro_info": "https://token.sman1-pontang.sch.id/api/exambro-info",
  "status_token": "https://token.sman1-pontang.sch.id/api/exambro-status/token",
  "status_warning": "https://token.sman1-pontang.sch.id/api/exambro-status/peringatan",
  "config_download": "https://token.sman1-pontang.sch.id/api/exambro-config/download"
}
```

**Endpoints**:
- `exambro_info` - Server information & health check
- `status_token` - PIN/token requirement status
- `status_warning` - Warning/notification from server
- `config_download` - Configuration download endpoint

**Built-in from exambro_page_url**:
- These URLs built from `exambro_page_url` base + path
- Can be overridden here for flexibility

### 3. Status Checks Configuration

#### 3.1 Server Status
```json
"status_checks": {
  "server_status": {
    "enabled": true,
    "label": "Status Server",
    "description": "Verifikasi server exam dapat diakses",
    "check_interval_seconds": 10,
    "timeout_seconds": 5,
    "retry_count": 3,
    "check_method": "connectivity",
    "fallback_on_error": "use_cached_status",
    "ui_indicator": {
      "show_lamp": true,
      "show_label": true,
      "label_loading": "Memeriksa server...",
      "label_ok": "Server siap",
      "label_error": "Server tidak siap"
    }
  },
```

**Server Status Logic**:
- **Check Method**: HTTP connectivity test (quick ping)
- **Interval**: 10 seconds (aggressive for critical status)
- **Timeout**: 5 seconds
- **Retry**: 3 attempts sebelum consider failed
- **Fallback**: Use cached status jika error
- **Critical**: Required untuk exam access

#### 3.2 Status PIN/Token
```json
  "status_pin": {
    "enabled": true,
    "label": "Status PIN/Token",
    "description": "PIN keamanan untuk exam access",
    "fetch_from_api": true,
    "api_endpoint": "status_token",
    "check_interval_seconds": 8,
    "timeout_seconds": 5,
    "retry_count": 2,
    "on_enable": {
      "action": "show_notification",
      "message": "PIN keamanan AKTIF - Perlu PIN untuk akses exam"
    },
    "on_disable": {
      "action": "show_notification",
      "message": "PIN keamanan tidak aktif"
    },
    "ui_indicator": {
      "show_lamp": true,
      "show_label": true,
      "show_value": true,
      "label_loading": "Memeriksa PIN...",
      "label_ok": "PIN Aktif",
      "label_error": "PIN tidak tersedia"
    }
  },
```

**PIN Status Logic**:
- **Source**: API `/api/exambro-status/token`
- **Interval**: 8 seconds (frequent check for security)
- **Behavior**: 
  - If ENABLED: Show "PIN Aktif" + notification
  - If DISABLED: Show "PIN Tidak Aktif" + notification
- **Critical**: Required untuk exam access
- **Show Value**: Display actual PIN code jika needed

#### 3.3 Status Peringatan

```json
  "status_peringatan": {
    "enabled": true,
    "label": "Status Peringatan",
    "description": "Warning/notifikasi untuk peserta ujian",
    "fetch_from_api": true,
    "api_endpoint": "status_warning",
    "check_interval_seconds": 12,
    "timeout_seconds": 5,
    "retry_count": 2,
    "audio_alert_enabled": true,
    "on_warning": {
      "action": "play_audio_and_notify",
      "audio_file": "warning",
      "notification": "Ada peringatan dari server - Perhatikan pesan di layar",
      "show_on_screen": true
    },
    "on_clear": {
      "action": "clear_notification",
      "message": "Peringatan telah dihapus"
    },
    "ui_indicator": {
      "show_lamp": true,
      "show_label": true,
      "show_value": true,
      "label_loading": "Memeriksa peringatan...",
      "label_ok": "Tidak ada peringatan",
      "label_warning": "⚠️ Peringatan Aktif",
      "lamp_color_ok": "#00AA00",
      "lamp_color_warning": "#FF6600"
    }
  }
}
```

**Warning Status Logic**:
- **Source**: API `/api/exambro-status/peringatan`
- **Interval**: 12 seconds (less critical, can be longer)
- **Behavior**:
  - If WARNING: Play audio + show notification + lamp blink
  - If OK: Clear notification + lamp steady green
- **Audio**: Play warning sound jika enabled
- **Not Critical**: Exam dapat continue bahkan jika error checking

### 4. Status Behavior Configuration

```json
"status_behavior": {
  "on_startup": {
    "check_immediately": true,
    "show_loading_indicator": true,
    "timeout_before_fallback_seconds": 8
  },
  "on_connect_error": {
    "action": "use_cached_status",
    "show_error_indicator": false,
    "retry_automatically": true,
    "retry_interval_seconds": 10
  },
  "on_parse_error": {
    "action": "log_error_and_retry",
    "show_error_to_user": false,
    "retry_immediately": true
  },
```

**Startup Behavior**:
- Check status immediately (don't wait for interval)
- Show loading indicators
- Timeout after 8 seconds

**Connection Error**:
- Use cached status jika dapat
- Don't show error to user (use fallback)
- Retry automatically setiap 10 detik

**Parse Error**:
- Log error untuk debugging
- Retry immediately (jangan wait interval)

### 5. Polling Strategy (Per Status Type)

```json
  "polling_strategy": {
    "server_status": {
      "interval_seconds": 10,
      "method": "connectivity_check",
      "required_for_exam": true
    },
    "status_pin": {
      "interval_seconds": 8,
      "method": "api_fetch",
      "required_for_exam": true,
      "critical": true
    },
    "status_peringatan": {
      "interval_seconds": 12,
      "method": "api_fetch",
      "required_for_exam": false,
      "critical": false
    }
  }
}
```

| Status | Interval | Method | Required | Critical |
|--------|----------|--------|----------|----------|
| Server | 10 sec | Connectivity | ✅ Yes | ✅ Yes |
| PIN | 8 sec | API Fetch | ✅ Yes | ✅ Yes |
| Warning | 12 sec | API Fetch | ❌ No | ❌ No |

---

## Application Logic Flow

### Startup Sequence

```
App Start
  ↓
Load bundled config.json (or cached version)
  ↓
Start status checks (server, PIN, warning)
  ↓
For each status:
  - Fetch immediately (timeout 8s)
  - Render loading indicator
  - Show result (lamp + label)
  ↓
Start periodic polling:
  - Server status: every 10s
  - PIN status: every 8s
  - Warning status: every 12s
  ↓
Update UI lamps & labels setiap perubahan
```

### Status Check Flow

```
Check scheduled (e.g., PIN every 8s)
  ↓
Timeout handler attached (5s timeout)
  ↓
Fetch from API endpoint
  ↓
Parse JSON response
  ↓
Compare with cached value
  ↓
If DIFFERENT:
  → Update cache
  → Update UI indicator (lamp + label)
  → Trigger action (notification, audio, etc)
  ↓
If ERROR:
  → Use cached status (if available)
  → Log error
  → Retry after 10s (if critical)
  → Schedule next check per interval
```

### Error Recovery

```
Network Error (HTTP 0, timeout, etc)
  ↓
  ├─ If CRITICAL (server, PIN):
  │   └─ Use cache + retry in 10s
  │
  └─ If NOT CRITICAL (warning):
      └─ Use cache + retry per normal interval (12s)

Parse Error (invalid JSON)
  ↓
  ├─ Log error
  ├─ Use cache
  └─ Retry immediately

All endpoints unavailable
  ↓
  └─ Continue with cached status (fallback mode)
```

---

## Cache Policy

```json
"cache_policy": {
  "status_cache_duration_seconds": 300,
  "cache_on_error": true,
  "cache_fallback_ttl_seconds": 600,
  "cache_storage": "shared_preferences"
}
```

| Setting | Value | Purpose |
|---------|-------|---------|
| `status_cache_duration_seconds` | 300 (5 min) | Normal cache validity |
| `cache_on_error` | true | Keep cache even after error |
| `cache_fallback_ttl_seconds` | 600 (10 min) | Extended TTL saat offline |
| `cache_storage` | `shared_preferences` | Use Android SharedPreferences |

---

## Network Configuration

```json
"network_config": {
  "connect_timeout_seconds": 5,
  "read_timeout_seconds": 5,
  "retry_count": {
    "server_status": 3,
    "status_pin": 2,
    "status_peringatan": 2
  },
  "retry_interval_seconds": 2,
  "backoff_multiplier": 1.5,
  "allow_unencrypted": false,
  "verify_ssl_certificates": true
}
```

| Setting | Value | Purpose |
|---------|-------|---------|
| `connect_timeout_seconds` | 5 | HTTP connection timeout |
| `read_timeout_seconds` | 5 | HTTP read timeout |
| Retry count | 2-3 retries | Depends on criticality |
| Backoff multiplier | 1.5x | Exponential backoff: 2s, 3s, 4.5s |
| SSL verify | true | Require valid certificates |

---

## UI Configuration

```json
"ui_config": {
  "status_display": {
    "show_server_status": true,
    "show_pin_status": true,
    "show_warning_status": true
  },
  "indicator_style": {
    "type": "lamp",
    "size": "medium",
    "animation_on_change": true
  },
  "status_labels": {
    "loading": { "color": "#FFCC00", "icon": "spinner" },
    "ok": { "color": "#00AA00", "icon": "check" },
    "error": { "color": "#FF0000", "icon": "alert" },
    "warning": { "color": "#FF6600", "icon": "warning" }
  },
  "refresh_display_interval_seconds": 1
}
```

**Color Codes**:
- 🟡 Loading: `#FFCC00` (yellow)
- 🟢 OK: `#00AA00` (green)
- 🔴 Error: `#FF0000` (red)
- 🟠 Warning: `#FF6600` (orange)

---

## Update Scenario Examples

### Scenario 1: Server Down

**Initial State**:
- Server Status: 🟢 OK (cached)
- PIN Status: 🟢 Active
- Warning Status: 🟢 OK

**Server goes down at 10:00:00**:
```
10:00:00 - Next server check (interval 10s)
          → HTTP error (connection refused)
          → Use cached "OK" (still shows 🟢)
          → Retry in 10s (exponential backoff)

10:00:10 - Retry
          → HTTP error again
          → Still showing 🟢 (cache valid for 5 min)
          → Retry in 15s

10:05:00 - Cache expires (5 min TTL)
          → HTTP error
          → Use fallback cache (10 min TTL)
          → Show 🔴 Error lamp now

User Impact: ~5 minutes before UI shows error
```

### Scenario 2: PIN Suddenly Required

**Initial State**:
- PIN Status: 🟢 Not Required

**Admin enables PIN at 10:00:00**:
```
10:00:00 - Server updates PIN requirement

10:00:08 - Next PIN check (interval 8s)
          → API returns: PIN required = true
          → Cache updated
          → UI lamp changes to 🟠 Active PIN
          → Notification: "PIN keamanan AKTIF"
          → APK user sees change immediately

User Impact: ~8 seconds latency
```

### Scenario 3: Warning Issued

**Initial State**:
- Warning: 🟢 No warnings

**Admin sends warning at 10:00:00**:
```
10:00:00 - Server updates warning

10:00:12 - Next warning check (interval 12s)
          → API returns: warning message
          → Cache updated
          → UI lamp changes to 🟠 Active
          → Audio plays: "warning.mp3"
          → Notification: "Ada peringatan dari server"
          → On-screen alert with message

User Impact: ~12 seconds latency + audio alert
```

---

## Admin Configuration Changes

### Change Interval

```json
"polling_strategy": {
  "server_status": {
    "interval_seconds": 10        // Change to 5 for faster
  }
}
```

**Effect**: Server status checked every 5s instead of 10s (use more battery)

### Disable Warning Audio

```json
"status_peringatan": {
  "audio_alert_enabled": false    // Disable sound
}
```

**Effect**: Warning shown but no sound played

### Enable Server Display Only

```json
"ui_config": {
  "status_display": {
    "show_server_status": true,   // Enable
    "show_pin_status": false,     // Disable
    "show_warning_status": false  // Disable
  }
}
```

**Effect**: Only server status lamp shown, PIN and warning hidden

---

## Debugging config.json

### Check Current Config (Android Device)

```bash
# Via ADB
adb shell sqlite3 /data/data/com.exambrowser.app/shared_prefs/exambro_config.xml

# Via logcat
adb logcat | grep -E "status|config"
```

### View Config Content

```bash
# Check bundled config
unzip -p Neo_Exam.apk assets/config.json | jq .

# Check cached config on device
adb shell cat /data/data/com.exambrowser.app/files/config.json
```

### Validate JSON

```bash
python3 -m json.tool config.json > /dev/null && echo "Valid JSON"
```

---

## Migration Guide

### From Old Config (List Servers) to New Config (Status Checks)

**Old Structure**:
```json
{
  "servers": {
    "utama": { "url": "...", "name": "..." }
  }
}
```

**New Structure**:
```json
{
  "api_endpoints": {
    "status_token": "https://token.sman1-pontang.sch.id/api/...",
    "status_warning": "https://token.sman1-pontang.sch.id/api/..."
  },
  "status_checks": {
    "server_status": { ... },
    "status_pin": { ... },
    "status_peringatan": { ... }
  }
}
```

**Impact**:
- ✅ Server list removed (now single base URL)
- ✅ Status checks become definitive source
- ✅ Intervals configurable per status type
- ✅ Better error handling & caching

---

## Troubleshooting

### Problem: Status not updating

**Check**:
1. Is `enabled: true` in status_checks?
2. Is `check_interval_seconds` reasonable?
3. Is API endpoint URL correct?
4. Check logcat for errors

### Problem: UI lamp stuck on status

**Cause**: Cache TTL exceeded

**Fix**:
1. Clear cache: `ApiConfigStore.clearAllCache(context)`
2. Lower TTL: `cache_policy.status_cache_duration_seconds`
3. Force manual refresh

### Problem: High battery drain (checking too often)

**Fix**:
1. Increase intervals: `interval_seconds` per status
2. Disable non-critical checks (warning status)
3. Increase cache TTL: `cache_policy`

---

## Files Reference

| File | Purpose |
|------|---------|
| `app/src/main/assets/config.json` | Bundled default config |
| `public/api/config.json` | Server-side config (downloaded) |
| `app/src/main/.../ApiConfigStore.java` | Config loading & caching |
| `app/src/main/.../VersionChecker.java` | Periodic config checks |
| `CONFIG_VERSIONING_GUIDE.md` | Detailed versioning docs |

---

**Created**: 2026-04-06  
**Status**: Production Ready  
**Next**: Deploy and monitor status checks
