# Config.json - Quick Reference Card

## ✅ Status Checks - Tiga Pilar Utama

### 1. SERVER STATUS ✓
```
Interval: 10 detik
Method: Connectivity check (HTTP GET)
Endpoint: /api/exambro-info
Timeout: 5 detik
Retry: 3x sebelum fail
Critical: ✅ YA - Diperlukan untuk exam

Behavior:
🟢 OK → Lamp hijau "Server siap"
🔴 ERROR → Lamp merah "Server tidak siap"
🟡 CHECKING → Lamp kuning dengan spinner

Cache: 5 menit TTL
Offline Fallback: 10 menit TTL extended
```

### 2. PIN STATUS ✓
```
Interval: 8 detik (paling sering)
Method: API Fetch JSON response
Endpoint: /api/exambro-status/token
Timeout: 5 detik
Retry: 2x
Critical: ✅ YA - Diperlukan untuk exam

Behavior:
🟢 TIDAK AKTIF → Lamp hijau "PIN tidak diperlukan"
🟠 AKTIF → Lamp oranye "PIN Aktif" + notification

Action on Enable:
- Show notification: "PIN keamanan AKTIF"
- Display PIN code jika needed
- Lock exam access tanpa PIN

Action on Disable:
- Show notification: "PIN tidak aktif"
- Allow free exam access
```

### 3. WARNING STATUS ✓
```
Interval: 12 detik
Method: API Fetch JSON response
Endpoint: /api/exambro-status/peringatan
Timeout: 5 detik
Retry: 2x
Critical: ❌ NO - Informational only

Behavior:
🟢 NO WARNING → Lamp hijau "Tidak ada peringatan"
🟠 WARNING → Lamp oranye "⚠️ Peringatan Aktif"

Action on Warning:
- Play audio: "warning.mp3" (ada ${warning_audio_enabled})
- Show notification: "Ada peringatan dari server"
- Display message on-screen
- Blink lamp indicator

Action on Clear:
- Clear notification
- Reset lamp to green
```

---

## Application Config Flow

### Startup (App Start)
```
Load /assets/config.json
  │
  ├─ Read: version = "1.0.0"
  ├─ Read: exambro_page_url = "https://..."
  ├─ Read: school_name = "SMA Negeri 1 Pontang"
  ├─ Read: api_endpoints (status_token, status_warning, etc)
  ├─ Read: status_checks config
  └─ Initialize status checkers
       ├─ Server Status Timer: start 10s cycle
       ├─ PIN Status Timer: start 8s cycle (offset 2s)
       └─ Warning Status Timer: start 12s cycle (offset 4s)
```

### First Status Check (Immediate)
```
Startup check (timeout 8 seconds)
  │
  ├─ Server Status
  │   └─ HTTP GET /api/exambro-info
  │       ├─ Success → lamp 🟢 GREEN
  │       └─ Timeout/Error → lamp 🔴 RED + use cache
  │
  ├─ PIN Status
  │   └─ HTTP GET /api/exambro-status/token
  │       ├─ Response: {pin_required: true}
  │       └─ lamp 🟠 ORANGE + notify
  │
  └─ Warning Status
      └─ HTTP GET /api/exambro-status/peringatan
          ├─ Response: {warning: "Exam dimulai 10 menit lagi"}
          └─ lamp 🟠 + audio + notification
```

### Periodic Polling (Every 8-12 Seconds)
```
Every 8s:  Check PIN Status
Every 10s: Check Server Status
Every 12s: Check Warning Status

Actions per status:
├─ PIN enabled? → Update lamp + notify
├─ PIN disabled? → Update lamp + notify
├─ Warning active? → Update lamp + audio + notify
├─ Warning cleared? → Update lamp + clear notification
└─ Server down? → Show error, use cache
```

### Error Handling Flow
```
Network Error (timeout, connection refused)
  │
  ├─ If CRITICAL (server, PIN)
  │   └─ Use cached status (5-10 min TTL)
  │   └─ Retry after 10 seconds (exponential backoff)
  │
  └─ If NOT CRITICAL (warning)
      └─ Use cached status
      └─ Retry per normal interval (12s)
```

---

## Config JSON Key Sections

### ✅ api_endpoints (URL Configuration)
```json
"api_endpoints": {
  "exambro_info": "https://server/api/exambro-info",
  "status_token": "https://server/api/exambro-status/token",
  "status_warning": "https://server/api/exambro-status/peringatan",
  "config_download": "https://server/api/exambro-config/download"
}
```

### ✅ status_checks (Per-Status Configuration)
```json
"status_checks": {
  "server_status": { interval: 10 },
  "status_pin": { interval: 8 },
  "status_peringatan": { interval: 12 }
}
```

### ✅ network_config (Timeout & Retry)
```json
"network_config": {
  "connect_timeout_seconds": 5,
  "read_timeout_seconds": 5,
  "retry_count": { server: 3, pin: 2, warning: 2 }
}
```

### ✅ cache_policy (Offline Support)
```json
"cache_policy": {
  "status_cache_duration_seconds": 300,      // 5 min
  "cache_fallback_ttl_seconds": 600,         // 10 min
  "cache_on_error": true
}
```

---

## Server List → Removed ✗

**OLD**:
```json
"servers": {
  "utama": { "url": "https://exam...", "name": "Server Utama" },
  "backup1": { "url": "", "enabled": false }
}
```

**WHY REMOVED**:
- Single base URL (`exambro_page_url`) is sufficient
- Server health checked via `/api/exambro-info`
- Failover handled by network retry logic
- Cleaner config, less maintenance

---

## Status API Response Examples

### Server Status Response
```json
{
  "status": "ok",
  "server_version": "1.0.0",
  "timestamp": "2026-04-06T10:00:00Z"
}
```

### PIN Status Response
```json
{
  "pin_required": true,
  "pin_code": "123456",           // If needed
  "message": "PIN dirakit untuk ujian hari ini"
}
```

### Warning Status Response
```json
{
  "has_warning": true,
  "warning": "Ujian dimulai dalam 10 menit",
  "severity": "info",             // info, warning, critical
  "action": "show_notification"
}
```

---

## Typical Scenarios

### Scenario A: Normal Exam Day
```
Status       │ Interval │ Typical Response
─────────────┼──────────┼──────────────────────────────
Server       │ 10s      │ 🟢 {status: "ok"}
PIN          │ 8s       │ 🟢 {pin_required: false}
Warning      │ 12s      │ 🟢 {has_warning: false}

Result: All lamps GREEN, exam can start
```

### Scenario B: PIN Enabled Mid-Exam
```
10:00:00 - Admin enables PIN requirement
10:00:08 - APK checks PIN status (next 8s interval)
         → Response: {pin_required: true}
         → Lamp changes to 🟠 ORANGE
         → Notification: "PIN keamanan AKTIF"
         → APK locks further access without PIN

Result: ~8 seconds to detect and enforce
```

### Scenario C: Warning Issued
```
10:00:00 - Admin issues warning
10:00:12 - APK checks warning status (next 12s interval)
         → Response: {has_warning: true, warning: "..."}
         → Lamp changes to 🟠 ORANGE
         → Audio plays: beep beep
         → Notification pops up with message

Result: ~12 seconds for user to see warning
```

### Scenario D: Server Down (Graceful Fallback)
```
10:00:00 - Server goes down
10:00:10 - APK checks (next interval)
         → Connection error
         → Use cached status (cache still valid)
         → Lamp still 🟢 (shows cached state)
         → Retry in 10s

10:05:00 - Cache TTL expires (5 minutes)
         → HTTP error persists
         → Use extended fallback cache (10 min TTL)
         → Lamp shows 🔴 ERROR
         → User sees "Server tidak siap"

Result: 5 minutes of graceful degradation
```

---

## Admin Configuration Changes (Quick Tips)

### Make Server Check Faster
```json
"polling_strategy": {
  "server_status": {
    "interval_seconds": 5        // Change from 10
  }
}
```

### Disable Warning Audio
```json
"status_peringatan": {
  "audio_alert_enabled": false   // Toggle
}
```

### Check Only Server, Hide PIN and Warning
```json
"ui_config": {
  "status_display": {
    "show_server_status": true,
    "show_pin_status": false,
    "show_warning_status": false
  }
}
```

### Increase Cache Validity (Offline Mode)
```json
"cache_policy": {
  "status_cache_duration_seconds": 600,  // 10 min (2x)
  "cache_fallback_ttl_seconds": 1200     // 20 min (2x)
}
```

---

## Files & Locations

| Location | Purpose |
|----------|---------|
| `/app/src/main/assets/config.json` | Bundled config (fallback) |
| `/public/api/config.json` | Server config (downloaded via VersionChecker) |

Both files are **IDENTICAL** and synced.

---

## Version History

| Version | Status | Changes |
|---------|--------|---------|
| 1.0.0 | Latest | Added status_checks object, removed servers list, added detailed network config |

---

## Deployment Checklist

- [ ] ✅ config.json updated with status_checks
- [ ] ✅ Servers list removed
- [ ] ✅ api_endpoints configured with correct URLs
- [ ] ✅ Bundled and public API configs synced
- [ ] ✅ Tested status checks with APK
- [ ] ✅ Verified lamp indicators work
- [ ] ✅ Verified notifications display
- [ ] ✅ Tested offline fallback
- [ ] ✅ Monitor server load (should be minimal)

---

**Updated**: 2026-04-06 v1.0.0  
**Status**: ✅ Production Ready  
**Next**: Test with real APK, monitor status checks
