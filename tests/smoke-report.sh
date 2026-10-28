#!/usr/bin/env bash
# Smoke test for --report flag (regression guard)
set -euo pipefail
cd "$(dirname "$0")/.." || exit 1

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

./bin/forensic --root=. --section=stats --report="$TMP/report.md" > /dev/null

[ -f "$TMP/report.md" ] || { echo "FAIL: report not created"; exit 1; }
grep -q "# Bona Forensic Audit Report" "$TMP/report.md" || { echo "FAIL: missing header"; exit 1; }
grep -q "## Executive Summary" "$TMP/report.md" || { echo "FAIL: missing exec summary"; exit 1; }
grep -q "## Findings" "$TMP/report.md" || { echo "FAIL: missing findings section"; exit 1; }
grep -q "Read-only audit" "$TMP/report.md" || { echo "FAIL: missing read-only disclaimer"; exit 1; }

# No duplicate PASS bullet names (D.1 dedup regression guard)
DUPS=$(grep -oE '^- \*\*[^*]+\*\*' "$TMP/report.md" | sort | uniq -d | wc -l | tr -d ' ')
[ "$DUPS" -eq 0 ] || { echo "FAIL: $DUPS duplicate PASS names in report"; exit 1; }

echo "PASS: smoke-report"
