#!/bin/bash
# Live checks for Dipstick against the Craft 6 harness (~/Sites/plugin-testing-craft6).
#
# Fixtures (all removed at the end, even on failure):
#   app/Dipstick/LeakyContext.php + app/Providers/DipstickFixtureProvider.php  a deliberately leaky singleton
#     (registered in bootstrap/providers.php under bin/harness-lock), from tests/Harness/app/
#   config/craft/dipstick.php        settings for the run (project config races other sessions)
#   resources/views/dipstick/*       Twig + Blade parity templates
#   dipstick_editor user, dipstick_* GraphQL schemas/tokens, the `dipstickfr` site (MULTISITE=0 skips it)
set -uo pipefail
exec < /dev/null
H=/Users/jholt/Sites/plugin-testing-craft6
SRC=$(cd "$(dirname "$0")" && pwd)
U=https://plugin-testing-craft6.ddev.site
cd "$H" || exit 2
pass=0 fail=0
ok() { if [ "$2" = "$3" ]; then echo "✓ $1"; pass=$((pass+1)); else echo "✗ $1 (got '$2', want '$3')"; fail=$((fail+1)); fi; }
tk() { ddev artisan tinker --execute "$1" 2>/dev/null | grep -v WARNING | tail -1; }
lock() { "$H/bin/harness-lock" "$@"; }
# py <expression over `r` (parsed JSON on stdin)>. eval() only ever runs the expressions written in this script.
py() { python3 -I -c 'import json,sys
try: r=json.load(sys.stdin)
except Exception: print("not json"); sys.exit()
j=lambda *v: "/".join(str(x) for x in v)
rules=lambda src=None: sorted({f["rule"] for f in r["findings"] if src is None or f["source"]==src})
find=lambda rule, src=None: next((f for f in r["findings"] if f["rule"]==rule and (src is None or f["source"]==src)), None)
print(eval(sys.argv[1]))' "$1"; }
# `ddev artisan` folds every failing exit code into 1; run artisan directly where the code matters.
art() { docker exec -w /var/www/html ddev-plugin-testing-craft6-web php artisan "$@"; }
scan() { art craft:dipstick:scan --json "$@" 2>/dev/null; }
probe() { art craft:dipstick:probe --json "$@" 2>/dev/null | grep '^DIPSTICK-JSON:' | sed 's/^DIPSTICK-JSON://'; }
conf() { # conf '<php array items>' → config/craft/dipstick.php, lint-checked, then moved into place in one step
  local tmp="$H/storage/dipstick-config.tmp.php"
  { echo '<?php'; echo; echo '// Written by craft-dipstick/tests/Harness/check.sh; removed when it finishes.'; echo "return [$1];"; } > "$tmp"
  if ! docker exec ddev-plugin-testing-craft6-web php -l /var/www/html/storage/dipstick-config.tmp.php >/dev/null 2>&1; then
    echo "✗ conf: refusing to install an invalid config file"; rm -f "$tmp"; fail=$((fail+1)); return 1
  fi
  lock mv "$tmp" "$H/config/craft/dipstick.php"
  sleep 2 # PHP-FPM's opcache revalidates every 2 s
}
mail_count() { ddev exec "curl -s 'http://127.0.0.1:8025/api/v1/search?query=subject%3A$1'" 2>/dev/null | python3 -I -c 'import json,sys
try: print(json.load(sys.stdin).get("messages_count", 0))
except Exception: print("mailpit?")'; }
mail_purge() { ddev exec "curl -s -X DELETE 'http://127.0.0.1:8025/api/v1/search?query=subject%3A$1'" >/dev/null 2>&1; }
# Craft allows 5 logins a minute per IP, shared with every other session on the harness: wait it out.
login() { # login <username> <password> → cookie jar
  local j x code tries=0; j=$(mktemp)
  while :; do
    curl -sk -c "$j" -o /dev/null "$U/admin/login"
    x=$(awk '$6=="XSRF-TOKEN"{print $7}' "$j" | tail -1 | python3 -I -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))')
    code=$(curl -sk -b "$j" -c "$j" -o /dev/null -w '%{http_code}' -H 'Accept: application/json' -H "X-XSRF-TOKEN: $x" -X POST "$U/actions/users/login" --data-urlencode "loginName=$1" --data-urlencode "password=$2")
    [ "$code" = 429 ] && [ $tries -lt 8 ] || break
    tries=$((tries+1)); sleep 10
  done
  [ "$code" = 200 ] || echo "login $1 failed: $code" >&2
  echo "$j"
}
xsrf() { awk '$6=="XSRF-TOKEN"{print $7}' "$1" | tail -1 | python3 -I -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))'; }
post() { local jar=$1; shift; curl -sk -b "$jar" -c "$jar" -X POST -H "X-XSRF-TOKEN: $(xsrf "$jar")" -o /dev/null -w '%{http_code}' "$@"; }
# CP pages are Inertia: the content arrives JSON-escaped inside the page.
cp_page() { curl -sk -b "$1" "$U/admin/$2" | python3 -I -c 'import sys; s = sys.stdin.read()
for a, b in (("\\\"", "\""), ("\\/", "/"), ("\\n", "\n"), ("\\u003C", "<"), ("\\u003E", ">"), ("\\u0026", "&"), ("\\u0027", chr(39))): s = s.replace(a, b)
print(s)'; }

fixture_on() {
  mkdir -p "$H/app/Dipstick" "$H/resources/views/dipstick"
  cp "$SRC/app/DipstickLeakyContext.php" "$H/app/Dipstick/LeakyContext.php"
  cp "$SRC/app/DipstickFixtureProvider.php" "$H/app/Providers/DipstickFixtureProvider.php"
  grep -q DipstickFixtureProvider "$H/bootstrap/providers.php" || sed -i '' 's/^    AppServiceProvider::class,$/    AppServiceProvider::class,\
    App\\Providers\\DipstickFixtureProvider::class,/' "$H/bootstrap/providers.php"
}
fixture_off() {
  # The provider line goes first, so no request ever boots a provider whose file is gone.
  sed -i '' '/DipstickFixtureProvider::class,/d' "$H/bootstrap/providers.php"
  rm -f "$H/app/Providers/DipstickFixtureProvider.php" "$H/app/Dipstick/LeakyContext.php"
  rmdir "$H/app/Dipstick" 2>/dev/null || true
  rm -f "$H/config/craft/dipstick.php"
}
export -f fixture_on fixture_off; export H SRC
cleanup() {
  lock bash -c fixture_off
  rm -rf "$H/resources/views/dipstick"
  lock ddev artisan tinker --execute "require 'storage/dipstick-cleanup.php';" >/dev/null 2>&1
  rm -f "$H"/storage/dipstick-{fixtures,multisite-fixtures,cleanup}.php
  mail_purge dipstick-sandbox; mail_purge dipstick-control
}
trap cleanup EXIT

cp "$SRC/fixtures.php" "$H/storage/dipstick-fixtures.php"
cp "$SRC/multisite-fixtures.php" "$H/storage/dipstick-multisite-fixtures.php"
cp "$SRC/cleanup.php" "$H/storage/dipstick-cleanup.php"
lock bash -c fixture_on
mkdir -p "$H/resources/views/dipstick" && cp "$SRC"/templates/{twig.twig,blade.blade.php} "$H/resources/views/dipstick/"
conf "'probeRequests' => 3, 'probeRender' => true"
# Project-config writes race other sessions (Stale/BusyResourceException): retry in a fresh process.
retry_tk() { local out i; for i in 1 2 3 4 5 6; do out=$(tk "$1"); [ "$out" = "$2" ] && break; sleep 3; done; echo "$out"; }
ok "fixtures (non-admin user, GraphQL schemas)" "$(retry_tk "require 'storage/dipstick-fixtures.php';" ok)" "ok"

# ---- Static scan ----
S=$(scan --no-probe --fail-on=none)
ok "static: singleton keeps the request, user and site (3 captures)" "$(echo "$S" | py 'j(*[e["kind"] for e in find("singleton-captures-request","app")["evidence"] if "LeakyContext" in e["file"]])')" "request/user/site"
ok "static: request-scoped value kept in a static" "$(echo "$S" | py '[e["target"] for e in find("static-request-state","app")["evidence"] if "LeakyContext" in e["file"]]')" "['App\\\\Dipstick\\\\LeakyContext::\$lastUser']"
ok "static: growing static array" "$(echo "$S" | py 'any(e["target"]=="App\\Dipstick\\LeakyContext::$seenUrls" for e in find("static-array-growth","app")["evidence"])')" "True"
ok "static: file shown relative to the project, with line" "$(echo "$S" | py 'j(find("static-request-state","app")["evidence"][0]["file"], find("static-request-state","app")["evidence"][0]["line"] > 0)')" "app/Dipstick/LeakyContext.php/True"
ok "static: plugins shown by package name" "$(echo "$S" | py 'sorted({f["source"] for f in r["findings"] if f["source"].startswith("justinholtweb/craft-d")})')" "['justinholtweb/craft-dipstick']"
ok "static: Dipstick passes its own audit" "$(echo "$S" | py 'rules("justinholtweb/craft-dipstick")')" "['static-clean']"
ok "static: no absolute paths anywhere in the report" "$(echo "$S" | grep -cE '/var/www|/Users/|/home/')" "0"
ok "static: templates counted (Blade checked, Twig counted)" "$(echo "$S" | py 'j(r["meta"]["files.blade"] > 0, r["meta"]["files.twig"] > 0)')" "True/True"
ok "static: score and counts add up" "$(echo "$S" | py 'r["score"] == max(0, 100 - 25*r["counts"]["critical"] - 10*r["counts"]["warning"] - 3*r["counts"]["notice"])')" "True"
scan --no-probe >/dev/null; ok "exit 1 when a finding is at --fail-on (critical)" "$?" "1"
scan --no-probe --fail-on=none >/dev/null; ok "exit 0 with --fail-on=none" "$?" "0"
scan --no-probe --fail-on=bogus >/dev/null; ok "exit 2 for a bad --fail-on" "$?" "2"

# Ignore (by evidence fingerprint) and exclude (by displayed path), from config/craft/dipstick.php.
FP=$(echo "$S" | py '[e["fingerprint"] for e in find("static-request-state","app")["evidence"] if "LeakyContext" in e["file"]][0]')
conf "'probeRequests' => 3, 'ignore' => \"$FP  # checked\""
ok "ignore: a fingerprinted row is dropped" "$(scan --no-probe --fail-on=none | py '[e["target"] for e in (find("static-request-state","app") or {"evidence": []})["evidence"] if "LeakyContext" in e["file"]]')" "[]"
conf "'probeRequests' => 3, 'excludePaths' => 'app/Dipstick/*'"
ok "exclude: app/Dipstick/* isn't scanned" "$(scan --no-probe --fail-on=none | grep -c 'LeakyContext')" "0"
conf "'probeRequests' => 3, 'probeRender' => true"

# ---- Runtime probe ----
mail_purge dipstick-sandbox; mail_purge dipstick-control
tk "Illuminate\Support\Facades\Mail::raw('control', fn (\$m) => \$m->to('control@dipstick.test')->subject('dipstick-control')); echo 1;" >/dev/null
sleep 1
ok "control: mail outside the sandbox reaches Mailpit" "$(mail_count dipstick-control)" "1"
P=$(probe)
ok "probe: singleton keeps an earlier request" "$(echo "$P" | py 'find("probe-request-leak")["target"]')" 'App\Dipstick\LeakyContext->request'
ok "probe: singleton keeps an earlier user (seen on later requests)" "$(echo "$P" | py 'j(find("probe-user-leak")["target"], find("probe-user-leak")["occurrences"] >= 1)')" 'App\Dipstick\LeakyContext->user/True'
# Other sessions may have sites of their own: one size per simulated request, across every site.
ok "probe: static array grows every request" "$(echo "$P" | py 'find("probe-static-growth")["evidence"][0]["sizes"] == " → ".join(str(i) for i in range(1, r["meta"]["requests"] + 1))')" "True"
ok "probe: evidence names the file in the project" "$(echo "$P" | py 'find("probe-request-leak")["evidence"][0]["file"]')" "app/Dipstick/LeakyContext.php"
ok "probe: no absolute paths in probe output" "$(echo "$P" | grep -cE '/var/www|/Users/|/home/')" "0"
ok "sandbox: database writes from simulated requests rolled back" "$(tk 'echo Illuminate\Support\Facades\DB::table("dipstick_scans")->where("status","sandbox")->count();')" "0"
sleep 1
ok "sandbox: mail from simulated requests never sent" "$(mail_count dipstick-sandbox)" "0"
PN=$(probe --no-render)
ok "probe --no-render: request + user leaks still found" "$(echo "$PN" | py 'j(find("probe-request-leak") is not None, find("probe-user-leak") is not None)')" "True/True"
ok "probe --no-render: no page rendered, so no per-request growth" "$(echo "$PN" | py 'j(find("probe-static-growth"), r["meta"]["rendered"])')" "None/False"
ok "probe: requests per site from settings" "$(echo "$P" | py 'r["meta"]["requests"] // r["meta"]["sites"]')" "3"

# ---- Full audit (static + probe), stored ----
A=$(scan --fail-on=none)
AID=$(echo "$A" | py 'r["id"]')
ok "audit: static + runtime findings stored together" "$(echo "$A" | py 'j(any(f["kind"]=="runtime" for f in r["findings"]), any(f["kind"]=="static" for f in r["findings"]), any(f["kind"]=="template" or f["source"]=="templates" for f in r["findings"]))')" "True/True/True"
ok "audit: stored with score and counts" "$(tk "echo json_encode(array_intersect_key(app(JustinHoltWeb\Dipstick\Audit\Scans::class)->find($AID), array_flip(['status','score','probed'])));")" "$(echo "$A" | py 'json.dumps({"status":"done","score":r["score"],"probed":True}, separators=(",",":"))')"
ok "audit: findings translated when shown (fr)" "$(tk "app()->setLocale('fr'); echo JustinHoltWeb\Dipstick\Findings\Rules::describe(new JustinHoltWeb\Dipstick\Findings\Finding('probe-site-leak', JustinHoltWeb\Dipstick\Findings\Severity::Critical, 'runtime', 'app', 'dipstickfr', 'X->site'))['title'];")" "X->site garde le site d’une requête précédente"
ok "audit: severity labels translated (fr)" "$(tk "app()->setLocale('fr'); echo JustinHoltWeb\Dipstick\Findings\Severity::Warning->label();")" "Avertissement"

# ---- Multi-site: the probe runs per site ----
if [ "${MULTISITE:-1}" = 1 ]; then
  ok "multisite: dipstickfr created" "$(lock ddev artisan tinker --execute "require 'storage/dipstick-multisite-fixtures.php';" 2>/dev/null | grep -v WARNING | tail -1 | grep -cE '^[0-9]+$')" "1"
  M=$(probe)
  ok "multisite: every site probed" "$(echo "$M" | py 'r["meta"]["sites"] >= 2')" "True"
  ok "multisite: a singleton keeping the site is caught" "$(echo "$M" | py 'find("probe-site-leak")["target"]')" 'App\Dipstick\LeakyContext->site'
  ok "multisite: it captured a non-primary site (probed first) and kept it on another" "$(echo "$M" | py 'all(e["fromSite"] == e["held"] != e["site"] and e["fromSite"] != "default" for e in find("probe-site-leak")["evidence"])')" "True"
  ok "multisite: request leaks reported on both sites" "$(echo "$M" | py '{"default", "dipstickfr"} <= {e["site"] for e in find("probe-request-leak")["evidence"]}')" "True"
  ok "multisite: the French home page rendered in the sandbox" "$(echo "$M" | py 'all(f["site"] != "dipstickfr" for f in r["findings"] if f["rule"]=="probe-render-error")')" "True"
  # Without the leaky fixture, every site is clean.
  conf "'probeRequests' => 2, 'excludePaths' => 'app/Dipstick/*', 'probeSkip' => ['App\\\\Dipstick\\\\LeakyContext']"
  ok "multisite: a clean service passes on every site" "$(probe --no-render | py 'sorted(f["site"] for f in r["findings"] if f["rule"]=="probe-clean" and f["site"] in ("default","dipstickfr"))')" "['default', 'dipstickfr']"
  conf "'probeRequests' => 3, 'probeRender' => true"
else
  echo "- multisite section skipped (MULTISITE=0)"
fi

# ---- Control panel (admins only) ----
CP=$(login admin claudepassword)
ED=$(login dipstick_editor dipstickpassword)
PAGE=$(cp_page "$CP" dipstick)
ok "CP: admin sees the latest audit" "$(echo "$PAGE" | grep -o 'id="dipstick-score">[0-9]*' | grep -oE '[0-9]+$')" "$(tk 'echo app(JustinHoltWeb\Dipstick\Audit\Scans::class)->recent(1)[0]["score"];')"
ok "CP: findings with fix recipes" "$(echo "$PAGE" | grep -c 'data-rule="probe-request-leak"')/$(echo "$PAGE" | grep -c 'How to fix it')" "1/$(echo "$PAGE" | grep -c 'How to fix it')"
ok "CP: no absolute paths in the page" "$(echo "$PAGE" | grep -cE '/var/www|/Users/')" "0"
ok "CP: an older audit by id" "$(curl -sk -b "$CP" -o /dev/null -w '%{http_code}' "$U/admin/dipstick/audits/$AID")" "200"
ok "CP: unknown audit 404s" "$(curl -sk -b "$CP" -o /dev/null -w '%{http_code}' "$U/admin/dipstick/audits/999999")" "404"
ok "CP: settings page loads" "$(curl -sk -b "$CP" -o /dev/null -w '%{http_code}' "$U/admin/settings/plugins/dipstick")" "200"
ok "CP: non-admin is refused the report" "$(curl -sk -b "$ED" -o /dev/null -w '%{http_code}' "$U/admin/dipstick")" "403"
ok "CP: non-admin can't start an audit" "$(post "$ED" "$U/admin/dipstick/run")" "403"
ok "CP: logged out → login" "$(curl -sk -o /dev/null -w '%{http_code}' "$U/admin/dipstick")" "302"
ok "CP: logged-out POST refused (CSRF)" "$(curl -sk -X POST -o /dev/null -w '%{http_code}' "$U/admin/dipstick/run")" "419"
ok "CP: sidebar item for admins only" "$(tk '$p=CraftCms\Cms\Support\Facades\Plugins::getPlugin("dipstick"); $a=CraftCms\Cms\User\Elements\User::find()->admin(true)->one(); $e=CraftCms\Cms\User\Elements\User::find()->username("dipstick_editor")->one(); CraftCms\Cms\craftAuth()->setUser($a); $x=$p->getCpNavItem() !== null; CraftCms\Cms\craftAuth()->setUser($e); echo json_encode([$x, $p->getCpNavItem() !== null]);')" "[true,false]"
BEFORE=$(tk 'echo Illuminate\Support\Facades\DB::table("dipstick_scans")->count();')
ok "CP: admin starts an audit (queued)" "$(post "$CP" "$U/admin/dipstick/run")" "302"
ok "CP: one audit queued, by the admin" "$(tk 'echo json_encode(Illuminate\Support\Facades\DB::table("dipstick_scans")->orderByDesc("id")->first(["status","trigger"]));')" '{"status":"queued","trigger":"cp"}'
post "$CP" "$U/admin/dipstick/run" >/dev/null
ok "CP: a second click while queued adds nothing" "$(tk 'echo Illuminate\Support\Facades\DB::table("dipstick_scans")->count();')" "$((BEFORE+1))"
ddev artisan queue:work --queue=default --stop-when-empty --max-time=300 >/dev/null 2>&1
ok "CP: the queued audit ran (probe in a child process)" "$(tk 'echo json_encode(Illuminate\Support\Facades\DB::table("dipstick_scans")->orderByDesc("id")->first(["status","probed"]));')" '{"status":"done","probed":1}'
ok "CP: the page shows it" "$(cp_page "$CP" dipstick | grep -c 'data-ready="0"')" "1"

# ---- GraphQL: readiness summary only ----
gq() { curl -sk -H "Authorization: Bearer $(printf 'dp%s' "$1" | awk '{ s=$0; while (length(s) < 32) s=s "0"; print s }')" -H 'Content-Type: application/json' \
  --data "$(python3 -I -c 'import json,sys; print(json.dumps({"query": sys.argv[1]}))' "$2")" "$U/actions/graphql/api"; }
gqe() { python3 -I -c 'import json,sys
try: r=json.load(sys.stdin)
except Exception: print("not json"); sys.exit()
if r.get("errors"): print("ERR:" + "; ".join(e.get("message","") for e in r["errors"])); sys.exit()
d=r.get("data") or {}; j=lambda *v: "/".join(str(x) for x in v)
print(eval(sys.argv[1]))' "$1"; }
LATEST=$(tk '$s=app(JustinHoltWeb\Dipstick\Audit\Scans::class)->latestDone(); echo $s["score"]."/".$s["grade"]."/".$s["counts"]["critical"];')
ok "gql: readiness summary for a schema with the component" "$(gq readiness '{ dipstickReadiness { score grade ready critical warning notice scannedAt } }' | gqe 'j(d["dipstickReadiness"]["score"], d["dipstickReadiness"]["grade"], d["dipstickReadiness"]["critical"])')" "$LATEST"
ok "gql: only summary fields exist (no files, classes or findings)" "$(gq readiness '{ __type(name: "DipstickReadiness") { fields { name } } }' | gqe '",".join(sorted(f["name"] for f in d["__type"]["fields"]))')" "critical,grade,notice,ready,scannedAt,score,warning"
ok "gql: a schema without the component can't query it" "$(gq none '{ dipstickReadiness { score } }' | gqe 'd')" 'ERR:Cannot query field "dipstickReadiness" on type "Query".'
ok "gql: no paths in the response" "$(gq readiness '{ dipstickReadiness { score grade ready critical warning notice scannedAt } }' | grep -cE '/var/www|/Users/|\.php')" "0"

# ---- Twig and Blade: readiness for admins only ----
rd() { curl -sk ${2:+-b "$2"} "$U/dipstick/$1" | grep -o '<p id="readiness">[^<]*' | sed 's/<p id="readiness">//; s/^ *//; s/ *$//'; }
WANT=$(tk '$s=app(JustinHoltWeb\Dipstick\Audit\Scans::class)->latestDone(); echo $s["score"]."/".$s["grade"]."/".($s["ready"]?"ready":"not-ready")."/".$s["counts"]["critical"];')
ok "twig: admin sees the readiness summary" "$(rd twig "$CP")" "$WANT"
ok "blade: admin sees the same" "$(rd blade "$CP")" "$WANT"
ok "twig: guest gets nothing" "$(rd twig)" "none"
ok "blade: guest gets nothing" "$(rd blade)" "none"
ok "twig/blade: a signed-in non-admin gets nothing" "$(rd twig "$ED")/$(rd blade "$ED")" "none/none"

# ---- Uninstall and reinstall ----
lock bash -c 'ddev artisan craft:plugin:uninstall dipstick --no-interaction >/dev/null 2>&1'
ok "uninstall: tables dropped" "$(tk 'echo json_encode([Illuminate\Support\Facades\Schema::hasTable("dipstick_scans"), Illuminate\Support\Facades\Schema::hasTable("dipstick_findings")]);')" "[false,false]"
lock bash -c 'ddev artisan craft:plugin:install dipstick --no-interaction >/dev/null 2>&1'
ok "reinstall: tables back, audit runs" "$(scan --no-probe --fail-on=none | py 'j(r["id"] >= 1, len(r["findings"]) > 0)')" "True/True"

echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
