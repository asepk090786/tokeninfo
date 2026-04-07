# LB Rollout Checklist (4 Backends)

## 1) DNS and Entry Point
- Point domain `token.sman1pontang.biz.id` to LB public IP.
- Keep APK and web using a single public domain only.

## 2) Backend Node Requirements (all 4 nodes)
- Same code version and same `.env` values.
- `APP_KEY` must be identical on all nodes.
- Session and cache must use shared Redis:
  - `SESSION_DRIVER=redis`
  - `CACHE_STORE=redis`
- Verify app endpoint on each node:
  - `http://<backend-ip>/api/config/health` returns HTTP 200.

## 3) LB Configuration
- Use one of these templates:
  - `scripts/lb/nginx-lb.conf.example`
  - `scripts/lb/haproxy.cfg.example`
- Update backend IPs before apply.
- Reload service and confirm no syntax errors.

## 4) App Config URLs (must remain single-domain)
- `https://token.sman1pontang.biz.id/api/version.json`
- `https://token.sman1pontang.biz.id/api/config.json`
- `https://token.sman1pontang.biz.id/exambro`

## 5) Smoke Test
- Run from client machine:
  - `curl -I https://token.sman1pontang.biz.id/api/version.json`
  - `curl -I https://token.sman1pontang.biz.id/api/config/health`
  - Open `https://token.sman1pontang.biz.id/exambro`
- Confirm no backend IP/domain leaks in redirects.

## 6) Load Test (post-cutover)
- Test 300, 400, 500 concurrent users.
- Track:
  - failed requests
  - p95/p99 response time
  - php-fpm saturation
  - redis memory and ops

## 7) Rollback Plan
- Keep old single-node DNS target ready.
- If failure rate increases materially, repoint DNS to previous stable target.
- Preserve same domain so APK does not need changes.
