#!/usr/bin/env bash
# Load KEY=value pairs from a dotenv file without expanding $ in values.
#
# Usage:
#   source scripts/lib/load-dotenv.sh
#   dotenv_load /path/to/.env

dotenv_load() {
    local env_file="$1"
    local line key value

    if [[ ! -f "$env_file" ]]; then
        echo "dotenv_load: missing file: ${env_file}" >&2
        return 1
    fi

    while IFS= read -r line || [[ -n "$line" ]]; do
        line="${line%$'\r'}"
        [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue

        if [[ "$line" =~ ^[[:space:]]*export[[:space:]]+(.+)$ ]]; then
            line="${BASH_REMATCH[1]}"
        fi

        if [[ ! "$line" =~ ^([A-Za-z_][A-Za-z0-9_]*)=(.*)$ ]]; then
            continue
        fi

        key="${BASH_REMATCH[1]}"
        value="${BASH_REMATCH[2]}"

        if [[ "$value" == \'*\' ]]; then
            value="${value:1:${#value}-2}"
        elif [[ "$value" == \"*\" ]]; then
            value="${value:1:${#value}-2}"
        fi

        export "${key}=${value}"
    done < "$env_file"
}
