#!/usr/bin/env bash
# Summarise both PHPUnit suites, including bootstrap failures, without masking
# the original test step's exit status. Called by an always-running CI step.
set -euo pipefail
: "${RUNNER_TEMP:?}" "${GITHUB_STEP_SUMMARY:?}" "${TITLE:?}"

escape() {
    local value=${1//'%'/'%25'}
    value=${value//$'\r'/'%0D'}
    printf '%s' "${value//$'\n'/'%0A'}"
}

report() {
    local suite=$1 outcome=$2 log="$RUNNER_TEMP/$1.out" totals failed
    if [ ! -s "$log" ]; then
        echo "**${suite} suite**: did not run" >> "$GITHUB_STEP_SUMMARY"
        return
    fi
    totals=$(grep -E '^(OK \(|Tests: )' "$log" | tail -n 1 || true)
    failed=$(grep -E '^[0-9]+\) ' "$log" || true)
    if [ -z "$totals" ]; then
        totals='No PHPUnit totals line. Last lines of output:'
        failed=$(grep -v '^[[:space:]]*$' "$log" | tail -n 5 || true)
    fi
    {
        echo "**${suite} suite** (${outcome})"
        echo '```'
        printf '%s\n' "$totals"
        if [ -n "$failed" ]; then printf '%s\n' "$failed"; fi
        echo '```'
    } >> "$GITHUB_STEP_SUMMARY"
    if [ "$outcome" = success ]; then
        echo "::notice title=${TITLE} - ${suite} suite::$(escape "$totals")"
    else
        echo "::error title=${TITLE} - ${suite} suite::$(escape "${totals}${failed:+$'\n'$failed}")"
    fi
}

echo "### ${TITLE}" >> "$GITHUB_STEP_SUMMARY"
report unit "${UNIT_OUTCOME:-skipped}"
report security "${SECURITY_OUTCOME:-skipped}"
