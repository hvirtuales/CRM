#!/usr/bin/env sh
# Compila Tailwind CSS sin Node.js usando el CLI autónomo (versión fijada y verificada por SHA-256).
set -eu
VERSION="v4.1.11"
SHA256="64805b84af4292e043ea6f86d242f191c0ac75359c1a498455dfe6c642afdbab"
DIR="$(cd "$(dirname "$0")" && pwd)"
BIN="$DIR/tailwindcss-linux-x64"
if [ ! -x "$BIN" ]; then
  curl -fsSL -o "$BIN" "https://github.com/tailwindlabs/tailwindcss/releases/download/${VERSION}/tailwindcss-linux-x64"
  echo "${SHA256}  ${BIN}" | sha256sum -c -
  chmod +x "$BIN"
fi
"$BIN" -i "$DIR/../resources/css/app.css" -o "$DIR/../public/assets/css/app.css" --minify
