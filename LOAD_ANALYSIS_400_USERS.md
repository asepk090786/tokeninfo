# Load Analysis: 400 Siswa Dengan APK Config System

**Tanggal**: April 6, 2026  
**Skenario**: 400 siswa simultaneous dengan APK config-driven architecture

---

## 1. Request Pattern Per Siswa (Per Menit)

### Startup Phase (1-5 menit)
Ketika APK pertama kali aplikasi start atau di-open:

```
1. GET /api/version.json             [~200-300 bytes]    → 1 request
2. GET /api/config.json              [~8-10 KB]          → 1 request 
3. GET /api/exambro-info             [~1-2 KB]           → 1 request
4. GET /exambro                      [HTML page ~50 KB]  → 1 request
   └─ (potentially multiple sub-resources)
```

**Startup Total**: ~4-5 HTTP requests per siswa dalam 5 menit = ~0.8-1 req/min per siswa

---

### Steady State Phase (During Exam, 30-120 menit ujian berlangsung)

**Status Check Loop** (Background):
```
- Token Status   /api/exambro-status/token      → Every 8 seconds
- Warning       /api/exambro-status/peringatan  → Every 12 seconds  
- Server Check  /api/exambro-info               → Every 10 seconds
```

**Per Siswa Rate During Exam**:
- Token check:  1 request / 8 sec = 7.5 req/min per siswa
- Warning check: 1 request / 12 sec = 5 req/min per siswa
- Server check: 1 request / 10 sec = 6 req/min per siswa

**Steady State Total**: ~18-19 requests/min per siswa

**Version Check** (Background, less frequent):
- VersionChecker checks every 60-90 sec = ~1-2 req/min per siswa

**Steady State TOTAL**: ~20 req/min per siswa

---

## 2. Load Calculation for 400 Siswa

### Startup Phase (First 5 minutes)
```
Simultaneous users starting up:     ~80-100 siswa (assuming staggered over 5 min)
Requests per startup:               4-5 requests
→ Burst rate:                       ~100 req/sec for 1-2 minutes
```

### Peak Load (During Exam)
```
400 siswa × 20 req/min per siswa = 8,000 requests/minute
                                 = ~133 requests/second (STEADY)
```

**Status Check Spike** (When multiple checks align):
- At t=0, t=8s, t=16s: All token checks fire ~50 requests/sec
- At t=0, t=10s, t=20s: All server checks fire ~67 requests/sec  
- At t=0, t=12s, t=24s: All warning checks fire ~50 requests/sec
- **"Spike window": ~67-100 req/sec during peak overlap**

### Realistic Peak (Accounting for Async Offset)
With Android async scheduling, not all 400 requests fire at exact same millisecond.  
Realistic sustained peak: **100-150 req/sec**

---

## 3. Comparison with Previous Load Tests

**From exam-deployment-handoff.md**:
- ✅ 500 concurrent synthetic homepage tests: 0 failed requests
- ✅ Practical safe zone: ~300-350 simultaneous startup users
- ⚠️ 500 simultaneous: Survivable but near ceiling (performance degradation)

**Current Setup Analysis**:
- VPS Config: 4C/8G (assumed from previous context)
- PHP-FPM: 120 max_children, 24 min_spare, 48 max_spare
- Estimated capacity: ~120-150 concurrent connections

---

## 4. Request Type Breakdown (400 Siswa Steady State)

| Endpoint | Method | Payload | Rate (400 users) | Total/sec |
|----------|--------|---------|------------------|-----------|
| `/api/exambro-status/token` | GET | ~500B | 7.5 req/min | ~50 req/sec |
| `/api/exambro-status/peringatan` | GET | ~500B | 5 req/min | ~33 req/sec |
| `/api/exambro-info` | GET | ~2KB | 6 req/min | ~40 req/sec |
| `/api/version.json` | GET | ~300B | 1-2 req/min | ~7 req/sec |
| `/api/config.json` | GET | ~10KB | 0.2 req/min | ~1 req/sec |
| **TOTAL** | - | - | ~20 req/min | **~131 req/sec** |

---

## 5. Risk Assessment: Can 4C/8G Handle 400?

### ✅ Good Signs:
1. **Request rate is manageable**: 131 req/sec sustained is well within most PHP-FPM capacity
   - PHP-FPM can handle 300-500 req/sec on 4C/8G with proper tuning
2. **Payload sizes are small**: Most requests are <2KB
3. **No database joins in status endpoints**: Cache hits should be high
4. **Config.json is cached aggressively**: Not downloading 10KB per second
5. **Version.json is no-cache but lightweight**: 300B, minimal overhead

### ⚠️ Risk Areas:
1. **Status endpoint database lookups**:
   - If `/api/exambro-status/token`, `/api/exambro-status/peringatan` hit database directly without cache
   - 50 req/sec + 33 req/sec = 83 DB queries/sec = **POTENTIAL BOTTLENECK**
   - Need to check if these endpoints are cached or hit DB every time

2. **Simultaneous connection growth**:
   - If each of 400 users holds persistent connection (WebSocket style)
   - With PHP-FPM max_children=120, you can only serve 120 concurrent PHP processes
   - 400 users > 120 processes = **Requests will queue**

3. **Memory pressure**:
   - If config parsing happens on every request (not cached in memory)
   - 133 req/sec × average 8-12 opcodes per callback = memory thrashing

---

## 6. Detailed Bottleneck Analysis

### Bottleneck #1: Status Endpoint Database Load
**Current situation** (needs verification):
```
/api/exambro-status/token    → Queries what? (users.pin_enabled? token_info?)
/api/exambro-status/peringatan → Queries what? (announcements? warnings?)
```

**If uncached**:
```
83 database queries/second × 400 users × 180 seconds (exam duration)
= 5,976,000 database hits during exam
→ Risk: Database connection pool exhaustion, query queue buildup
```

**If cached (Redis/Memcached, 30-60 sec TTL)**:
```
→ Maybe 5-10 queries/second instead of 83
→ Acceptable
```

---

### Bottleneck #2: PHP-FPM Process Pool
**Current config**: max_children=120

**Steady state request handling**:
```
133 requests/second ÷ 120 PHP processes = ~1.1 requests per process per second
Average request processing time = 900ms (if hitting DB every time)
→ ONE process can handle ~1.1 requests/sec if response time = 900ms
→ Actual queue depth = 120 × 1.1 = ~130 requests waiting
```

**IF status endpoints are cached (response time = 50-100ms)**:
```
One process can handle 10+ requests/sec
Total throughput = 120 × 10 = 1,200 req/sec capacity
→ 133 req/sec = only 11% utilization ✅
```

---

### Bottleneck #3: Bandwidth
```
Peak data: 133 req/sec × ~500B average = ~67 KB/sec = 0.5 Mbps
Typical local deployment: 1+ Gbps available
→ Bandwidth NOT a bottleneck ✅
```

---

## 7. Recommendation for 400 Siswa

### **Verdict: MARGINAL - Depends on Status Endpoint Caching**

| Scenario | Outcome | Risk |
|----------|---------|------|
| **Status endpoints cached** (Redis, TTL 30-60s) | ✅ **Safe** | Low. 131 req/sec very manageable |
| **Status endpoints hit DB every time** | ⚠️ **Risky** | High. 83 DB qry/sec + queue = degradation |
| **With upgraded node (8C/16G)** | ✅ **Safe** | Low. More PHP processes, more memory |
| **With 3-node LB setup** | ✅ **Safe** | Low. Request distribution + shared cache |

---

## 8. Action Items

### IMMEDIATE (Before 400 Siswa Exam)
1. **Check status endpoint implementation**:
   ```
   grep -r "exambro-status\|exambro-info" app/Http/Controllers/
   ↓ Verify if cached or hits DB
   ```

2. **Verify cache configuration**:
   ```
   cat config/cache.php
   ↓ Ensure Redis/Memcached is configured (not file cache for status)
   ↓ File cache under 83 req/sec = thrashing
   ```

3. **Check endpoint SQL queries**:
   ```
   Enable query log temporarily
   Run 50-100 concurrent status check requests
   Count unique queries / sec
   ```

4. **Run load test at 400 concurrent**:
   ```
   Apache Bench or Siege:
   - Ramp up to 400 simultaneous users
   - Have them all hit status endpoints every 8-12 seconds  
   - Monitor:
     * Response times (target < 500ms)
     * Failed requests (target 0%)
     * FPM saturation warnings
     * Database query count
   - Duration: 10 minutes minimum
   ```

### IF BOTTLENECK DETECTED
- Option A: **Implement Redis caching** for status endpoints (easiest)
- Option B: **Upgrade to 8C/16G single node** (fast, cost ~$50-100/month more)
- Option C: **Deploy 3-node LB topology** (recommended for long-term)
- Option D: **Reduce status check frequency** (e.g., every 12s instead of 8s) - not ideal

---

## 9. Recommended Safe Configuration

**For 400 siswa SAFELY**:
```php
// config/cache.php
'default' => 'redis', // Not file cache

// ConfigApiController.php - Cache status responses
public function exambroTokenStatus(Request $request) {
    $cacheKey = 'exam:token:status:' . now()->minute;
    return cache()->remember($cacheKey, 60, function() {
        // DB query here, cached for 60 seconds
        return CbtInfo::getTokenStatus();
    });
}
// Similarly for peringatan, info endpoints

// API Throttle (prevent abuse + layer burst)
'exambro-api' => '200,5' // 200 req per 5 minutes = ~670 req/sec burst capacity
```

---

## 10. Final Estimate: 400 Siswa Readiness

| Criteria | Current 4C/8G | Status |
|----------|---------------|--------|
| Req/sec capacity | 300-500 (if cached) | ✅ Sufficient |
| Peak load (131 req/sec) | 26% of capacity | ✅ Good headroom |
| PHP-FPM processes | 120 max | ✅ Adequate if response <200ms |
| Concurrent connections | ~120 available | ⚠️ Marginal (re-use helps) |
| Database layer | Unknown (needs check) | ❌ **VERIFY IMMEDIATELY** |
| Network bandwidth | 1+ Gbps | ✅ Excellent |

**CONFIDENCE**: 
- **80%** safe IF status endpoints are cached
- **40%** safe IF status endpoints hit DB every time  
- **95%** safe if upgraded to 8C/16G single node
- **98%** safe with 3-node LB setup

**RECOMMENDATION**: Before exam day, run load test with 400 concurrent users hitting status endpoints continuously for 15+ minutes. Check database load during test.
