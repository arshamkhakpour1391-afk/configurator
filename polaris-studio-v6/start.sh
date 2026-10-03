#!/usr/bin/env bash
cd "$(dirname "$0")"
export POLARIS_HOST="${POLARIS_HOST:-127.0.0.1}"
exec python3 start.py
